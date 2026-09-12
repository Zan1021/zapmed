<?php

/**
 * Contro (source-of-truth CRM) ingestion configuration.
 *
 * Drives the upstream_sync staging pull. Field names below are VERBATIM from Contro's DTOs/schemas
 * (see specs/contro-rebuild/03-contro-import-blueprint.md §1) — casing is deliberate and inconsistent
 * across entities (e.g. patients use `lastModifiedDT`, prescriptions use `lastModifiedDt`). Do NOT
 * "normalise" these; the API is matched on the exact string.
 *
 * Nothing here triggers side-effects. Import must never send email/SMS/WhatsApp, charge/refund,
 * submit to RxHub, or fire outbound webhooks (blueprint §3 rule 6).
 */
return [

    // Payment gateway for the OWNED system is PayFast (confirmed by Captain Zan 2026-09-13).
    // Contro's payments enum says 'peach' and finance_reports says 'PayFast' — we map provider -> payfast.
    'payment_gateway' => 'payfast',

    'api' => [
        // Swagger lists http://; we require the production HTTPS base URL (blocker until Craig provides).
        'base_url' => env('CONTRO_API_BASE_URL'),
        'email' => env('CONTRO_API_EMAIL'),
        'password' => env('CONTRO_API_PASSWORD'),
        'login_path' => '/api/crm/auth/login',
        // int64 ids must arrive as strings — never parse to int.
        'accept_header' => 'application/json;IEEE754Compatible=true',
        'page_size' => 100,
        'max_retries' => 5,
        'retry_backoff_ms' => 500,
        // JWE response envelope is NOT live on Contro yet; keypair only needed if enabled later.
        'envelope_enabled' => env('CONTRO_ENVELOPE_ENABLED', false),
    ],

    'upstream_source' => 'contro',

    /**
     * The 7 Contro entity sets. Per entity:
     *  - path            : API collection path
     *  - id_field        : the field used as upstream_id (patients = userHash, not a numeric id)
     *  - watermark_field : field for incremental $filter gt :hwm (VERBATIM casing)
     *  - detail_path     : optional per-record enrichment endpoint (orders only)
     */
    'entities' => [
        'orders' => [
            'path' => '/api/crm/orders',
            'id_field' => 'id',
            'watermark_field' => 'dtCreatedModified',
            // OrderDetailDto adds 7 fields; fetched per order by orderNumber.
            'detail_path' => '/api/crm/orders/{orderNumber}',
        ],
        'order_status_history' => [
            'path' => '/api/crm/order-status-history',
            'id_field' => 'id',
            'watermark_field' => 'changedAt',
        ],
        'patients' => [
            'path' => '/api/crm/patients',
            'id_field' => 'userHash',       // ONLY patient key; internal int64 id is not exposed
            'watermark_field' => 'lastModifiedDT', // NB: upper-case T (differs from prescriptions/coupons)
        ],
        'payments' => [
            'path' => '/api/crm/payments',
            'id_field' => 'id',
            'watermark_field' => 'dtCreatedModified',
        ],
        'prescriptions' => [
            'path' => '/api/crm/prescriptions',
            'id_field' => 'id',
            'watermark_field' => 'lastModifiedDt', // NB: lower-case t (differs from patients)
        ],
        'products' => [
            'path' => '/api/crm/products',
            'id_field' => 'id',
            'watermark_field' => 'dtCreatedModified',
        ],
        'coupons' => [
            'path' => '/api/crm/coupons',
            'id_field' => 'id',
            'watermark_field' => 'lastModifiedDt',
        ],
    ],
];
