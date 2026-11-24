<?php

namespace App\Services\Doctor;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\FollowUpRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\Coach\CoachAccess;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;
use App\Services\Patient\PatientMessenger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Doctor follow-up requests (Craig's guide 4.2, 7, 8.2).
 *
 *  - A doctor who treats the patient asks for a follow-up in 7/14/20/30/60/90 days, tied to one order.
 *  - While it is open (requested or booked) the order's next repeat is blocked, whatever the order status is.
 *    When the order is already delivered/completed it also moves to Pending follow-up request (and to
 *    Pending follow-up profile book once the patient has booked); an order still being prepared is left alone.
 *  - It is finished when a doctor has reviewed and submitted again (the order goes to Processing and repeats resume),
 *    or cancelled with a reason by the requesting doctor or Operations (the order goes back where it was).
 *  - The first follow-up on an order is free, later ones paid. This only records it.
 *  - Messages (OFF by default) never carry the note, a medicine name or health detail.
 */
class FollowUpService
{
    public function __construct(
        private readonly CoachAccess $access,
        private readonly NotificationDispatcher $dispatcher,
        private readonly PatientMessenger $messenger,
    ) {
    }

    public function days(): array
    {
        return config('doctor.followups.days');
    }

    /** True while an open follow-up request stops this order's next repeat. */
    public function blocksRepeat(Order $order): bool
    {
        return FollowUpRequest::open()->where('order_id', $order->id)->exists();
    }

    public function request(User $doctor, Order $order, int $days, ?string $note = null, ?int $consultationId = null): FollowUpRequest
    {
        abort_unless($doctor->role === UserRole::Doctor, 403);

        $patient = User::where('role', UserRole::Patient->value)->find($order->patient_id);
        abort_unless($patient && $this->access->canView($doctor, $patient), 403);

        if (! in_array($days, $this->days(), true)) {
            throw ValidationException::withMessages(['days' => 'Choose one of: '.implode(', ', $this->days()).' days.']);
        }
        if ($this->blocksRepeat($order)) {
            throw ValidationException::withMessages(['order' => 'This order already has a follow-up request open.']);
        }
        if (in_array($order->status, ['Cancelled', 'Paused'], true)) {
            throw ValidationException::withMessages(['order' => 'This order is '.strtolower($order->status).', so no follow-up can be requested.']);
        }

        $tz = config('doctor.time.timezone');
        $due = CarbonImmutable::now($tz)->addDays($days)->startOfDay()->utc();   // midnight South African time, stored as UTC

        $followUp = DB::transaction(function () use ($doctor, $order, $days, $note, $consultationId, $due) {
            $isFree = ! FollowUpRequest::where('order_id', $order->id)->whereIn('status', [FollowUpRequest::BOOKED, FollowUpRequest::COMPLETED])->exists();

            $followUp = FollowUpRequest::create([
                'order_id' => $order->id,
                'patient_id' => $order->patient_id,
                'requested_by' => $doctor->id,
                'consultation_id' => $consultationId,
                'days' => $days,
                'due_at' => $due,
                'status' => FollowUpRequest::REQUESTED,
                'is_free' => $isFree,
                'note' => $note ? trim($note) : null,
            ]);

            if (in_array($order->status, config('doctor.followups.move_order_from'), true)) {
                $before = $order->status;
                $order->transitionTo('PendingFollowUpRequest', 'doctor', [
                    'triggered_by' => $doctor->id,
                    'notes' => "Follow-up requested in {$days} days",
                ]);
                $followUp->update(['order_status_before' => $before]);
            }

            return $followUp;
        });

        $this->tellPatient($followUp, 'doctor.followup_requested', 'A follow-up appointment is needed',
            'Your doctor has asked for a follow-up appointment. Sign in to Zapmed to book it.');
        $this->tellOperations($followUp, 'doctor.followup_requested_ops', 'Follow-up requested',
            'A doctor has requested a follow-up. The next repeat is on hold until it is done. Open the order board in Zapmed to see it.');

        return $followUp;
    }

    /** The patient (or the calendar) booked it: Requested becomes Booked, and the order moves to profile-book if it had been moved. */
    public function markBooked(FollowUpRequest $followUp, ?Appointment $appointment = null): FollowUpRequest
    {
        if ($followUp->status !== FollowUpRequest::REQUESTED) {
            throw ValidationException::withMessages(['status' => 'Only a requested follow-up can be marked as booked.']);
        }
        if ($appointment && $appointment->patient_id !== $followUp->patient_id) {
            throw ValidationException::withMessages(['appointment' => 'That appointment belongs to another patient.']);
        }

        DB::transaction(function () use ($followUp, $appointment) {
            $followUp->update(['status' => FollowUpRequest::BOOKED, 'booked_at' => now(), 'appointment_id' => $appointment?->id]);

            $order = $followUp->order;
            if ($order->status === 'PendingFollowUpRequest') {
                $order->transitionTo('PendingFollowUpProfileBook', 'patient', ['notes' => 'Patient booked the follow-up']);
            }
        });

        return $followUp->fresh();
    }

    /** A doctor who treats the patient has reviewed and submitted again: the follow-up is done and repeats resume. */
    public function complete(User $doctor, FollowUpRequest $followUp): FollowUpRequest
    {
        abort_unless($doctor->role === UserRole::Doctor && $this->access->canView($doctor, $followUp->patient), 403);

        if (! $followUp->isOpen()) {
            throw ValidationException::withMessages(['status' => 'This follow-up is already '.$followUp->status.'.']);
        }

        DB::transaction(function () use ($doctor, $followUp) {
            $followUp->update(['status' => FollowUpRequest::COMPLETED, 'completed_at' => now(), 'closed_by' => $doctor->id]);

            $order = $followUp->order;
            if (in_array($order->status, ['PendingFollowUpRequest', 'PendingFollowUpProfileBook'], true)) {
                $order->transitionTo('Processing', 'doctor', ['triggered_by' => $doctor->id, 'notes' => 'Follow-up reviewed and submitted']);
            }
        });

        return $followUp->fresh();
    }

    /** The requesting doctor or Operations cancels it. A reason is required. The order goes back to where it was. */
    public function cancel(User $user, FollowUpRequest $followUp, string $reason): FollowUpRequest
    {
        $allowed = $user->role === UserRole::Admin || ($user->role === UserRole::Doctor && $followUp->requested_by === $user->id);
        abort_unless($allowed, 403);

        if (! $followUp->isOpen()) {
            throw ValidationException::withMessages(['status' => 'This follow-up is already '.$followUp->status.'.']);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Give a reason for cancelling.']);
        }

        DB::transaction(function () use ($user, $followUp, $reason) {
            $followUp->update([
                'status' => FollowUpRequest::CANCELLED, 'cancelled_at' => now(),
                'cancel_reason' => mb_substr($reason, 0, 255), 'closed_by' => $user->id,
            ]);

            $order = $followUp->order;
            if ($followUp->order_status_before && in_array($order->status, ['PendingFollowUpRequest', 'PendingFollowUpProfileBook'], true)) {
                $order->transitionTo($followUp->order_status_before, $user->role === UserRole::Admin ? 'admin' : 'doctor', [
                    'triggered_by' => $user->id, 'notes' => 'Follow-up cancelled',
                ]);
            }
        });

        return $followUp->fresh();
    }

    /**
     * Follow-ups still waiting to be booked that fall due within the prompt window (or are already overdue): tell the
     * patient once. Returns how many were prompted. Marks reminded_at even when messages are OFF, so turning them on later
     * does not send a pile of old prompts.
     */
    public function promptDue(): int
    {
        $until = now()->addDays((int) config('doctor.followups.prompt_days_before'));
        $count = 0;

        FollowUpRequest::where('status', FollowUpRequest::REQUESTED)
            ->whereNull('reminded_at')
            ->where('due_at', '<=', $until)
            ->with('patient')
            ->each(function (FollowUpRequest $f) use (&$count) {
                $f->update(['reminded_at' => now()]);
                $this->tellPatient($f, 'doctor.followup_prompt', 'Time to book your follow-up',
                    'Your follow-up appointment is coming up. Sign in to Zapmed to book it so your next repeat is not delayed.');
                $count++;
            });

        return $count;
    }

    private function tellPatient(FollowUpRequest $f, string $template, string $subject, string $body): void
    {
        if (! config('doctor.followups.notify_enabled') || ! $f->patient) {
            return;
        }

        $this->send($f, new OutboundMessage(
            templateKey: $template, category: 'transactional', user: $f->patient, phone: $f->patient->phone,
            subject: $subject, body: $body, meta: ['follow_up_request_id' => $f->id],
        ));
    }

    private function tellOperations(FollowUpRequest $f, string $template, string $subject, string $body): void
    {
        if (! config('doctor.followups.notify_enabled')) {
            return;
        }

        foreach (config('doctor.followups.ops_emails') as $email) {
            $this->send($f, new OutboundMessage(
                templateKey: $template, category: 'transactional', user: null, email: $email,
                subject: $subject, body: $body, meta: ['follow_up_request_id' => $f->id],
            ));
        }
    }

    private function send(FollowUpRequest $f, OutboundMessage $message): void
    {
        try {
            // The patient gets it on the channel they chose (with the other as a fallback); Operations get email.
            $message->user ? $this->messenger->send($message) : $this->dispatcher->send($message, ['email']);
        } catch (\Throwable $e) {
            Log::error('follow-up: message failed', ['follow_up' => $f->id, 'error' => $e->getMessage()]);
        }
    }
}
