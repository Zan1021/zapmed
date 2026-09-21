<?php

namespace Zapmed\SparCore\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Zapmed\SparCore\Enums\SparActionableStatus;

/**
 * Shared "actionable item" behaviour for the SPAR close-the-loop engine
 * (spar-close-the-loop design §1.1, option B).
 *
 * Any subject that can require follow-up — SparPrescriptionJourney,
 * SparDispenseRecord, SparOrder — uses this trait plus the lifecycle columns
 * added by the 2026_09_21 migration:
 *   action_status  (string, default 'open')  cast to {@see SparActionableStatus}
 *   snoozed_until  (datetime, nullable)
 *   last_action_at (datetime, nullable)
 *   resolved_at    (datetime, nullable)
 *
 * The trait is the single place transitions are validated, so no caller can
 * put a subject into an illegal state. State mutations here are intentionally
 * NOT consent-gated — that concern lives in SparActionService, which decides
 * whether an outbound message may be sent before it records the action.
 */
trait ResolvesActionable
{
    /**
     * Merge the actionable casts/fillable in without clobbering the model's own.
     * Call from the model's boot if needed; casts are declared via
     * initializeResolvesActionable() below (Eloquent trait initializer).
     */
    public function initializeResolvesActionable(): void
    {
        $this->mergeCasts([
            'action_status' => SparActionableStatus::class,
            'snoozed_until' => 'datetime',
            'last_action_at' => 'datetime',
            'resolved_at' => 'datetime',
        ]);

        $this->mergeFillable([
            'action_status',
            'snoozed_until',
            'last_action_at',
            'resolved_at',
        ]);
    }

    public function actionStatus(): SparActionableStatus
    {
        $status = $this->action_status;

        if ($status instanceof SparActionableStatus) {
            return $status;
        }

        return SparActionableStatus::tryFrom((string) $status) ?? SparActionableStatus::Open;
    }

    public function isActionable(): bool
    {
        return $this->actionStatus()->isOpen();
    }

    public function isResolvedActionable(): bool
    {
        return $this->actionStatus()->isResolved();
    }

    /**
     * Whether a snooze is currently in effect (status snoozed AND not yet due).
     */
    public function isSnoozed(): bool
    {
        return $this->actionStatus() === SparActionableStatus::Snoozed
            && $this->snoozed_until !== null
            && $this->snoozed_until->isFuture();
    }

    /**
     * Move to a new status, enforcing the enum's transition rules. Returns
     * false (without writing) if the transition is illegal.
     */
    public function transitionActionable(SparActionableStatus $to): bool
    {
        if (! $this->actionStatus()->canTransitionTo($to)) {
            return false;
        }

        $this->action_status = $to;
        $this->save();

        return true;
    }

    /**
     * Record that staff acted on the item (e.g. sent a nudge). Stamps
     * last_action_at and, unless awaiting a patient reply, moves to Actioned.
     */
    public function markActioned(bool $awaitingPatient = false): bool
    {
        $target = $awaitingPatient
            ? SparActionableStatus::AwaitingPatient
            : SparActionableStatus::Actioned;

        if (! $this->actionStatus()->canTransitionTo($target)) {
            return false;
        }

        $this->forceFill([
            'action_status' => $target,
            'last_action_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Defer the item for $days days. Clamped to a sane minimum of 1 day.
     */
    public function snoozeActionable(int $days): bool
    {
        $days = max(1, $days);

        if (! $this->actionStatus()->canTransitionTo(SparActionableStatus::Snoozed)) {
            return false;
        }

        $this->forceFill([
            'action_status' => SparActionableStatus::Snoozed,
            'snoozed_until' => now()->addDays($days),
            'last_action_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Wake a snoozed item back to Open (used by dueNow reconciliation or a
     * manual "un-snooze"). No-op if not currently snoozed.
     */
    public function wakeActionable(): bool
    {
        if ($this->actionStatus() !== SparActionableStatus::Snoozed) {
            return false;
        }

        $this->forceFill([
            'action_status' => SparActionableStatus::Open,
            'snoozed_until' => null,
        ])->save();

        return true;
    }

    /**
     * Close the loop for this cycle. Idempotent — resolving an already-resolved
     * item just refreshes resolved_at.
     */
    public function resolveActionable(): bool
    {
        $this->forceFill([
            'action_status' => SparActionableStatus::Resolved,
            'resolved_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Re-open a resolved item (used ONLY by the import reconciler when new
     * dispense facts contradict a prior resolution — never by a UI action).
     */
    public function reopenActionable(): bool
    {
        $this->forceFill([
            'action_status' => SparActionableStatus::Open,
            'resolved_at' => null,
            'snoozed_until' => null,
        ])->save();

        return true;
    }

    /* --------------------------------------------------------------------- */
    /* Scopes                                                                */
    /* --------------------------------------------------------------------- */

    /**
     * Items with an open loop: open / actioned / awaiting_patient. Snoozed and
     * resolved are excluded (snoozed reappears via dueNow once it wakes).
     */
    public function scopeOpenItems(Builder $query): Builder
    {
        return $query->whereIn('action_status', SparActionableStatus::openValues());
    }

    /**
     * Items that should surface in a "do this now" list: every open item, plus
     * snoozed items whose snoozed_until has passed. Resolved never appears.
     */
    public function scopeDueNow(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('action_status', SparActionableStatus::openValues())
                ->orWhere(function (Builder $snoozed) {
                    $snoozed->where('action_status', SparActionableStatus::Snoozed->value)
                        ->where(function (Builder $expired) {
                            $expired->whereNull('snoozed_until')
                                ->orWhere('snoozed_until', '<=', now());
                        });
                });
        });
    }

    /**
     * Currently-suppressed snoozed items (status snoozed AND still in the future).
     */
    public function scopeSnoozed(Builder $query): Builder
    {
        return $query->where('action_status', SparActionableStatus::Snoozed->value)
            ->where('snoozed_until', '>', now());
    }

    public function scopeResolvedItems(Builder $query): Builder
    {
        return $query->where('action_status', SparActionableStatus::Resolved->value);
    }
}
