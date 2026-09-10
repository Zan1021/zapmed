<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Onboarding Mode (mutually exclusive)
    |--------------------------------------------------------------------------
    |
    | Selects how patients get their contactable identity into the system.
    | A deployment runs in EXACTLY ONE mode (see spec FR-6, design §8.1):
    |
    |   'import'             The SPAR export carries contact details
    |                        (first name, surname, cellphone and/or email).
    |                        SparImportService populates identity directly;
    |                        no pharmacist data-entry required.
    |
    |   'pharmacist_capture' The export is a sales/transaction extract with NO
    |                        contact details. The CSV seeds medication history
    |                        (keyed on Profile Code); a pharmacist captures
    |                        name/surname/contact + consent via the capture UI.
    |
    | The deciding factor is simply whether the file carries contact columns.
    |
    */

    'onboarding_mode' => env('SPAR_ONBOARDING_MODE', 'pharmacist_capture'),

    /*
    |--------------------------------------------------------------------------
    | Import — file naming + FTP drop ingestion
    |--------------------------------------------------------------------------
    |
    | Two ways in (both dev'd, per Captain Zan):
    |   1. Manual upload via the admin "SPAR Data Imports" screen.
    |   2. Automated FTP drop: SPAR (or their vendor) drops the two files into
    |      a directory that an FTP server exposes; `spar:ingest-drop` (scheduled)
    |      pairs + imports them. This config only describes WHERE to look and HOW
    |      to recognise the two files — it provisions NO FTP server/credentials
    |      (that's infra, set up at deploy). Point 'drop_disk'/'drop_path' at the
    |      directory the FTP account writes to.
    |
    | File recognition is by filename substring (case-insensitive) so the exact
    | vendor names ("SalesExtract072026.csv", "Drug Usage 01 Sept 2026.xlsx")
    | and our demo names ("Demo SalesExtract...", "Demo Drug Usage...") both match.
    |
    */

    'import' => [
        // Filesystem disk + relative path the FTP account drops files into.
        'drop_disk' => env('SPAR_IMPORT_DROP_DISK', 'local'),
        'drop_path' => env('SPAR_IMPORT_DROP_PATH', 'spar-drop'),

        // Where processed files are moved to (archive) or whether to delete.
        // PHI-bearing files SHOULD be deleted after processing; archiving is
        // opt-in for non-prod debugging only.
        'archive_processed' => env('SPAR_IMPORT_ARCHIVE', false),
        'archive_path' => env('SPAR_IMPORT_ARCHIVE_PATH', 'spar-drop/processed'),

        // Filename substrings used to classify a dropped file.
        'sales_match' => env('SPAR_IMPORT_SALES_MATCH', 'salesextract'),
        'drug_usage_match' => env('SPAR_IMPORT_DRUG_MATCH', 'drug usage'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Host Mode
    |--------------------------------------------------------------------------
    |
    | 'integrated' = running inside ZapMed (telehealth bridge active).
    | 'standalone' = running as the independent SPAR app (NullTelehealthBridge).
    | Drives which TelehealthBridge binding is used and whether the renewal
    | "consult a ZapMed doctor" CTA is offered.
    |
    */

    'host_mode' => env('SPAR_HOST_MODE', 'integrated'),

    /*
    |--------------------------------------------------------------------------
    | Host User Model (integrated only)
    |--------------------------------------------------------------------------
    |
    | Integrated hosts (ZapMed) supply their User model class so SPAR can resolve
    | a linked user for identity fallback WITHOUT the package naming any host
    | class (AC-3). Standalone leaves this null — there is no users table and
    | identity comes entirely from SPAR-owned records.
    |
    */

    'user_model' => env('SPAR_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Blind Index Key (national patient identity)
    |--------------------------------------------------------------------------
    |
    | Profile Code and cellphone are encrypted at rest, so they cannot be matched
    | with a plain SQL WHERE. We store a deterministic keyed HMAC (blind index) of
    | each so a patient can be de-duplicated ACROSS pharmacies without decrypting
    | and without leaking PHI. This key is SEPARATE from APP_KEY and is rotatable
    | (rotation = re-run spar:backfill-blind-index). Set it per environment and
    | back it up — losing it means re-hashing from the still-encrypted plaintext.
    |
    | Falls back to APP_KEY only so local/test never crashes with an empty key;
    | production MUST set SPAR_BLIND_INDEX_KEY explicitly.
    |
    */

    'blind_index_key' => env('SPAR_BLIND_INDEX_KEY', env('APP_KEY', 'spar-dev-blind-index-key')),

    /*
    |--------------------------------------------------------------------------
    | Messaging Channels (WhatsApp-primary, SMS-fallback)
    |--------------------------------------------------------------------------
    |
    | Ordered priority list resolved by MessagingChannel (spec FR-12). The
    | first available/successful channel wins; later entries are fallbacks.
    |
    | NOTE: WhatsApp is the INTENDED primary but is DEFERRED — no provider is
    | provisioned yet (2026-09). Until then the pilot runs on in-app + email
    | + interim SMS. Adding 'whatsapp' later is a config + binding change only.
    |
    | The system must NEVER be configured SMS-less/WhatsApp-only in a way that
    | leaves a contactable patient unreachable — keep at least one working
    | text channel as a fallback.
    |
    */

    'channels' => array_values(array_filter(array_map('trim', explode(
        ',',
        env('SPAR_CHANNELS', 'inapp,email,sms')
    )))),

    /*
    |--------------------------------------------------------------------------
    | Channel Factories (host-injected concrete channels)
    |--------------------------------------------------------------------------
    |
    | Map of channel key => resolvable (class-string or callable) that produces
    | a MessagingChannel. The package ships portable in-app + email channels;
    | each HOST registers its own SMS (and later WhatsApp) implementation here
    | (ZapMed's SparServiceProvider adds 'sms' => App\Services\Spar\Channels\
    | SmsChannel::class). Left empty by default so the package has no host dep.
    |
    */

    'channel_factories' => [],

    'whatsapp' => [
        'enabled' => env('SPAR_WHATSAPP_ENABLED', false),

        // Driver: 'log' (default — no creds, records what it would send, so the
        // system is fully testable before Meta onboarding) or 'cloud_api'
        // (real Meta Cloud API direct). Flip to cloud_api once the WABA token +
        // phone number id are provisioned.
        'driver' => env('SPAR_WHATSAPP_DRIVER', 'log'),

        // Meta Cloud API credentials (cloud_api driver only).
        'phone_number_id' => env('SPAR_WHATSAPP_PHONE_NUMBER_ID'),
        'token' => env('SPAR_WHATSAPP_TOKEN'),
        'api_version' => env('SPAR_WHATSAPP_API_VERSION', 'v21.0'),
        'language' => env('SPAR_WHATSAPP_LANG', 'en'),

        // Meta-approved template NAMES, keyed by the payload template key the
        // app uses. Proactive (outside-24h-window) sends MUST use one of these.
        // Author the bodies for Meta submission (see docs/whatsapp-templates.md).
        'templates' => [
            'onboarding_consent' => env('SPAR_WA_TPL_ONBOARDING'),
            'monthly_collection' => env('SPAR_WA_TPL_MONTHLY'),
            'renewal_due' => env('SPAR_WA_TPL_RENEWAL'),
            'missed_renewal' => env('SPAR_WA_TPL_MISSED'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Patient Tracker Link
    |--------------------------------------------------------------------------
    |
    | Patients have NO login (spec FR-9). Access is via a signed, expiring link
    | delivered over a messaging channel; an optional short OTP re-verifies a
    | returning visitor so a forwarded link cannot leak PHI.
    |
    */

    'link' => [
        'ttl_minutes' => (int) env('SPAR_LINK_TTL_MINUTES', 60 * 24 * 7), // 7 days
        'require_otp_reverify' => env('SPAR_LINK_REQUIRE_OTP', true),
        'otp_ttl_minutes' => (int) env('SPAR_OTP_TTL_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminders
    |--------------------------------------------------------------------------
    */

    'reminders' => [
        'monthly_lead_days' => (int) env('SPAR_REMINDER_LEAD_DAYS', 7),   // before dispense due
        'renewal_lead_days' => (int) env('SPAR_RENEWAL_LEAD_DAYS', 14),   // before final dispense
        'missed_followup_days' => (int) env('SPAR_MISSED_FOLLOWUP_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session / Scope Security (pharmacy staff)
    |--------------------------------------------------------------------------
    */

    'session_timeout_minutes' => (int) env('SPAR_SESSION_TIMEOUT_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Consent
    |--------------------------------------------------------------------------
    |
    | The version string recorded against each consent grant (spec FR-7.3).
    | Bump when the consent wording changes so the evidence trail is exact.
    | The wording itself is defined by the compliance officer.
    |
    */

    'consent_version' => env('SPAR_CONSENT_VERSION', '1.0'),

    /*
    |--------------------------------------------------------------------------
    | Branding (SPAR-branded pilot; white-label left possible, not built now)
    |--------------------------------------------------------------------------
    */

    'branding' => [
        'name' => env('SPAR_BRAND_NAME', 'SPAR Pharmacy'),
        'primary_color' => env('SPAR_BRAND_COLOR', '#006B3F'),
        'logo_path' => env('SPAR_BRAND_LOGO', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Layouts (host-supplied Blade layouts)
    |--------------------------------------------------------------------------
    |
    | The SPAR Livewire screens live in this package but render inside a
    | host-provided layout. Each host points these at its own layout view:
    |   - 'staff'   : the authenticated staff/admin shell
    |   - 'patient' : the no-login patient tracker shell
    | The package cannot ship these layouts (they belong to the host chrome),
    | so the defaults are the ZapMed integrated views; the standalone app
    | overrides them via env / its own config/spar.php.
    |
    */

    'layouts' => [
        'staff' => env('SPAR_LAYOUT_STAFF', 'layouts.app'),
        'patient' => env('SPAR_LAYOUT_PATIENT', 'layouts.spar-meds'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Middleware Groups (host-configured)
    |--------------------------------------------------------------------------
    |
    | The package ships routes/spar.php but each host decides how to gate them.
    | Defaults below match the ZapMed integrated host (role enum + web/auth).
    | The standalone app overrides these (its own guard, no role: enum).
    |   - public : patient tracker surface (public, signed-link/no-login)
    |   - staff  : authenticated pharmacy-staff area
    |   - admin  : authenticated admin area
    |
    | 'renewal_handoff_route' is the named route the signed renewal handoff
    | redirects to in the host (integrated = ZapMed 'dashboard').
    |
    */

    'route_middleware' => [
        'public' => ['web'],
        'staff' => ['web', 'auth', 'verified', 'role:pharmacy_staff', 'spar.scope', 'spar.timeout'],
        'admin' => ['web', 'auth', 'verified', 'role:admin'],
    ],

    'renewal_handoff_route' => env('SPAR_RENEWAL_HANDOFF_ROUTE', 'dashboard'),

    /*
    |--------------------------------------------------------------------------
    | Online Consultation (external link)
    |--------------------------------------------------------------------------
    |
    | A permanent link shown to patients in the tracker for booking an online
    | consultation to obtain a NEW prescription (e.g. ZapMed telehealth at
    | zapmed.co.za). This is a plain OUTBOUND LINK — deliberately NOT the
    | TelehealthBridge — so the standalone app can point patients at an online
    | consult provider WITHOUT reintroducing telehealth coupling (AC-4 stays
    | intact: no in-app booking/handoff, no zapmed_prescription_id written).
    | Set to null/empty to hide the button.
    |
    */

    'online_consult' => [
        'enabled' => env('SPAR_ONLINE_CONSULT_ENABLED', true),
        'url' => env('SPAR_ONLINE_CONSULT_URL', 'https://zapmed.co.za'),
        'label' => env('SPAR_ONLINE_CONSULT_LABEL', 'Get a new prescription online'),
    ],

];
