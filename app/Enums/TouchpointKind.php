<?php

namespace App\Enums;

/**
 * Coaching touchpoint kind — verbatim parity with coaching_touchpoint_kind_enum.
 */
enum TouchpointKind: string
{
    case FirstContact = 'first_contact';
    case CheckIn = 'check_in';
    case Reminder = 'reminder';
    case CrossSellOffer = 'cross_sell_offer';
    case Support = 'support';
    case Survey = 'survey';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FirstContact => 'First contact',
            self::CheckIn => 'Check-in',
            self::Reminder => 'Reminder',
            self::CrossSellOffer => 'Cross-sell offer',
            self::Support => 'Support',
            self::Survey => 'Survey',
            self::Other => 'Other',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
