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
];
