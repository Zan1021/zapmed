<?php

namespace App\Enums;

/**
 * Retention action — verbatim parity with compliance_retention_action_enum.
 */
enum RetentionAction: string
{
    case Erase = 'erase';                     // hard delete
    case Redact = 'redact';                   // replace PII with placeholders, keep aggregate
    case ArchiveAndErase = 'archive_and_erase'; // copy to cold storage then delete

    public function label(): string
    {
        return match ($this) {
            self::Erase => 'Erase',
            self::Redact => 'Redact',
            self::ArchiveAndErase => 'Archive & erase',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $a) => $a->value, self::cases());
    }
}
