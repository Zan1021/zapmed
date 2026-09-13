<?php

namespace App\Enums;

/**
 * Subscription renewal cycle status — verbatim parity with subscriptions_cycle_status_enum.
 *
 * scheduled → attempted → placed → (fulfilled | payment_failed); cancelled is terminal.
 * A cycle 'places' an order; success later 'fulfils' it, a failed charge marks 'payment_failed'.
 */
enum CycleStatus: string
{
    case Scheduled = 'scheduled';
    case Attempted = 'attempted';
    case Placed = 'placed';
    case Fulfilled = 'fulfilled';
    case PaymentFailed = 'payment_failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Attempted => 'Attempted',
            self::Placed => 'Placed',
            self::Fulfilled => 'Fulfilled',
            self::PaymentFailed => 'Payment failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Fulfilled, self::PaymentFailed, self::Cancelled], true);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
