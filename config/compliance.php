<?php

/**
 * Compliance (POPIA) configuration — parity with Mark's ComplianceConfig. Values as DATA.
 */
return [
    // Current consent policy version. Bump to force re-consent across all principals.
    'policy_version' => env('COMPLIANCE_POLICY_VERSION', 'v1.0'),

    // Days after which a consent grant auto-expires (0 = never).
    'consent_expiry_days' => 0,

    // SLA: hours from DSAR receipt before ops gets a "stale ack" alert (POPIA guidance: ack within 7 days).
    'dsar_acknowledge_within_hours' => 168,

    // SLA: days from DSAR receipt before it must be completed (POPIA: 30).
    'dsar_complete_within_days' => 30,

    // Retention runner: only pick up rows due within this look-back, capped per pass.
    'retention_runner_lookback_hours' => 24,
    'retention_runner_batch_size' => 100,
];
