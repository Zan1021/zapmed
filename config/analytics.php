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

];
