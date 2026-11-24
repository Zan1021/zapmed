<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\IntegrationMessage;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use App\Services\PharmacyService;
use Illuminate\Validation\ValidationException;

/**
 * The integration log with a safe resend (Craig's guide 6.2, 6.4).
 *
 * Every send of a prescription to the pharmacy is one row (see PharmacyService). The rule that keeps patients safe from a
 * double order: a prescription that has a SUCCESS row, or is already marked dispatched, is never sent again, by the automatic
 * retries or by a person. A failure we are SURE about (they refused it, or we could not reach them) is retried by itself up to
 * ten times with growing waits. A failure we are NOT sure about (no answer) is never retried by itself: a person must check with
 * the pharmacy and say so before it can be sent again.
 */
class IntegrationLog
{
    public const SYSTEM = 'pharmacy';

    private static ?int $resentBy = null;

    // ---- written by the sender -----------------------------------------------------------------------------------

    /** Start a row for one attempt. The row says "unknown" until the sender finishes it, so a crash never looks like a success. */
    public function begin(Prescription $rx): IntegrationMessage
    {
        $first = IntegrationMessage::where('system', self::SYSTEM)->where('prescription_id', $rx->id)->orderBy('id')->first();
        $attempt = IntegrationMessage::where('system', self::SYSTEM)->where('prescription_id', $rx->id)->count() + 1;

        $row = IntegrationMessage::create([
            'system' => self::SYSTEM,
            'type' => 'prescription_send',
            'prescription_id' => $rx->id,
            'order_id' => $this->orderIdFor($rx),
            'patient_id' => $rx->patient_id,
            'status' => IntegrationMessage::UNKNOWN,
            'attempt' => $attempt,
            'parent_id' => $first?->id,
            'request_summary' => 'Prescription '.($rx->reference ?? $rx->id).' to the pharmacy (attempt '.$attempt.')',
            'resent_by' => self::$resentBy,
        ]);

        return $row;
    }

    public function succeed(IntegrationMessage $row, ?int $code, ?string $excerpt = null): void
    {
        $row->update(['status' => IntegrationMessage::SUCCESS, 'response_code' => $code, 'response_excerpt' => $this->cut($excerpt), 'error' => null, 'next_retry_at' => null]);
    }

    /** @param bool $definite true when we know it did not arrive (an error answer, or the connection never opened) */
    public function fail(IntegrationMessage $row, ?int $code, string $error, bool $definite, ?string $excerpt = null): void
    {
        $retry = null;
        if ($definite && $row->attempt < (int) config('operations.integrations.max_attempts')) {
            $waits = config('operations.integrations.retry_minutes');
            $retry = now()->addMinutes((int) ($waits[$row->attempt - 1] ?? end($waits)));
        }

        $row->update([
            'status' => $definite ? IntegrationMessage::FAILED : IntegrationMessage::UNKNOWN,
            'response_code' => $code, 'error' => mb_substr($error, 0, 255), 'response_excerpt' => $this->cut($excerpt), 'next_retry_at' => $retry,
        ]);
    }

    // ---- reading -------------------------------------------------------------------------------------------------

    /** Was this prescription already delivered to the pharmacy, as far as we know? */
    public function alreadySent(Prescription $rx): bool
    {
        return $rx->pharmacy_status === 'dispatched'
            || IntegrationMessage::where('system', self::SYSTEM)->where('prescription_id', $rx->id)->where('status', IntegrationMessage::SUCCESS)->exists();
    }

    /** The newest attempt of each prescription's chain. */
    public function isLatest(IntegrationMessage $row): bool
    {
        return ! IntegrationMessage::where('system', $row->system)->where('prescription_id', $row->prescription_id)->where('id', '>', $row->id)->exists();
    }

    // ---- sending again -------------------------------------------------------------------------------------------

    /** Automatic retries of definite failures that are due. Returns how many were sent again. */
    public function retryDue(): int
    {
        $sent = 0;

        IntegrationMessage::with('prescription')->where('system', self::SYSTEM)->where('status', IntegrationMessage::FAILED)
            ->whereNotNull('next_retry_at')->where('next_retry_at', '<=', now())->orderBy('id')->limit(100)->get()
            ->each(function (IntegrationMessage $row) use (&$sent) {
                $rx = $row->prescription;
                // clear the schedule first so two runs can never send the same retry
                $row->update(['next_retry_at' => null]);

                if (! $rx || ! $this->isLatest($row) || $this->alreadySent($rx)) {
                    return;
                }

                $this->prepare($rx);
                app(PharmacyService::class)->dispatch($rx);
                $sent++;
            });

        return $sent;
    }

    /**
     * A person sends it again. Refused if it already arrived. An "unknown" row needs the person to confirm they checked with the
     * pharmacy and it did NOT arrive.
     */
    public function resend(User $ops, IntegrationMessage $row, bool $confirmedNotReceived = false): array
    {
        abort_unless($ops->role === UserRole::Admin, 403);

        $rx = $row->prescription;
        if (! $rx) {
            throw ValidationException::withMessages(['resend' => 'This message has no prescription to send.']);
        }
        if ($this->alreadySent($rx)) {
            throw ValidationException::withMessages(['resend' => 'The pharmacy already has this prescription. It will not be sent again.']);
        }
        if (! $this->isLatest($row)) {
            throw ValidationException::withMessages(['resend' => 'There is a newer attempt for this prescription. Use that one.']);
        }
        if ($row->status === IntegrationMessage::UNKNOWN && ! $confirmedNotReceived) {
            throw ValidationException::withMessages(['resend' => 'We do not know if the pharmacy got this one. Check with them first, then confirm they did not.']);
        }

        self::$resentBy = $ops->id;
        try {
            $this->prepare($rx);
            $result = app(PharmacyService::class)->dispatch($rx);
        } finally {
            self::$resentBy = null;
        }

        if ($row->order_id && ($order = Order::find($row->order_id))) {
            app(OrderControls::class)->audit($order, $ops, 'integration_resend', 'Prescription sent to the pharmacy again: '.($result['success'] ? 'accepted' : 'not accepted').'.', ['integration_message_id' => $row->id]);
        }

        return $result;
    }

    /** The sender only accepts a prescription that is waiting ("pending"). A failed one is put back to waiting first. Never one the pharmacy has. */
    private function prepare(Prescription $rx): void
    {
        if ($rx->pharmacy_status === 'failed') {
            $rx->update(['pharmacy_status' => 'pending']);
        }
    }

    private function orderIdFor(Prescription $rx): ?int
    {
        return $rx->pharmacy_script_ref ? Order::where('pharmacy_script_ref', $rx->pharmacy_script_ref)->where('patient_id', $rx->patient_id)->value('id') : null;
    }

    private function cut(?string $text): ?string
    {
        return $text === null ? null : mb_substr($text, 0, 1000);
    }
}
