<?php

namespace App\Enums;

/**
 * CRM funnel stage — verbatim parity with Mark's crm_funnel_stage_enum
 * (crm/migrations/0001_init.up.sql). 14 stages, in lifecycle order.
 */
enum FunnelStage: string
{
    case Lead = 'lead';                       // captured (form fill, ad click)
    case SignedUp = 'signed_up';              // account created
    case IntakeStarted = 'intake_started';
    case IntakeComplete = 'intake_complete';
    case ConsultBooked = 'consult_booked';
    case ConsultComplete = 'consult_complete';
    case ScriptIssued = 'script_issued';
    case Paid = 'paid';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Coached = 'coached';                 // first coach contact made
    case Subscribed = 'subscribed';           // converted to recurring
    case Churned = 'churned';                 // cancelled / lapsed
    case DroppedOff = 'dropped_off';          // inactive at a stage past threshold

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead',
            self::SignedUp => 'Signed up',
            self::IntakeStarted => 'Intake started',
            self::IntakeComplete => 'Intake complete',
            self::ConsultBooked => 'Consult booked',
            self::ConsultComplete => 'Consult complete',
            self::ScriptIssued => 'Script issued',
            self::Paid => 'Paid',
            self::Dispatched => 'Dispatched',
            self::Delivered => 'Delivered',
            self::Coached => 'Coached',
            self::Subscribed => 'Subscribed',
            self::Churned => 'Churned',
            self::DroppedOff => 'Dropped off',
        };
    }

    /** Terminal stages — no forward progression expected. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Churned, self::DroppedOff], true);
    }

    /** The linear "happy path" order index (used to detect forward vs backward moves). */
    public function order(): int
    {
        return array_search($this, self::cases(), true);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
