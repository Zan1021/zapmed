<?php

namespace App\Enums;

/**
 * Retention schedule item status — verbatim parity with compliance_retention_status_enum.
 */
enum RetentionStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case OnHold = 'on_hold'; // legal-hold blocks the action

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::OnHold => 'On hold (legal)',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
