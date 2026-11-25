<?php

/**
 * Operations portal settings (Craig's build guide, section 6). Values as data so they can be tuned without code.
 */
return [
    'reports' => [
        // Upcoming repeats are batched to these weekdays (0 = Sunday ... 6 = Saturday): Monday and Wednesday, so deliveries
        // land before weekends and holidays. The batch day is the first of these ON OR AFTER the repeat date. PROVISIONAL.
        'batch_weekdays' => [1, 3],

        // Orders in these statuses need somebody to do something: they are the "records requiring action" in the customer tracker.
        'action_statuses' => [
            'AwaitingInformation', 'PaymentFailed', 'RepeatPaymentFailed', 'TwoRepeatFailures', 'ThreeRepeatFailures',
            'NoShow', 'ClaimRejected', 'NotDelivered', 'PendingFollowUpRequest', 'PendingFollowUpProfileBook',
            'PendingBloodTests', 'PendingReviewFeedback', 'Paused', 'RefundNeeded',
        ],

        // The most rows one report shows on screen or in a CSV.
        'max_rows' => 5000,
    ],

    'staff' => [
        // Roles Operations can give a signed-up user (Craig's guide 6.4: Operations, Doctor or Health Coach).
        'assignable_roles' => ['admin', 'doctor', 'health_coach'],

        // Where a staff member is registered. A suggestion list: the board is saved as typed.
        'boards' => ['HPCSA', 'SANC', 'SAPC'],

        // verified lets a doctor appear in booking (it switches DoctorProfile.is_verified on).
        'registration_statuses' => ['pending' => 'Waiting to be checked', 'verified' => 'Verified', 'lapsed' => 'Registration lapsed', 'not_required' => 'Not required'],

        // Signature image used on prescriptions. Private disk, random file name.
        'signature' => [
            'disk' => env('STAFF_SIGNATURE_DISK', 'local'),
            'allowed_extensions' => ['png', 'jpg', 'jpeg', 'webp'],
            'max_kb' => 2048,
        ],
    ],

    'tracking' => [
        'default_courier' => 'DPS Laser',
        // Where a tracking number can be followed. {number} is replaced. Set DPS_TRACKING_URL once DPS Laser give us the address;
        // until then Operations paste the link by hand. Nothing here talks to DPS Laser: they have not given us API access yet.
        'url_template' => env('DPS_TRACKING_URL'),
        'statuses' => [
            'created' => 'Booked with the courier',
            'collected' => 'Collected from the pharmacy',
            'in_transit' => 'On its way',
            'out_for_delivery' => 'Out for delivery',
            'delivered' => 'Delivered',
            'failed' => 'Delivery did not work out',
        ],
    ],

    'abandoned_checkout' => [
        // Remind a patient who started an order and did not pay (Craig's guide 6.1). OFF until OPS_ABANDONED_NOTIFY=true.
        'notify_enabled' => env('OPS_ABANDONED_NOTIFY', false),
        // How long an unpaid order sits before the reminder, and how old it can be and still get one. The upper limit means that
        // switching the reminder on later does not message everybody who ever left a cart.
        'after_hours' => 24,
        'max_age_days' => 3,
        // An optional thank-you coupon made for that patient. OFF: there is no coupon box at checkout yet, so a code could not be
        // used by the patient on their own. Turn on only once checkout can take a code (then the patient sees it under Rewards).
        'coupon' => [
            'enabled' => env('OPS_ABANDONED_COUPON', false),
            'percent' => 10,
            'valid_days' => 7,
        ],
    ],

    'addons' => [
        // Tell the patient when an add-on is added, paused or cancelled. OFF until OPS_ADDON_NOTIFY=true. No health detail.
        'notify_enabled' => env('OPS_ADDON_NOTIFY', false),
    ],

    'templates' => [
        // Words a message may never contain: no health detail in any message (Craig's guide 8.3). Medicine names are checked against
        // the product list as well.
        'banned_words' => ['diagnosis', 'diagnosed', 'diabetes', 'hiv', 'cancer', 'depression', 'blood pressure', 'dose', 'dosage', 'injection', 'pregnan', 'std', 'sti'],

        // The messages Operations can reword or switch off. The text here is what is sent until somebody saves a different one.
        // {placeholders} are filled in by the system; only the ones listed can be used, and "required" ones must stay in.
        'registry' => [
            'doctor.followup_requested' => [
                'name' => 'Doctor asked for a follow-up', 'placeholders' => [], 'required' => [],
                'subject' => 'A follow-up appointment is needed',
                'body' => 'Your doctor has asked for a follow-up appointment. Sign in to Zapmed to book it.',
            ],
            'doctor.followup_prompt' => [
                'name' => 'Reminder to book a follow-up', 'placeholders' => [], 'required' => [],
                'subject' => 'Time to book your follow-up',
                'body' => 'Your follow-up appointment is coming up. Sign in to Zapmed to book it so your next repeat is not delayed.',
            ],
            'doctor.bloodtest_requested' => [
                'name' => 'Doctor asked for tests', 'placeholders' => [], 'required' => [],
                'subject' => 'Blood tests needed',
                'body' => 'Your doctor has asked for some tests. Sign in to Zapmed to see what to do next.',
            ],
            'ops.abandoned_checkout' => [
                'name' => 'Unfinished order reminder', 'placeholders' => ['link', 'coupon_text'], 'required' => ['link'],
                'subject' => 'Finish your Zapmed order',
                'body' => 'You started an order with Zapmed and did not finish paying. You can pick up where you left off: sign in and open My Orders.{coupon_text} {link}',
            ],
            'ops.addon_added' => [
                'name' => 'Add-on added', 'placeholders' => ['addon'], 'required' => [],
                'subject' => 'An add-on was added to your order',
                'body' => 'We added {addon} to your order. Sign in to Zapmed and open My Orders to see it.',
            ],
            'ops.addon_paused' => [
                'name' => 'Add-on paused', 'placeholders' => ['addon'], 'required' => [],
                'subject' => 'Your add-on was updated',
                'body' => 'Your {addon} add-on was paused. Sign in to Zapmed and open My Orders to see it.',
            ],
            'ops.addon_active' => [
                'name' => 'Add-on continued', 'placeholders' => ['addon'], 'required' => [],
                'subject' => 'Your add-on was updated',
                'body' => 'Your {addon} add-on was continued. Sign in to Zapmed and open My Orders to see it.',
            ],
            'ops.addon_cancelled' => [
                'name' => 'Add-on cancelled', 'placeholders' => ['addon'], 'required' => [],
                'subject' => 'Your add-on was updated',
                'body' => 'Your {addon} add-on was cancelled. Sign in to Zapmed and open My Orders to see it.',
            ],
        ],
    ],

    'integrations' => [
        // Minutes to wait before attempt 2, 3, ... (about ten attempts in all, Craig's guide 6.4). Only a DEFINITE failure is retried
        // automatically: the pharmacy answered with an error, or we could not reach it at all. If we do not know whether it arrived
        // (a timeout), the row is "unknown" and a person must check with the pharmacy before sending again, so nothing is ever sent twice.
        'retry_minutes' => [5, 15, 30, 60, 120, 240, 480, 720, 1440],
        'max_attempts' => 10,
    ],

    'booking_links' => [
        // A link Operations send a patient that opens the follow-up booking page (Craig's guide 6.2). About three uses and two
        // weeks to start with; Operations can change both per link, within the limits below.
        'default_uses' => 3,
        'default_days' => 14,
        'max_uses' => 20,
        'max_days' => 60,
    ],

    'server' => [
        // After a deploy the web server's code cache (opcache) is full of the OLD release's files and the site slows down. When this is
        // on, the minute scheduler notices a new release and reloads PHP once. Needs permission to run the command below without a
        // password (Forge gives the forge user this for service php8.x-fpm reload). OFF by default; ON in staging.
        'reload_php_on_release' => (bool) env('RELOAD_PHP_ON_RELEASE', false),
        'reload_command' => ['sudo', '-n', '/usr/sbin/service', 'php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'-fpm', 'reload'],
    ],
];
