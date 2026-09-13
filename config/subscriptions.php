<?php

/**
 * Subscription repeat-lifecycle configuration — parity with Mark's SubscriptionsConfig (Dave's spec).
 * Values as DATA so they're tunable without code changes.
 */
return [
    // Days from origin order Completed → follow-up due. Dave's spec: 180.
    'followup_after_days' => 180,

    // Consecutive cycle failures before auto-cancel. Dave's spec: 3 (the "3-strike" rule).
    'max_consecutive_failures' => 3,

    // Default renewal interval (days) when the plan/caller doesn't supply one.
    'default_interval_days' => 30,

    // Cycle runner look-ahead: only schedule/run cycles due within this window.
    'cycle_runner_lookback_hours' => 24,
];
