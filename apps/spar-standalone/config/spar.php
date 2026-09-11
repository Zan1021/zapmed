<?php

/*
|--------------------------------------------------------------------------
| SPAR — Standalone host overrides
|--------------------------------------------------------------------------
|
| The spar-core package ships the full config/spar.php defaults (merged first).
| This file overrides ONLY the keys that differ for the standalone deployment:
|
|   - host_mode  = 'standalone'  -> NullTelehealthBridge, no online-consult CTA
|   - user_model = null          -> no telehealth User table; identity is
|                                   SPAR-owned only
|   - layouts    -> standalone's own Blade shells (not ZapMed's layouts.app)
|
| All other SPAR settings (channels, link TTL, reminders, consent, branding)
| inherit the package defaults and are env-tunable.
|
*/

return [
    'host_mode' => env('SPAR_HOST_MODE', 'standalone'),

    'user_model' => null,

    'layouts' => [
        'staff' => 'layouts.staff',
        'patient' => 'layouts.patient',
    ],

    // Standalone brand chrome. The updated "Pharmacy at SPAR" lockup lives in
    // this host's public/img; the staff/patient layouts read branding.logo_path.
    'branding' => [
        'name' => env('SPAR_BRAND_NAME', 'Pharmacy at SPAR'),
        'primary_color' => env('SPAR_BRAND_COLOR', '#006B3F'),
        'logo_path' => env('SPAR_BRAND_LOGO', 'img/pharmacy-at-spar-logo.jpg'),
    ],

    // Renewal handoff has no target in standalone (NullTelehealthBridge never
    // issues a handoff link); point at the tracker as a safe fallback.
    'renewal_handoff_route' => 'my-meds.track',

    /*
    | Route middleware groups for THIS host. Standalone has NO `role:` enum
    | middleware — staff authz is the pharmacy_users guard + the identity
    | provider's pharmacy scope (spar.scope). Overrides the package defaults
    | (which carry ZapMed's role: middleware).
    */
    'route_middleware' => [
        'public' => ['web'],
        'staff' => ['web', 'auth', 'spar.scope', 'spar.timeout'],
        'admin' => ['web', 'auth'],
    ],
];
