<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PayFast Merchant Credentials
    |--------------------------------------------------------------------------
    |
    | Sandbox credentials are provided by PayFast for testing.
    | Production credentials come from your PayFast merchant dashboard.
    |
    */
    'merchant_id' => env('PAYFAST_MERCHANT_ID', '10000100'),
    'merchant_key' => env('PAYFAST_MERCHANT_KEY', '46f0cd694581a'),
    'passphrase' => env('PAYFAST_PASSPHRASE', 'jt7NOE43FZPn'),

    /*
    |--------------------------------------------------------------------------
    | Test Mode
    |--------------------------------------------------------------------------
    |
    | When true, uses PayFast sandbox environment.
    | Set to false in production.
    |
    */
    'test_mode' => env('PAYFAST_TEST_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    */
    'return_url' => env('PAYFAST_RETURN_URL', '/payment/success'),
    'cancel_url' => env('PAYFAST_CANCEL_URL', '/payment/cancel'),
    'notify_url' => env('PAYFAST_NOTIFY_URL', '/payment/notify'),

    /*
    |--------------------------------------------------------------------------
    | PayFast URLs
    |--------------------------------------------------------------------------
    */
    'url' => env('PAYFAST_TEST_MODE', true)
        ? 'https://sandbox.payfast.co.za/eng/process'
        : 'https://www.payfast.co.za/eng/process',

    'validate_url' => env('PAYFAST_TEST_MODE', true)
        ? 'https://sandbox.payfast.co.za/eng/query/validate'
        : 'https://www.payfast.co.za/eng/query/validate',
];
