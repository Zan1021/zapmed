<?php

namespace App\Services\Orders;

/**
 * Order status state machine — the equivalent of Mark's orders_is_transition_allowed().
 *
 * Rules are DATA (config/orders.php), not hard-coded branches. Used at two points:
 *  - runtime: guard legitimate status changes in the owned system.
 *  - import: the Contro reconciler validates each history step; disallowed steps are QUARANTINED
 *    (flagged), never silently rejected — Contro data may predate the current rules.
 *
 * Contro status spelling is preserved verbatim (incl. 'PendingConsulation').
 */
class OrderStatusMachine
{
    /** @return array<int,string> */
    public static function statuses(): array
    {
        return config('orders.statuses', []);
    }

    /** @return array<int,string> */
    public static function triggers(): array
    {
        return config('orders.triggers', []);
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::statuses(), true);
    }

    public static function isValidTrigger(string $trigger): bool
    {
        return in_array($trigger, self::triggers(), true);
    }

    /**
     * Is (from -> to) permitted? If $trigger is given it must also match a rule for that pair.
     */
    public static function isTransitionAllowed(string $from, string $to, ?string $trigger = null): bool
    {
        foreach (config('orders.transitions', []) as [$f, $t, $trg]) {
            if ($f === $from && $t === $to && ($trigger === null || $trigger === $trg)) {
                return true;
            }
        }

        return false;
    }

    /**
     * All statuses reachable from $from (optionally constrained to a trigger).
     * @return array<int,string>
     */
    public static function allowedNextStatuses(string $from, ?string $trigger = null): array
    {
        $next = [];
        foreach (config('orders.transitions', []) as [$f, $t, $trg]) {
            if ($f === $from && ($trigger === null || $trigger === $trg)) {
                $next[] = $t;
            }
        }

        return array_values(array_unique($next));
    }

    /** Map an inbound RxHub event code to the order status it drives, or null if unknown. */
    public static function statusForRxhubEvent(string $eventCode): ?string
    {
        return config("orders.rxhub_event_map.{$eventCode}");
    }
}
