<?php

namespace Zapmed\SparCore\Services;

use Illuminate\Support\Collection;
use Zapmed\SparCore\Enums\SparPatientSignalType;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPatientSignal;

/**
 * SPAR Close-the-Loop Wave C5 (FR-C5) — Lost-customer / win-back.
 *
 * A patient becomes a "lost customer" when their LATEST response signal is an
 * opt-out (stop_reminders / ignore_future). This service mines the append-only
 * spar_patient_signals feed (Wave B3) for those patients, scoped to the current
 * actor, and drives a personalised win-back offer.
 *
 * The win-back message itself still routes through the consent-gated
 * MessagingDispatcher — so if the patient has fully withdrawn consent they are
 * not messaged. A "stop_reminders" patient who is still consented (muted, not
 * opted-out) CAN receive a single personalised win-back; a fully opted-out
 * patient cannot (NFR-1 holds).
 *
 * Package-pure: scoping via SparPatient::visibleToCurrentActor; no host classes.
 */
class SparWinBackService
{
    public function __construct(private MessagingDispatcher $dispatcher)
    {
    }

    /**
     * Patient ids (scoped to the actor) whose most recent signal is an opt-out.
     *
     * @return array<int, int>
     */
    public function lostPatientIds(): array
    {
        // Latest signal per patient, within the actor's visible patient set.
        $visibleIds = SparPatient::query()->visibleToCurrentActor()->pluck('id');

        if ($visibleIds->isEmpty()) {
            return [];
        }

        $optOutValues = [
            SparPatientSignalType::StopReminders->value,
            SparPatientSignalType::IgnoreFuture->value,
        ];

        // Most-recent signal id per patient, then keep those that are opt-outs.
        $latestPerPatient = SparPatientSignal::query()
            ->whereIn('spar_patient_id', $visibleIds)
            ->selectRaw('spar_patient_id, MAX(id) as last_id')
            ->groupBy('spar_patient_id')
            ->pluck('last_id');

        if ($latestPerPatient->isEmpty()) {
            return [];
        }

        return SparPatientSignal::query()
            ->whereIn('id', $latestPerPatient)
            ->whereIn('signal', $optOutValues)
            ->pluck('spar_patient_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The lost-customer queue: scoped patient models flagged as lost.
     */
    public function lostCustomers(): Collection
    {
        $ids = $this->lostPatientIds();

        if (empty($ids)) {
            return collect();
        }

        return SparPatient::query()
            ->whereIn('id', $ids)
            ->with('pharmacy')
            ->get();
    }

    public function lostCount(): int
    {
        return count($this->lostPatientIds());
    }

    /**
     * Send a single personalised win-back offer to a lost patient. Consent-gated
     * by the dispatcher: a fully opted-out patient is not messaged (returns
     * false). Returns true if the offer was delivered.
     */
    public function sendWinBack(SparPatient $patient, string $incentive): bool
    {
        return $this->dispatcher->send($patient, [
            'subject' => 'We miss you at SPAR',
            'body' => trim($incentive) !== ''
                ? $incentive
                : "We'd love to keep helping with your medication. Here's a little something to welcome you back — pop into your SPAR pharmacy.",
            'link' => null,
        ]);
    }
}
