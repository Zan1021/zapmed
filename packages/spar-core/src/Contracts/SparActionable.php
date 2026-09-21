<?php

namespace Zapmed\SparCore\Contracts;

use Zapmed\SparCore\Enums\SparActionableStatus;

/**
 * Marks a model as an "actionable item" the close-the-loop engine can act on
 * (nudge / snooze / message / resolve). Satisfied by any model using the
 * {@see \Zapmed\SparCore\Concerns\ResolvesActionable} trait —
 * SparPrescriptionJourney, SparDispenseRecord, SparOrder.
 *
 * Declared so SparActionService depends on the capability, not a concrete
 * subject. Package-pure: names no host class.
 *
 * @property \Illuminate\Support\Carbon|null $snoozed_until
 * @property \Illuminate\Support\Carbon|null $last_action_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
interface SparActionable
{
    public function actionStatus(): SparActionableStatus;

    public function markActioned(bool $awaitingPatient = false): bool;

    public function snoozeActionable(int $days): bool;

    public function resolveActionable(): bool;

    /** The patient this actionable item belongs to (for consent + messaging). */
    public function patient();
}
