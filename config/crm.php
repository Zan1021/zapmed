<?php

use App\Enums\FunnelStage;

/**
 * CRM funnel configuration — the 14-stage lifecycle (parity with Mark's crm module).
 *
 * The funnel is more permissive than the order state machine: ops can move a lead to most stages
 * (leads slip backwards, skip stages, get re-engaged). Rather than an exhaustive allow-list we forbid
 * a small set of illegal moves (no-op, and progressing OUT of a terminal stage without an explicit
 * re-engagement). Everything else is allowed and logged. This mirrors the reference, which records
 * every transition rather than gate-keeping a rigid DAG.
 *
 * Risk rubric thresholds live here too so the rules engine + AT layer share one source of truth.
 */
return [

    // Stage the lifecycle starts at.
    'initial_stage' => FunnelStage::Lead->value,

    // Terminal stages: a lead must be explicitly re-engaged (system/ops) to leave these.
    'terminal_stages' => [
        FunnelStage::Churned->value,
        FunnelStage::DroppedOff->value,
    ],

    /**
     * Rules-based risk scoring weights (parity with the AI rubric's factor model). Each rule that
     * fires adds its points; the total is clamped to 0–100 and banded via RiskBand::fromScore().
     */
    'risk' => [
        'weights' => [
            'payment_failed_each' => 12,        // per failed payment (capped below)
            'payment_failed_cap' => 48,
            'three_repeat_failures' => 25,      // order in ThreeRepeatFailures
            'days_inactive_30' => 15,           // 30+ days since last activity
            'days_inactive_60' => 25,           // 60+ days (replaces the 30 bump)
            'stalled_stage_14' => 10,           // 14+ days in the same non-terminal stage
            'no_show_each' => 8,                // per no-show consult (capped)
            'no_show_cap' => 24,
            'flag_at_risk' => 20,
            'flag_complaint_open' => 15,
            'flag_fraud_suspected' => 30,
            'churned' => 40,                    // already churned/dropped
        ],
    ],
];
