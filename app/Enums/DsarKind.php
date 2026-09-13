<?php

namespace App\Enums;

/**
 * Data-subject access request kind — verbatim parity with compliance_dsar_kind_enum (POPIA s23-25).
 */
enum DsarKind: string
{
    case Access = 'access';           // POPIA s23 — what do you have on me?
    case Export = 'export';           // portable copy
    case Erasure = 'erasure';         // POPIA s24 — delete me
    case Rectification = 'rectification';
    case Portability = 'portability'; // structured machine-readable export
    case Restrict = 'restrict';       // limit processing

    public function label(): string
    {
        return match ($this) {
            self::Access => 'Access',
            self::Export => 'Export',
            self::Erasure => 'Erasure',
            self::Rectification => 'Rectification',
            self::Portability => 'Portability',
            self::Restrict => 'Restrict processing',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $k) => $k->value, self::cases());
    }
}
