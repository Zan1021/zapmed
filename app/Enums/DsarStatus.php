<?php

namespace App\Enums;

/**
 * DSAR lifecycle status — verbatim parity with compliance_dsar_status_enum.
 *
 * received → acknowledged → in_progress → completed (or rejected / cancelled at appropriate points).
 */
enum DsarStatus: string
{
    case Received = 'received';
    case Acknowledged = 'acknowledged';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Acknowledged => 'Acknowledged',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Received, self::Acknowledged, self::InProgress], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Cancelled], true);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
