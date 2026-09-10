<?php

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'pharmacy_users'),
    ],

    'guards' => [
        // Staff auth against pharmacy_users (spec design §5.3). No telehealth User.
        'web' => [
            'driver' => 'session',
            'provider' => 'pharmacy_users',
        ],
    ],

    'providers' => [
        'pharmacy_users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\PharmacyUser::class),
        ],
    ],

    'passwords' => [
        'pharmacy_users' => [
            'provider' => 'pharmacy_users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
