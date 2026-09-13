<?php

namespace App\Enums;

/**
 * Coaching cross-sell offer status — verbatim parity with coaching_offer_status_enum.
 */
enum OfferStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function colour(): string
    {
        return match ($this) {
            self::Open => 'sky',
            self::Accepted => 'emerald',
            self::Declined, self::Withdrawn => 'gray',
            self::Expired => 'amber',
        };
    }

    public function isTerminal(): bool
    {
        return $this !== self::Open;
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
