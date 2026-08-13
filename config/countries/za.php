<?php

return [
    'name' => 'South Africa',
    'code' => 'ZA',
    'currency' => 'ZAR',
    'currency_symbol' => 'R',
    'currency_position' => 'before', // R450 vs 450R
    'currency_decimals' => 2,
    'phone_prefix' => '+27',
    'phone_format' => '0XX XXX XXXX',
    'timezone' => 'Africa/Johannesburg',
    'locale' => 'en_ZA',
    'language' => 'English',

    // Regulatory
    'medical_regulator' => 'HPCSA',
    'pharmacy_regulator' => 'SAPC',
    'data_protection_law' => 'POPIA',
    'telehealth_legal' => true,

    // Payment
    'payment_gateway' => 'payfast',
    'tax_rate' => 15, // VAT %
    'tax_name' => 'VAT',
    'tax_included' => true, // prices shown include VAT

    // SMS
    'sms_provider' => 'bulksms',

    // Delivery
    'delivery_available' => true,
    'delivery_fee' => 5000, // R50 in cents
    'delivery_days' => '1-3',
    'delivery_partner' => 'courier',

    // Geography
    'provinces' => [
        'gauteng' => 'Gauteng',
        'western-cape' => 'Western Cape',
        'kwazulu-natal' => 'KwaZulu-Natal',
        'eastern-cape' => 'Eastern Cape',
        'free-state' => 'Free State',
        'limpopo' => 'Limpopo',
        'mpumalanga' => 'Mpumalanga',
        'north-west' => 'North West',
        'northern-cape' => 'Northern Cape',
    ],

    // Legal pages
    'company_name' => 'Zapmed (Pty) Ltd',
    'company_registration' => '',
    'support_email' => 'support@zapmed.co.za',
    'doctors_email' => 'doctors@zapmed.co.za',
];
