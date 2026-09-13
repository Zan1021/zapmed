<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Analytics 4
    |--------------------------------------------------------------------------
    |
    | Add your GA4 Measurement ID (starts with "G-") to enable tracking on
    | all public pages. Leave empty to disable tracking entirely.
    |
    | REMINDER: Captain Zan needs to:
    | 1. Create GA4 property at https://analytics.google.com
    | 2. Get Measurement ID (G-XXXXXXXXXX)
    | 3. Set GOOGLE_ANALYTICS_ID in .env
    | 4. For admin dashboard: create service account, download JSON key,
    |    set GOOGLE_APPLICATION_CREDENTIALS and GOOGLE_ANALYTICS_PROPERTY_ID
    |
    */

    'measurement_id' => env('GOOGLE_ANALYTICS_ID', ''),

    // GA4 Property ID (numeric) — for Data API queries in admin dashboard
    'property_id' => env('GOOGLE_ANALYTICS_PROPERTY_ID', ''),

    // PageSpeed Insights API key (free tier: 25k requests/day)
    'pagespeed_api_key' => env('PAGESPEED_API_KEY', ''),

    // URLs to monitor with PageSpeed
    'monitored_urls' => [
        env('APP_URL', 'https://zapmed.co.za'),
        env('APP_URL', 'https://zapmed.co.za') . '/weight-loss',
        env('APP_URL', 'https://zapmed.co.za') . '/erectile-dysfunction-treatment',
    ],

    /*
    |--------------------------------------------------------------------------
    | CRM Analytics + Finance (Task 7 — parity with Mark's AnalyticsConfig /
    | FinanceReportsConfig). Values as DATA so they're tunable without code.
    |--------------------------------------------------------------------------
    */

    // Default KPI window (days) when the caller doesn't supply since/until.
    'default_lookback_days' => 30,

    // Marketing channels ops can log spend against (used to seed the ad-spend form).
    'channels' => ['google_ads', 'meta', 'tiktok', 'organic', 'referral', 'other'],

    // Finance:
    'finance' => [
        // ± tolerance (cents) within which a recon entry auto-grades to "matched".
        'recon_auto_match_tolerance_cents' => 100,

        // Hours a payment can be non-captured before it appears on the outstanding-payments report.
        'outstanding_payments_aged_hours' => 24,
    ],

];
