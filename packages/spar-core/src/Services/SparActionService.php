<?php

namespace Zapmed\SparCore\Services;

use Illuminate\Support\Facades\Log;
use Zapmed\SparCore\Contracts\SparActionable;
use Zapmed\SparCore\Enums\SparPatientSignalType;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPatientSignal;

/**
 * SPAR Close-the-Loop, Wave B2 — the single seam for STAFF actions on any
 * actionable item (a prescription journey, dispense record, or order that uses
 * the ResolvesActionable trait / implements the SparActionable contract).
 *
 * Three verbs (spar-close-the-loop design §2):
 *   nudge(item, payload)        — consent-gated outbound reminder; moves the
 *                                 item to awaiting_patient.
 *   snooze(item, days)          — defer the item; no message sent.
 *   personalMessage(item, body) — post a staff message into the patient's coach
 *                                 thread (SparCoachService already nudges +
 *                                 audits); moves the item to actioned.
 *
 * Guarantees:
 *  - Every outbound path is CONSENT-GATED. The MessagingDispatcher already
 *    refuses a non-consented patient; we additionally short-circuit so no state
 *    change or audit "sent" event is recorded when consent is absent.
 *  - Every action is AUDITED to the spar_audit channel (mirrors SparCoachService).
 *  - State transitions go through the trait, so illegal transitions are refused.
 *  - Package-pure: no host User / Prescription / role references (AC-3).
 */
class SparActionService
{
    public function __construct(
        private MessagingDispatcher $dispatcher,
        private SparCoachService $coach,
    ) {
    }

    /**
     * Send a consent-gated nudge to the patient about this item and move it to
     * awaiting_patient. Returns false (with NO state change / NO "sent" audit)
     * when the patient hasn't consented or delivery fails on every channel.
     *
     * @param  array{subject?:string,body:string,link?:string|null}  $payload
     */
    public function nudge(SparActionable $item, array $payload): bool
    {
        $patient = $item->patient;

        if (! $patient instanceof SparPatient || ! $patient->hasConsented()) {
            $this->audit('action_nudge_refused_no_consent', $item, $patient);

            return false;
        }

        $sent = $this->dispatcher->send($patient, [
            'subject' => $payload['subject'] ?? 'A reminder from your SPAR pharmacy',
            'body' => $payload['body'],
            'link' => $payload['link'] ?? null,
        ]);

        if (! $sent) {
            $this->audit('action_nudge_undelivered', $item, $patient);

            return false;
        }

        // Loop is now waiting on the patient to act.
        $item->markActioned(awaitingPatient: true);

        $this->audit('action_nudge_sent', $item, $patient);

        return true;
    }

    /**
     * Defer the item for $days days (clamped to >= 1 by the trait). No message
     * is sent — this is a staff-side deferral. Returns false if the item's
     * current state cannot be snoozed (e.g. already resolved).
     */
    public function snooze(SparActionable $item, int $days): bool
    {
        if (! $item->snoozeActionable($days)) {
            return false;
        }

        $this->audit('action_snoozed', $item, $item->patient, ['days' => max(1, $days)]);

        return true;
    }

    /**
     * Post a personal staff message into the patient's coach thread. Routes
     * through SparCoachService (which sends the consent-gated, no-PHI nudge and
     * writes its own coach audit), then marks this item actioned so it drops
     * out of the "needs a first touch" list.
     *
     * @param  array{id?:int|null,name?:string|null,role?:string|null}  $author
     * @return bool  true if the message was posted (patient resolvable + consented)
     */
    public function personalMessage(SparActionable $item, string $body, array $author, int $pharmacyId): bool
    {
        $patient = $item->patient;

        if (! $patient instanceof SparPatient || ! $patient->hasConsented()) {
            $this->audit('action_message_refused_no_consent', $item, $patient);

            return false;
        }

        $conversation = $this->coach->openConversation($patient, $pharmacyId);
        $this->coach->postStaffMessage($conversation, $body, $author);

        $item->markActioned();

        $this->audit('action_message_sent', $item, $patient, [
            'spar_conversation_id' => $conversation->id,
            'author_id' => $author['id'] ?? null,
        ]);

        return true;
    }

    /**
     * Explicitly close the loop on an item (e.g. staff confirms it's handled
     * outside the app). Always audited.
     */
    public function resolve(SparActionable $item): bool
    {
        $item->resolveActionable();

        $this->audit('action_resolved', $item, $item->patient);

        return true;
    }

    /**
     * Record a PATIENT's response to a reminder/prompt for an actionable item
     * (spar-close-the-loop FR-B3/B4). Writes an append-only SparPatientSignal,
     * adjusts the item's reminder schedule, and resolves the loop where the
     * response closes it (a fulfilment "yes"). Returns the created signal.
     *
     * This is the patient half of the engine — the counterpart to the staff
     * nudge(). It is NOT consent-gated: a patient responding to their own
     * reminder (including "stop") must always be honoured, and "stop" is itself
     * how a consented patient dials comms down.
     *
     * @param  array<string,mixed>  $payload  signal data, e.g. ['days' => 30]
     */
    public function recordPatientResponse(
        SparActionable $item,
        SparPatientSignalType $signal,
        array $payload = [],
        string $channel = 'inapp',
    ): SparPatientSignal {
        $patient = $item->patient;

        $record = SparPatientSignal::create([
            'spar_patient_id' => $patient?->getKey(),
            'subject_type' => SparPatientSignal::canonicalType($item),
            'subject_id' => $item->getKey(),
            'signal' => $signal,
            'payload' => $payload ?: null,
            'channel' => $channel,
        ]);

        $this->applyScheduleAdjustment($item, $signal, $payload);

        $this->audit('patient_signal_recorded', $item, $patient instanceof SparPatient ? $patient : null, [
            'signal' => $signal->value,
            'payload' => $payload ?: null,
            'channel' => $channel,
            'signal_id' => $record->id,
        ]);

        return $record;
    }

    /**
     * Translate a patient signal into a state/schedule change on the item.
     *  - fulfilment (yes_*) → resolve the loop (an order is placed elsewhere).
     *  - remind_in_days      → snooze N days (payload.days, default 30).
     *  - remind_next_cycle   → snooze one cycle (config reminder cadence).
     *  - ignore_month        → snooze roughly one month.
     *  - stop/ignore_future  → mark awaiting/actioned but leave suppression to
     *                          the reminder service (it reads the latest signal).
     */
    private function applyScheduleAdjustment(
        SparActionable $item,
        SparPatientSignalType $signal,
        array $payload,
    ): void {
        if ($signal->isFulfilment()) {
            // Patient committed — close this reminder loop; fulfilment/order flow
            // (Wave C) takes it from here.
            $item->resolveActionable();

            return;
        }

        if ($signal === SparPatientSignalType::RemindInDays) {
            $days = (int) ($payload['days'] ?? 30);
            $item->snoozeActionable(max(1, $days));

            return;
        }

        if ($signal === SparPatientSignalType::RemindNextCycle) {
            $cycleDays = (int) config('spar.reminders.monthly_cycle_days', 30);
            $item->snoozeActionable(max(1, $cycleDays));

            return;
        }

        if ($signal === SparPatientSignalType::IgnoreMonth) {
            $item->snoozeActionable(30);

            return;
        }

        // stop_reminders / ignore_future: the item stays where it is; the
        // reminder service (B4) reads the latest signal and suppresses future
        // sends. We DON'T resolve — the clinical need may persist even if the
        // patient muted comms.
    }

    private function audit(string $action, SparActionable $item, ?SparPatient $patient, array $context = []): void
    {
        $channel = config('logging.channels.spar_audit') ? 'spar_audit' : 'stack';

        Log::channel($channel)->info("{$action}: " . $this->subjectRef($item), array_merge([
            'action' => $action,
            'subject_type' => get_class($item),
            'subject_id' => $item->getKey(),
            'spar_patient_id' => $patient?->id,
            'action_status' => $item->actionStatus()->value,
            'timestamp' => now()->toISOString(),
        ], $context));
    }

    private function subjectRef(SparActionable $item): string
    {
        return class_basename($item) . ' #' . $item->getKey();
    }
}
