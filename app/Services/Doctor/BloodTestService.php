<?php

namespace App\Services\Doctor;

use App\Enums\UserRole;
use App\Models\BloodTestRequest;
use App\Models\BloodTestResult;
use App\Models\Order;
use App\Models\User;
use App\Services\Coach\CoachAccess;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;
use App\Services\Patient\PatientMessenger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Blood tests (Craig's guide 4.1, 4.2).
 *
 *  - A doctor who treats the patient requests tests: ticked tests, free text, a lab form (chosen or uploaded).
 *    If the order is In review it moves to Pending blood tests; any other status is left alone.
 *  - The patient or a doctor who treats them uploads results. The first result moves a waiting order to
 *    Pending review/feedback. The doctor then reviews it (order back to In review) or cancels it with a reason.
 *  - Results are health records: private disk, readable only by the patient and a doctor who treats them.
 *    Operations and coaches never see results. Blood tests do not block repeats (Craig's guide does not say they do).
 *  - Messages (OFF by default) never name the tests, the medicine or any result.
 */
class BloodTestService
{
    public function __construct(
        private readonly CoachAccess $access,
        private readonly NotificationDispatcher $dispatcher,
        private readonly PatientMessenger $messenger,
        private readonly \App\Services\Operations\MessageTemplates $templates,
    ) {
    }

    public function disk()
    {
        return Storage::disk(config('doctor.blood_tests.disk'));
    }

    /** The patient themself, or a doctor who treats them. Nobody else (not Operations, not a coach). */
    public function canView(User $user, BloodTestRequest $request): bool
    {
        if ($user->id === $request->patient_id) {
            return true;
        }

        return $user->role === UserRole::Doctor && $request->patient && $this->access->canView($user, $request->patient);
    }

    /**
     * @param string[] $tests keys of config doctor.blood_tests.catalogue
     */
    public function request(User $doctor, User $patient, array $tests, ?string $freeText = null, ?string $labForm = null, ?UploadedFile $labFormFile = null, ?Order $order = null): BloodTestRequest
    {
        abort_unless($doctor->role === UserRole::Doctor && $patient->role === UserRole::Patient && $this->access->canView($doctor, $patient), 403);

        $catalogue = config('doctor.blood_tests.catalogue');
        $tests = collect($tests)->map(fn ($t) => (string) $t)->unique()->values();
        if ($tests->diff(array_keys($catalogue))->isNotEmpty()) {
            throw ValidationException::withMessages(['tests' => 'One of the tests is not on the list.']);
        }
        $freeText = $freeText !== null ? trim($freeText) : null;
        if ($tests->isEmpty() && ! $freeText) {
            throw ValidationException::withMessages(['tests' => 'Choose at least one test or write what is needed.']);
        }
        if ($labForm && ! array_key_exists($labForm, config('doctor.blood_tests.lab_forms'))) {
            throw ValidationException::withMessages(['lab_form' => 'That lab form is not on the list.']);
        }
        if ($order && $order->patient_id !== $patient->id) {
            throw ValidationException::withMessages(['order' => 'That order does not belong to the patient.']);
        }

        $formPath = $formName = null;
        if ($labFormFile) {
            $this->checkFile($labFormFile, 'lab_form_file');
            $ext = strtolower($labFormFile->getClientOriginalExtension());
            $formPath = $labFormFile->storeAs('blood-tests/forms', bin2hex(random_bytes(12)).'.'.$ext, config('doctor.blood_tests.disk'));
            $formName = mb_substr(basename($labFormFile->getClientOriginalName()), 0, 200);
        }

        $row = DB::transaction(function () use ($doctor, $patient, $tests, $freeText, $labForm, $formPath, $formName, $order) {
            $row = BloodTestRequest::create([
                'patient_id' => $patient->id,
                'order_id' => $order?->id,
                'requested_by' => $doctor->id,
                'tests' => $tests->all(),
                'free_text' => $freeText ?: null,
                'lab_form' => $labForm ?: null,
                'lab_form_path' => $formPath,
                'lab_form_name' => $formName,
                'status' => BloodTestRequest::REQUESTED,
            ]);

            if ($order && $order->status === 'InReview') {
                $order->transitionTo('PendingBloodTests', 'doctor', ['triggered_by' => $doctor->id, 'notes' => 'Blood tests requested']);
                $row->update(['order_status_before' => 'InReview']);
            }

            return $row;
        });

        $this->tellPatient($row, 'doctor.bloodtest_requested');

        return $row;
    }

    public function uploadResult(User $user, BloodTestRequest $request, UploadedFile $file): BloodTestResult
    {
        $isPatient = $user->id === $request->patient_id;
        abort_unless($isPatient || $this->canView($user, $request), 403);

        if (! $request->isOpen()) {
            throw ValidationException::withMessages(['status' => 'This request is '.$request->status.', so results cannot be added.']);
        }
        $this->checkFile($file, 'file');
        if ($request->results()->count() >= config('doctor.blood_tests.max_files_per_request')) {
            throw ValidationException::withMessages(['file' => 'The most files allowed are already uploaded.']);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('blood-tests/'.$request->id, bin2hex(random_bytes(12)).'.'.$ext, config('doctor.blood_tests.disk'));

        return DB::transaction(function () use ($user, $request, $path, $file, $isPatient) {
            $result = $request->results()->create([
                'uploader_id' => $user->id,
                'uploader_role' => $isPatient ? 'patient' : 'doctor',
                'path' => $path,
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
                'size_bytes' => (int) $file->getSize(),
            ]);

            if ($request->status === BloodTestRequest::REQUESTED) {
                $request->update(['status' => BloodTestRequest::RESULTS_RECEIVED, 'results_at' => now()]);

                $order = $request->order;
                if ($order && $order->status === 'PendingBloodTests') {
                    $order->transitionTo('PendingReviewFeedback', $isPatient ? 'patient' : 'doctor', [
                        'triggered_by' => $user->id, 'notes' => 'Blood test results uploaded',
                    ]);
                }
            }

            return $result;
        });
    }

    /** A doctor who treats the patient has read the results. Needs at least one result file. */
    public function review(User $doctor, BloodTestRequest $request, ?string $note = null): BloodTestRequest
    {
        abort_unless($doctor->role === UserRole::Doctor && $this->canView($doctor, $request), 403);

        if ($request->status !== BloodTestRequest::RESULTS_RECEIVED) {
            throw ValidationException::withMessages(['status' => $request->status === BloodTestRequest::REQUESTED
                ? 'There are no results to review yet.' : 'This request is already '.$request->status.'.']);
        }

        DB::transaction(function () use ($doctor, $request, $note) {
            $request->update([
                'status' => BloodTestRequest::REVIEWED, 'reviewed_at' => now(), 'closed_by' => $doctor->id,
                'review_note' => $note ? trim($note) : null,
            ]);

            $order = $request->order;
            if ($order && $order->status === 'PendingReviewFeedback') {
                $order->transitionTo('InReview', 'doctor', ['triggered_by' => $doctor->id, 'notes' => 'Blood test results reviewed']);
            }
        });

        return $request->fresh();
    }

    /** The doctor who asked cancels it. A reason is required. A waiting order goes back to In review. */
    public function cancel(User $doctor, BloodTestRequest $request, string $reason): BloodTestRequest
    {
        abort_unless($doctor->role === UserRole::Doctor && $request->requested_by === $doctor->id, 403);

        if (! $request->isOpen()) {
            throw ValidationException::withMessages(['status' => 'This request is already '.$request->status.'.']);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Give a reason for cancelling.']);
        }

        DB::transaction(function () use ($doctor, $request, $reason) {
            $request->update([
                'status' => BloodTestRequest::CANCELLED, 'cancelled_at' => now(),
                'cancel_reason' => mb_substr($reason, 0, 255), 'closed_by' => $doctor->id,
            ]);

            $order = $request->order;
            if ($order && in_array($order->status, ['PendingBloodTests', 'PendingReviewFeedback'], true)) {
                $order->transitionTo('InReview', 'doctor', ['triggered_by' => $doctor->id, 'notes' => 'Blood tests cancelled']);
            }
        });

        return $request->fresh();
    }

    private function checkFile(UploadedFile $file, string $field): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, config('doctor.blood_tests.allowed_extensions'), true)) {
            throw ValidationException::withMessages([$field => 'Upload a PDF, JPG or PNG file.']);
        }
        if ($file->getSize() > config('doctor.blood_tests.max_file_kb') * 1024) {
            throw ValidationException::withMessages([$field => 'That file is too big (10 MB at most).']);
        }
    }

    private function tellPatient(BloodTestRequest $r, string $template): void
    {
        if (! config('doctor.blood_tests.notify_enabled') || ! $r->patient) {
            return;
        }

        $t = $this->templates->render($template);
        if ($t === null) {
            return;
        }

        try {
            $this->messenger->send(new OutboundMessage(
                templateKey: $template, category: 'transactional', user: $r->patient, phone: $r->patient->phone,
                subject: $t['subject'], body: $t['body'], meta: ['blood_test_request_id' => $r->id, 'template_version' => $t['version']],
            ));
        } catch (\Throwable $e) {
            Log::error('blood test: message failed', ['request' => $r->id, 'error' => $e->getMessage()]);
        }
    }
}
