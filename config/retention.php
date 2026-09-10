<?php

/**
 * Data-retention policy per data category (POPIA March-2026 health-data
 * regulations require documented retention + secure disposal per category,
 * not a blanket "keep everything" policy).
 *
 * Each entry:
 *   retention_days : how long to keep after the anchor date
 *   basis          : documented lawful/clinical reason for the period
 *   disposal       : 'anonymise' (strip PII, keep clinical record for legal
 *                     retention) or 'delete' (remove entirely)
 *
 * NOTE: The exact clinical retention period (commonly cited as 6 years for
 * adult records, longer for minors, under HPCSA/National Health Act guidance)
 * MUST be confirmed by the compliance officer / attorney before enforcement —
 * these are sensible defaults, not legal advice.
 */

return [

    // Clinical records — retained for legal/clinical minimum, then anonymised
    // (never hard-deleted while retention applies).
    'consultations' => [
        'retention_days' => 365 * 6, // 6 years (confirm with compliance)
        'basis' => 'HPCSA / National Health Act clinical record-keeping',
        'disposal' => 'anonymise',
    ],
    'prescriptions' => [
        'retention_days' => 365 * 6,
        'basis' => 'Pharmacy dispensing + clinical record retention',
        'disposal' => 'anonymise',
    ],

    // Financial records — SARS/tax retention.
    'payments' => [
        'retention_days' => 365 * 5, // 5 years (tax)
        'basis' => 'SARS financial record retention',
        'disposal' => 'anonymise',
    ],

    // Marketing data — deleted, not just anonymised, once dormant.
    'newsletter_subscribers' => [
        'retention_days' => 365 * 3,
        'basis' => 'Direct-marketing consent lifecycle',
        'disposal' => 'delete',
        'anchor' => 'unsubscribed_at', // only dispose after unsubscribe + period
    ],

    // Assessment intake data (pre-consult questionnaires).
    'assessments' => [
        'retention_days' => 365 * 6,
        'basis' => 'Forms part of the clinical record',
        'disposal' => 'anonymise',
    ],

];
