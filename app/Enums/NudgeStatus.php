<?php

namespace App\Enums;

/**
 * CRM nudge lifecycle status (parity with Mark's crm_nudge workflow).
 *
 * draft → approved → sent  (or dismissed at any pre-sent point). A nudge is an AI-/rule-drafted
 * outreach that ops MUST approve before it is sent — the AI never sends anything itself.
 */
enum NudgeStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Sent = 'sent';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Sent => 'Sent',
            self::Dismissed => 'Dismissed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Sent, self::Dismissed], true);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
