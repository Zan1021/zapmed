<?php

/**
 * Patient portal settings (Craig's build guide, section 3). Values as data so they can be tuned without code.
 */
return [
    'orders' => [
        // What the patient reads instead of the internal status. Anything missing falls back to "In progress".
        'status_labels' => [
            'PendingPayment' => 'Waiting for payment',
            'PaymentFailed' => 'Payment did not go through',
            'PaymentReceived' => 'Paid',
            'PendingBooking' => 'Book your consultation',
            'PendingConsulation' => 'Consultation booked',
            'NoShow' => 'Consultation missed',
            'InReview' => 'With your doctor',
            'AwaitingInformation' => 'We need more information from you',
            'PendingBloodTests' => 'Waiting for your blood tests',
            'PendingReviewFeedback' => 'Your doctor is reviewing your results',
            'PendingFollowUpRequest' => 'A follow-up is needed',
            'PendingFollowUpProfileBook' => 'Follow-up booked',
            'PendingFollowUpBooking' => 'Time to book your follow-up',
            'PendingFollowUp' => 'Follow-up booked',
            'Processing' => 'Being prepared',
            'PharmacyProcessing' => 'Being prepared',
            'PreparingMedication' => 'Being prepared',
            'ClaimRejected' => 'Medical aid claim needs attention',
            'Despatched' => 'On its way',
            'NotDelivered' => 'Delivery did not work out',
            'Delivered' => 'Delivered',
            'Completed' => 'Completed',
            'Paused' => 'Paused',
            'Cancelled' => 'Cancelled',
            'RepeatPaymentFailed' => 'Repeat payment did not go through',
            'TwoRepeatFailures' => 'Repeat payment did not go through',
            'ThreeRepeatFailures' => 'Paused after failed payments',
            'RefundNeeded' => 'Refund on its way',
            'RefundComplete' => 'Refunded',
        ],
        // Statuses where an order is finished and can be archived. An unfinished checkout can be archived too.
        'archivable_statuses' => ['Cancelled', 'RefundComplete', 'PendingPayment', 'PaymentFailed'],
        'authorisation_check_in_note' => 'Book a short check-in with your doctor to renew.',
    ],
    'health' => [
        // An order counts as weight-loss when its service category contains one of these words (any case).
        'weight_loss_markers' => ['weight'],
        // Medical records and results (Craig's guide 3.1). Private disk, random file names.
        'records' => [
            'disk' => env('PATIENT_RECORD_DISK', 'local'),
            'max_kb' => 10240,
            'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
            'max_per_patient' => 100,
        ],
        // The patient may log their own weight and update their height. Same limits as the coach app (config coach.weight).
        'height_cm' => [100, 230],
    ],
];
