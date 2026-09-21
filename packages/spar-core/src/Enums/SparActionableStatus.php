<?php

namespace Zapmed\SparCore\Enums;

/**
 * Lifecycle of a SPAR "actionable item" — the shared state machine that the
 * close-the-loop engine drives across every subject that can require staff or
 * patient follow-up (SparPrescriptionJourney, SparDispenseRecord, SparOrder).
 *
 * Design decision (spar-close-the-loop design §1.1, option B): rather than a
 * separate polymorphic table, each subject carries its own `action_status`
 * column typed by this enum and shares behaviour through the
 * {@see \Zapmed\SparCore\Concerns\ResolvesActionable} trait. This keeps each
 * subject authoritative and minimises migration churn for demo-grade.
 *
 * Transitions (enforced by the trait):
 *   open ──nudge──▶ actioned ──(await response)──▶ awaiting_patient
 *   open|actioned|awaiting_patient ──snooze──▶ snoozed (until snoozed_until)
 *   snoozed ──(wake / manual)──▶ open
 *   any ──resolve──▶ resolved   (terminal for this cycle)
 */
enum SparActionableStatus: string
{
    /** Needs attention and has had no action yet. */
    case Open = 'open';

    /** Staff took an action (e.g. sent a nudge) but the loop isn't closed. */
    case Actioned = 'actioned';

    /** We've asked the patient something and are waiting on their response. */
    case AwaitingPatient = 'awaiting_patient';

    /** Deliberately deferred until `snoozed_until`; excluded from due lists. */
    case Snoozed = 'snoozed';

    /** The loop is closed for this cycle. Terminal. */
    case Resolved = 'resolved';

    /**
     * Statuses that still represent an open loop (i.e. surface in work lists).
     * Snoozed is "open" conceptually but hidden until it wakes, so it is
     * handled separately by the dueNow() scope rather than listed here.
     *
     * @return array<int, self>
     */
    public static function openStates(): array
    {
        return [self::Open, self::Actioned, self::AwaitingPatient];
    }

    /**
     * @return array<int, string>
     */
    public static function openValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::openStates());
    }

    /**
     * Whether a transition from this status to $target is permitted.
     */
    public function canTransitionTo(self $target): bool
    {
        // Resolved is terminal for the cycle; only a fresh import cycle
        // (which re-opens via the reconciler, not a UI action) may revive it.
        if ($this === self::Resolved) {
            return false;
        }

        // No-op "transition" to the same state is always fine (idempotent).
        if ($this === $target) {
            return true;
        }

        return match ($target) {
            self::Actioned => in_array($this, [self::Open, self::AwaitingPatient, self::Snoozed], true),
            self::AwaitingPatient => in_array($this, [self::Open, self::Actioned, self::Snoozed], true),
            self::Snoozed => in_array($this, self::openStates(), true),
            self::Open => $this === self::Snoozed,
            self::Resolved => true, // any open state may resolve
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, self::openStates(), true);
    }

    public function isResolved(): bool
    {
        return $this === self::Resolved;
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Actioned => 'Actioned',
            self::AwaitingPatient => 'Awaiting patient',
            self::Snoozed => 'Snoozed',
            self::Resolved => 'Resolved',
        };
    }
}
