<?php

namespace Zapmed\SparCore\Enums;

/**
 * The patient's response to a reminder / prompt (spar-close-the-loop design
 * §1.2, FR-B3/B4). Recorded append-only in spar_patient_signals; the latest
 * per subject drives the reminder schedule and the lost-customer insight.
 *
 * Fulfilment intents (yes_*) close the loop and start an order;
 * cadence intents (remind_*) reschedule; suppression intents (stop/ignore_*)
 * mute reminders for a window or permanently.
 */
enum SparPatientSignalType: string
{
    /** "Pack it for collection" — close the loop, collect fulfilment. */
    case YesCollect = 'yes_collect';

    /** "Deliver it to me" — close the loop, delivery fulfilment. */
    case YesDeliver = 'yes_deliver';

    /** "Remind me in N days" — payload {days:int}. */
    case RemindInDays = 'remind_in_days';

    /** "Remind me next cycle" — snooze to the next dispense cycle. */
    case RemindNextCycle = 'remind_next_cycle';

    /** "Stop reminding me" — opt out of reminders (permanent until re-opt-in). */
    case StopReminders = 'stop_reminders';

    /** "Not this month" — mute for roughly one cycle. */
    case IgnoreMonth = 'ignore_month';

    /** "Never for this item" — permanent suppression for the subject. */
    case IgnoreFuture = 'ignore_future';

    public function isFulfilment(): bool
    {
        return in_array($this, [self::YesCollect, self::YesDeliver], true);
    }

    public function isCadenceChange(): bool
    {
        return in_array($this, [self::RemindInDays, self::RemindNextCycle], true);
    }

    /**
     * Whether this signal suppresses future reminders (temporarily or for good).
     */
    public function isSuppression(): bool
    {
        return in_array($this, [self::StopReminders, self::IgnoreMonth, self::IgnoreFuture], true);
    }

    /**
     * A hard opt-out from reminders (as opposed to a timed mute).
     */
    public function isOptOut(): bool
    {
        return in_array($this, [self::StopReminders, self::IgnoreFuture], true);
    }

    /**
     * The fulfilment mode this signal implies, if any (for order creation).
     */
    public function fulfilmentMode(): ?string
    {
        return match ($this) {
            self::YesCollect => 'collection',
            self::YesDeliver => 'delivery',
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::YesCollect => 'Collect',
            self::YesDeliver => 'Deliver to me',
            self::RemindInDays => 'Remind me later',
            self::RemindNextCycle => 'Remind me next month',
            self::StopReminders => 'Stop reminders',
            self::IgnoreMonth => 'Not this month',
            self::IgnoreFuture => 'Never for this',
        };
    }
}
