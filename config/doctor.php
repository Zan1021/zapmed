<?php

/**
 * Doctor portal settings (Craig's build guide, section 4 and 8.1). Values as data so they can be tuned without code.
 */
return [
    'requests' => [
        // Tell people about a new request / a response. OFF until DOCTOR_REQUEST_NOTIFY=true.
        // The message never carries the request text, a patient name or any health detail.
        'notify_enabled' => env('DOCTOR_REQUEST_NOTIFY', false),

        // Attachments live on a private disk and are only served through an access-checked route.
        'disk' => env('DOCTOR_REQUEST_DISK', 'local'),
        'max_file_kb' => 10240,
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'csv'],
        'max_files_per_request' => 10,
    ],

    'time' => [
        // A timer left running is cut at this many minutes when it is stopped (and flagged "capped" for review).
        'max_timer_minutes' => 240,
        // Biggest single entry a doctor can type in or adjust to.
        'max_entry_minutes' => 720,
        // How far back a manual entry may be dated.
        'manual_back_days' => 62,
        'timezone' => 'Africa/Johannesburg',
    ],
    'followups' => [
        // Days a doctor can ask for (Craig's guide 4.2).
        'days' => [7, 14, 20, 30, 60, 90],
        // The patient is prompted to book this many days before the follow-up is due.
        'prompt_days_before' => 3,
        // OFF until DOCTOR_FOLLOWUP_NOTIFY=true. Messages never name a medicine or give health detail.
        'notify_enabled' => env('DOCTOR_FOLLOWUP_NOTIFY', false),
        // Operations is told too: comma separated emails in DOCTOR_FOLLOWUP_OPS_EMAIL.
        'ops_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('DOCTOR_FOLLOWUP_OPS_EMAIL', ''))))),
        // Order statuses where the order itself moves to Pending follow-up request. In any other status (a script still
        // being prepared or posted) the order is left alone and only the open follow-up blocks the next repeat.
        'move_order_from' => ['Delivered', 'Completed'],
    ],
    'blood_tests' => [
        // The tests a doctor can tick. PROVISIONAL list: Dr Anke / Craig to confirm the real menu and lab codes.
        'catalogue' => [
            'fbc' => 'Full blood count',
            'lipids' => 'Cholesterol (lipid profile)',
            'hba1c' => 'HbA1c',
            'glucose' => 'Fasting glucose',
            'tsh' => 'Thyroid (TSH)',
            'liver' => 'Liver function',
            'kidney' => 'Kidney function (urea and electrolytes)',
            'vitd' => 'Vitamin D',
            'iron' => 'Iron studies',
            'hormones' => 'Hormone profile',
        ],
        // Lab request forms a doctor can attach by name. PROVISIONAL: no form templates exist yet, so only the choice is
        // recorded. A doctor can also upload their own form.
        'lab_forms' => [
            'pathcare' => 'PathCare request form',
            'lancet' => 'Lancet request form',
            'ampath' => 'Ampath request form',
        ],
        // Tell the patient. OFF until DOCTOR_BLOODTEST_NOTIFY=true. Never names the tests or the medicine.
        'notify_enabled' => env('DOCTOR_BLOODTEST_NOTIFY', false),
        'disk' => env('DOCTOR_BLOODTEST_DISK', 'local'),
        'max_file_kb' => 10240,
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
        'max_files_per_request' => 10,
    ],
    'authorisations' => [
        // A doctor's authorisation for an order's repeats lasts this many months (Craig's guide 4.1: six months).
        'months' => 6,
        // It shows up for review this many days before it ends ("review around month five").
        'review_days_before' => 31,
        // Once an order has an authorisation and none is valid any more, the next repeat is held back until a doctor renews it.
        // PROVISIONAL reading of "renew for six months": confirm with Craig. Orders with no authorisation record are never held.
        'block_when_expired' => env('DOCTOR_AUTH_BLOCK_EXPIRED', true),
    ],
    'notes' => [
        // Tell addressees about a new staff note. OFF until DOCTOR_NOTE_NOTIFY=true. The message never carries the note.
        'notify_enabled' => env('DOCTOR_NOTE_NOTIFY', false),
    ],
];
