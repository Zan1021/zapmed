<?php

namespace App\Enums;

/**
 * Consent state — verbatim parity with compliance_consent_state_enum.
 */
enum ConsentState: string
{
    case Granted = 'granted';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
    case PendingReconsent = 'pending_reconsent'; // policy version bumped; need fresh consent

    public function label(): string
    {
        return match ($this) {
            self::Granted => 'Granted',
            self::Withdrawn => 'Withdrawn',
            self::Expired => 'Expired',
            self::PendingReconsent => 'Pending re-consent',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Granted;
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
