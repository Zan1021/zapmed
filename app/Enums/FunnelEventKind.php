<?php

namespace App\Enums;

/**
 * Analytics funnel event kind — verbatim parity with analytics_funnel_event_kind_enum.
 *
 * The acquisition→retention journey, captured as an append-only event log. Anonymous events
 * (page_view, sign_up_started) are allowed before a principal exists (keyed by visitor_id).
 */
enum FunnelEventKind: string
{
    case PageView = 'page_view';
    case SignUpStarted = 'sign_up_started';
    case SignUpComplete = 'sign_up_complete';
    case IntakeStarted = 'intake_started';
    case IntakeComplete = 'intake_complete';
    case ConsultBooked = 'consult_booked';
    case ConsultComplete = 'consult_complete';
    case FirstPayment = 'first_payment';
    case Subscribed = 'subscribed';
    case Cancelled = 'cancelled';
    case Churned = 'churned';
    case Reactivated = 'reactivated';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::PageView => 'Page view',
            self::SignUpStarted => 'Sign-up started',
            self::SignUpComplete => 'Sign-up complete',
            self::IntakeStarted => 'Intake started',
            self::IntakeComplete => 'Intake complete',
            self::ConsultBooked => 'Consult booked',
            self::ConsultComplete => 'Consult complete',
            self::FirstPayment => 'First payment',
            self::Subscribed => 'Subscribed',
            self::Cancelled => 'Cancelled',
            self::Churned => 'Churned',
            self::Reactivated => 'Reactivated',
            self::Custom => 'Custom',
        };
    }

    /**
     * The ordered acquisition funnel used for the conversion board (excludes lifecycle-only
     * events: cancelled/churned/reactivated/custom). Order matters for step-to-step rates.
     *
     * @return array<int,self>
     */
    public static function funnelOrder(): array
    {
        return [
            self::PageView,
            self::SignUpStarted,
            self::SignUpComplete,
            self::IntakeStarted,
            self::IntakeComplete,
            self::ConsultBooked,
            self::ConsultComplete,
            self::FirstPayment,
            self::Subscribed,
        ];
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $k) => $k->value, self::cases());
    }
}
