<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Charge-at-booking onboarding — feature flag
    |--------------------------------------------------------------------------
    | Which treatment slugs use the NEW onboarding journey (Assessment → Review
    | → Your details → Book → Pay, with the consultation fee charged at
    | confirmation). Any slug not listed keeps the existing BookAppointment flow.
    |
    | Every treatment that has a live question-bank manifest is enabled (all 18), each
    | verified end-to-end by tests/Feature/TreatmentJourneyWalkTest. A slug with no live
    | manifest would fall back to the legacy config questions, so add a slug here only
    | once its category is imported + published. Override per environment with
    | ONBOARDING_ENABLED_SLUGS=slug1,slug2 (or '*' for everything).
    */
    'enabled_slugs' => array_filter(
        array_map('trim', explode(',', (string) env('ONBOARDING_ENABLED_SLUGS', implode(',', [
            'acid-reflux-treatment', 'acne-treatment', 'anti-aging-treatment', 'bacterial-vaginosis-treatment',
            'birth-control', 'cold-sores-treatment', 'erectile-dysfunction-treatment', 'genital-herpes-101',
            'genital-warts-treatment', 'gp-consult', 'haemorrhoids-treatment', 'hair-loss-treatment',
            'menopause-management', 'premature-ejaculation-treatment', 'rosacea-treatment', 'sti-treatment',
            'uti-treatment', 'weight-loss',
        ]))))
    ),

    /*
    | How long a chosen slot is held (reserved) while the patient completes
    | payment, before the reservation is swept and the slot released.
    */
    'reservation_minutes' => (int) env('ONBOARDING_RESERVATION_MINUTES', 15),

    /*
    | Fallback consultation fee in CENTS if neither the doctor profile nor the
    | treatment config supplies one. R450.00 = 45000. (Fee is always bound
    | server-side; never trusted from the client.)
    */
    'default_fee_cents' => (int) env('ONBOARDING_DEFAULT_FEE_CENTS', 45000),
];
