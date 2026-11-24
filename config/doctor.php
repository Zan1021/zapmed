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
];
