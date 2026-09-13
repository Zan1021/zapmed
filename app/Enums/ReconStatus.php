<?php

namespace App\Enums;

/**
 * PayFast ↔ pharmacy reconciliation status — verbatim parity with finance_recon_status_enum.
 *
 * unmatched → matched (auto-graded by tolerance) | partial | disputed | written_off (admin).
 */
enum ReconStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Partial = 'partial';
    case Disputed = 'disputed';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::Unmatched => 'Unmatched',
            self::Matched => 'Matched',
            self::Partial => 'Partial',
            self::Disputed => 'Disputed',
            self::WrittenOff => 'Written off',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Matched, self::WrittenOff], true);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
