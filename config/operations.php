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
];
