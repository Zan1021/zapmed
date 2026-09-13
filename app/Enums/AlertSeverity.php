<?php

namespace App\Enums;

/**
 * Alert severity — verbatim parity with Mark's alerts_severity_enum.
 */
enum AlertSeverity: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Informational = 'informational';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function colour(): string
    {
        return match ($this) {
            self::Critical => 'red',
            self::Warning => 'amber',
            self::Informational => 'sky',
        };
    }

    /** Sort weight — critical first. */
    public function weight(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::Warning => 1,
            self::Informational => 2,
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
