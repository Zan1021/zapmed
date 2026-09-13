<?php

namespace App\Enums;

/**
 * Alert lifecycle status — verbatim parity with Mark's alerts_status_enum.
 *
 * open → acknowledged → resolved (or snoozed → back to open, or auto_closed by the scanner when the
 * underlying condition clears). The "active" set (open/acknowledged/snoozed) is what the dedupe unique
 * index guards, so at most one active alert exists per dedupe_key at a time.
 */
enum AlertStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case Snoozed = 'snoozed';
    case AutoClosed = 'auto_closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Acknowledged => 'Acknowledged',
            self::Resolved => 'Resolved',
            self::Snoozed => 'Snoozed',
            self::AutoClosed => 'Auto-closed',
        };
    }

    /** The states that count as "still live" — guarded by the dedupe unique index. */
    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::Acknowledged, self::Snoozed], true);
    }

    /** @return array<int,string> the active-status string values. */
    public static function activeValues(): array
    {
        return [self::Open->value, self::Acknowledged->value, self::Snoozed->value];
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
