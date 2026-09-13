<?php

namespace App\Enums;

/**
 * Coaching touchpoint channel — verbatim parity with coaching_touchpoint_channel_enum.
 */
enum TouchpointChannel: string
{
    case Phone = 'phone';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case InApp = 'in_app';
    case InPerson = 'in_person';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::Sms => 'SMS',
            self::WhatsApp => 'WhatsApp',
            self::Email => 'Email',
            self::InApp => 'In-app',
            self::InPerson => 'In person',
            self::Other => 'Other',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
