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
];
