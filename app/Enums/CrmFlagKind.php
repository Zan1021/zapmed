<?php

namespace App\Enums;

/**
 * CRM flag kind — verbatim parity with Mark's crm_flag_kind_enum
 * (crm/migrations/0001_init.up.sql).
 */
enum CrmFlagKind: string
{
    case AtRisk = 'at_risk';
    case Vip = 'vip';
    case DoNotContact = 'do_not_contact';
    case FraudSuspected = 'fraud_suspected';
    case ComplaintOpen = 'complaint_open';
    case HighValue = 'high_value';
    case FollowUpRequired = 'follow_up_required';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::AtRisk => 'At risk',
            self::Vip => 'VIP',
            self::DoNotContact => 'Do not contact',
            self::FraudSuspected => 'Fraud suspected',
            self::ComplaintOpen => 'Complaint open',
            self::HighValue => 'High value',
            self::FollowUpRequired => 'Follow-up required',
            self::Other => 'Other',
        };
    }

    /** Tailwind colour token for the badge. */
    public function colour(): string
    {
        return match ($this) {
            self::AtRisk, self::FraudSuspected, self::ComplaintOpen => 'red',
            self::Vip, self::HighValue => 'emerald',
            self::DoNotContact => 'slate',
            self::FollowUpRequired => 'amber',
            self::Other => 'gray',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
