<?php

namespace App\Services\Doctor;

use App\Enums\UserRole;
use App\Models\DoctorRequest;
use App\Models\DoctorRequestEvent;
use App\Models\DoctorRequestFile;
use App\Models\Order;
use App\Models\User;
use App\Services\Coach\CoachAccess;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Doctor Requests (Craig's guide 4.1 and 8.1): shared work items between doctors, Operations and coaches.
 *
 * Who may do what:
 *  - Create: admin (Operations), doctor, health coach.
 *  - See: admin sees every request; everybody else sees requests they asked for, are assigned to or take part in.
 *  - Open (Pending > In review), start (> In progress), submit a response (> Submitted): the assignees.
 *  - Complete: the requester or admin. Nobody else, and a completed request is closed.
 *  - Reference a patient: admin any patient; a doctor or coach only patients they may already see (CoachAccess).
 * Every change leaves an event row. Messages carry no request text, patient name or health detail.
 */
class DoctorRequestService
{
    public function __construct(
        private readonly CoachAccess $access,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    public function canCreate(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Doctor, UserRole::HealthCoach], true);
    }

    public function canView(User $user, DoctorRequest $request): bool
    {
        return $user->role === UserRole::Admin
            || $request->requester_id === $user->id
            || $request->people()->where('users.id', $user->id)->exists();
    }

    public function isAssignee(User $user, DoctorRequest $request): bool
    {
        return $request->assignees()->where('users.id', $user->id)->exists();
    }

    public function canComplete(User $user, DoctorRequest $request): bool
    {
        return $user->role === UserRole::Admin || $request->requester_id === $user->id;
    }

    /** Requests this person can list, newest first. */
    public function visibleTo(User $user)
    {
        $q = DoctorRequest::query()->with(['requester', 'assignees', 'refs.patient'])->orderByDesc('id');

        return $user->role === UserRole::Admin ? $q : $q->involving($user);
    }

    /**
     * @param array{title:string, description?:?string, assignee_ids:int[], participant_ids?:int[], refs?:array<int,array{patient_id:int, order_id?:?int}>} $data
     */
    public function create(User $requester, array $data): DoctorRequest
    {
        if (! $this->canCreate($requester)) {
            abort(403);
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Give the request a title.']);
        }

        $assigneeIds = collect($data['assignee_ids'] ?? [])->map(fn ($i) => (int) $i)->unique()->values();
        if ($assigneeIds->isEmpty()) {
            throw ValidationException::withMessages(['assignee_ids' => 'Choose at least one person to do the request.']);
        }

        $assignees = User::whereIn('id', $assigneeIds)->where('role', UserRole::Doctor->value)->get();
        if ($assignees->count() !== $assigneeIds->count()) {
            throw ValidationException::withMessages(['assignee_ids' => 'Requests can only be assigned to doctors.']);
        }

        $participantIds = collect($data['participant_ids'] ?? [])->map(fn ($i) => (int) $i)->unique()
            ->reject(fn ($i) => $assigneeIds->contains($i) || $i === $requester->id)->values();
        $participants = User::whereIn('id', $participantIds)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Doctor->value, UserRole::HealthCoach->value])->get();
        if ($participants->count() !== $participantIds->count()) {
            throw ValidationException::withMessages(['participant_ids' => 'Participants must be staff.']);
        }

        $refs = $this->checkedRefs($requester, $data['refs'] ?? []);

        $request = DB::transaction(function () use ($requester, $title, $data, $assignees, $participants, $refs) {
            $request = DoctorRequest::create([
                'requester_id' => $requester->id,
                'title' => mb_substr($title, 0, 160),
                'description' => $data['description'] ?? null,
                'status' => DoctorRequest::PENDING,
            ]);

            foreach ($assignees as $a) {
                $request->people()->attach($a->id, ['role' => 'assignee']);
            }
            foreach ($participants as $p) {
                $request->people()->attach($p->id, ['role' => 'participant']);
            }
            foreach ($refs as $ref) {
                $request->refs()->create($ref);
            }

            $this->log($request, $requester, 'created', null, DoctorRequest::PENDING);

            return $request;
        });

        $this->tell($assignees, 'doctor.request_new', 'You have a new request in Zapmed', 'A new request is waiting for you. Sign in to Zapmed to open it.', $request);

        return $request;
    }

    /** An assignee opens a pending request: Pending becomes In review. Opening again changes nothing. */
    public function open(User $user, DoctorRequest $request): void
    {
        if ($request->status === DoctorRequest::PENDING && $this->isAssignee($user, $request)) {
            $this->move($request, $user, DoctorRequest::IN_REVIEW, ['opened_at' => now()]);
        }
    }

    public function start(User $user, DoctorRequest $request): void
    {
        $this->assertAssignee($user, $request);
        $this->assertStatus($request, [DoctorRequest::PENDING, DoctorRequest::IN_REVIEW]);

        $this->move($request, $user, DoctorRequest::IN_PROGRESS, $request->opened_at ? [] : ['opened_at' => now()]);
    }

    public function submit(User $user, DoctorRequest $request, string $response): void
    {
        $this->assertAssignee($user, $request);
        $this->assertStatus($request, [DoctorRequest::PENDING, DoctorRequest::IN_REVIEW, DoctorRequest::IN_PROGRESS]);

        $response = trim($response);
        if ($response === '') {
            throw ValidationException::withMessages(['response' => 'Write a response before you submit.']);
        }

        $this->move($request, $user, DoctorRequest::SUBMITTED, ['response' => $response, 'submitted_at' => now()]);

        $targets = collect([$request->requester])->filter();
        $this->tell($targets, 'doctor.request_response', 'A request has been answered', 'A response is ready on a request you sent. Sign in to Zapmed to read it.', $request);
    }

    public function complete(User $user, DoctorRequest $request): void
    {
        if (! $this->canComplete($user, $request)) {
            abort(403);
        }
        $this->assertStatus($request, [DoctorRequest::SUBMITTED]);

        $this->move($request, $user, DoctorRequest::COMPLETED, ['completed_at' => now()]);
    }

    /** Attach a file. Anyone who can see the request may add one, until it is completed. */
    public function attach(User $user, DoctorRequest $request, UploadedFile $file): DoctorRequestFile
    {
        if (! $this->canView($user, $request)) {
            abort(403);
        }
        $this->assertStatus($request, [DoctorRequest::PENDING, DoctorRequest::IN_REVIEW, DoctorRequest::IN_PROGRESS, DoctorRequest::SUBMITTED]);

        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, config('doctor.requests.allowed_extensions'), true)) {
            throw ValidationException::withMessages(['file' => 'That file type is not allowed.']);
        }
        if ($file->getSize() > config('doctor.requests.max_file_kb') * 1024) {
            throw ValidationException::withMessages(['file' => 'That file is too big (10 MB at most).']);
        }
        if ($request->files()->count() >= config('doctor.requests.max_files_per_request')) {
            throw ValidationException::withMessages(['file' => 'This request already has the most files allowed.']);
        }

        // Random stored name: the original name is only shown, never used as a path.
        $path = $file->storeAs('doctor-requests/'.$request->id, bin2hex(random_bytes(12)).'.'.$ext, config('doctor.requests.disk'));

        $row = $request->files()->create([
            'uploader_id' => $user->id,
            'path' => $path,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
            'size_bytes' => (int) $file->getSize(),
        ]);

        $this->log($request, $user, 'file', null, null, $row->original_name);

        return $row;
    }

    public function disk()
    {
        return Storage::disk(config('doctor.requests.disk'));
    }

    /** @return array<int,array{patient_id:int, order_id:?int}> */
    private function checkedRefs(User $requester, array $refs): array
    {
        $out = [];

        foreach ($refs as $ref) {
            $patient = User::where('role', UserRole::Patient->value)->find((int) ($ref['patient_id'] ?? 0));
            if (! $patient) {
                throw ValidationException::withMessages(['refs' => 'That patient could not be found.']);
            }

            $allowed = $requester->role === UserRole::Admin || $this->access->canView($requester, $patient);
            if (! $allowed) {
                throw ValidationException::withMessages(['refs' => 'You can only refer to your own patients.']);
            }

            $orderId = ! empty($ref['order_id']) ? (int) $ref['order_id'] : null;
            if ($orderId && ! Order::where('id', $orderId)->where('patient_id', $patient->id)->exists()) {
                throw ValidationException::withMessages(['refs' => 'That order does not belong to the patient.']);
            }

            $out[] = ['patient_id' => $patient->id, 'order_id' => $orderId];
        }

        return $out;
    }

    private function move(DoctorRequest $request, User $user, string $to, array $extra = []): void
    {
        $from = $request->status;
        $request->update(['status' => $to] + $extra);
        $this->log($request, $user, 'status', $from, $to);
    }

    private function assertAssignee(User $user, DoctorRequest $request): void
    {
        if (! $this->isAssignee($user, $request)) {
            abort(403);
        }
    }

    private function assertStatus(DoctorRequest $request, array $allowed): void
    {
        if (! in_array($request->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'This request is '.strtolower($request->label()).', so that is not possible now.']);
        }
    }

    private function log(DoctorRequest $request, ?User $user, string $event, ?string $from, ?string $to, ?string $note = null): void
    {
        DoctorRequestEvent::create([
            'doctor_request_id' => $request->id,
            'user_id' => $user?->id,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    /** OFF until DOCTOR_REQUEST_NOTIFY=true. Never includes the request text or a patient. */
    private function tell($users, string $template, string $subject, string $body, DoctorRequest $request): void
    {
        if (! config('doctor.requests.notify_enabled')) {
            return;
        }

        foreach ($users as $user) {
            try {
                $this->dispatcher->send(new OutboundMessage(
                    templateKey: $template, category: 'transactional', user: $user,
                    subject: $subject, body: $body, meta: ['doctor_request_id' => $request->id],
                ), ['email']);
            } catch (\Throwable $e) {
                Log::error('doctor request: message failed', ['request' => $request->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
