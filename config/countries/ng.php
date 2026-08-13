<?php

/*
|--------------------------------------------------------------------------
| Nigeria — TEMPLATE (not yet launched)
|--------------------------------------------------------------------------
| Copy this pattern when launching in a new country.
| Set APP_COUNTRY=ng in that deployment's .env
*/

return [
    'name' => 'Nigeria',
    'code' => 'NG',
    'currency' => 'NGN',
    'currency_symbol' => '₦',
    'currency_position' => 'before',
    'currency_decimals' => 0, // Nigerians don't use decimals for Naira
    'phone_prefix' => '+234',
    'phone_format' => '0XXX XXX XXXX',
    'timezone' => 'Africa/Lagos',
    'locale' => 'en_NG',
    'language' => 'English',

    // Regulatory
    'medical_regulator' => 'MDCN',
    'pharmacy_regulator' => 'PCN',
    'data_protection_law' => 'NDPR',
    'telehealth_legal' => true,

    // Payment
    'payment_gateway' => 'paystack',
    'tax_rate' => 7.5,
    'tax_name' => 'VAT',
    'tax_included' => true,

    // SMS
    'sms_provider' => 'termii',

    // Delivery
    'delivery_available' => true,
    'delivery_fee' => 200000, // ₦2,000 in kobo
    'delivery_days' => '2-5',
    'delivery_partner' => 'kwik',

    // Geography
    'provinces' => [
        'lagos' => 'Lagos',
        'abuja' => 'FCT Abuja',
        'rivers' => 'Rivers',
        'oyo' => 'Oyo',
        'kano' => 'Kano',
        'delta' => 'Delta',
        'enugu' => 'Enugu',
        'kaduna' => 'Kaduna',
    ],

    // Legal
    'company_name' => 'Zapmed Nigeria Ltd',
    'company_registration' => '',
    'support_email' => 'support@zapmed.com.ng',
    'doctors_email' => 'doctors@zapmed.com.ng',
];
