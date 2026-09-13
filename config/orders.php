<?php

/**
 * Order lifecycle state machine — defined as DATA (specs/contro-rebuild/01-system-design-dossier.md §4,
 * 03-contro-import-blueprint.md §2.4/2.5). Mirrors Dave's 22-Apr Order Status Transitions doc as
 * reconstructed from Mark's orders module. The transition rules are SEEDED into the DB
 * (OrderStatusTransitionSeeder) and enforced by OrderStatusMachine::isTransitionAllowed().
 *
 * Contro status spelling is preserved VERBATIM, including the misspelling 'PendingConsulation'.
 * Do NOT correct it — the importer matches Contro's exact strings.
 */
return [

    // ---- 25 statuses (20 base + 5 restored/Jess-confirmed) --------------------------------------
    'statuses' => [
        // base 20
        'PendingPayment',
        'PaymentFailed',
        'PendingBooking',
        'PendingConsulation',   // sic — Contro spelling
        'NoShow',
        'InReview',
        'AwaitingInformation',
        'Processing',
        'PharmacyProcessing',
        'PreparingMedication',
        'ClaimRejected',
        'Despatched',
        'Delivered',
        'Completed',
        'Paused',
        'Cancelled',
        'RepeatPaymentFailed',
        'ThreeRepeatFailures',
        'PendingFollowUpBooking',
        'PendingFollowUp',
        // restored 5 (orders migration 0002, Jess-confirmed live)
        'PaymentReceived',
        'NotDelivered',
        'RefundComplete',
        'RefundNeeded',
        'TwoRepeatFailures',
    ],

    // ---- trigger types --------------------------------------------------------------------------
    'triggers' => ['payment', 'calendar', 'doctor', 'system_timer', 'patient', 'rxhub', 'admin'],

    /**
     * Allowed transitions: [from, to, trigger]. Seeded into orders_order_status_transition-equivalent
     * rules. The importer flags any Contro history step not represented here (quarantine, not reject).
     */
    'transitions' => [
        // 1-2 payment
        ['PendingPayment', 'PaymentFailed', 'payment'],
        ['PaymentFailed', 'PendingPayment', 'patient'],          // retry
        ['PendingPayment', 'PaymentReceived', 'payment'],        // restored intermediate
        ['PaymentReceived', 'PendingBooking', 'payment'],
        ['PendingPayment', 'PendingBooking', 'payment'],         // direct (no intermediate)
        // 3-4 booking / consult
        ['PendingBooking', 'PendingConsulation', 'calendar'],
        ['PendingConsulation', 'NoShow', 'system_timer'],
        ['NoShow', 'PendingBooking', 'patient'],                 // rebook
        ['PendingConsulation', 'InReview', 'doctor'],            // consult completed
        // 5-7 review
        ['InReview', 'AwaitingInformation', 'doctor'],
        ['AwaitingInformation', 'InReview', 'patient'],          // patient supplies info
        ['InReview', 'Processing', 'doctor'],                    // approve
        ['InReview', 'Cancelled', 'doctor'],                     // decline
        // 8 pharmacy pipeline (rxhub-driven)
        ['Processing', 'PharmacyProcessing', 'rxhub'],
        ['PharmacyProcessing', 'PreparingMedication', 'rxhub'],
        ['PharmacyProcessing', 'ClaimRejected', 'rxhub'],
        ['PreparingMedication', 'Despatched', 'rxhub'],
        ['Despatched', 'Delivered', 'rxhub'],
        ['Despatched', 'NotDelivered', 'rxhub'],                 // restored
        ['Delivered', 'Completed', 'system_timer'],
        // refund recovery (restored)
        ['ClaimRejected', 'RefundNeeded', 'admin'],
        ['RefundNeeded', 'RefundComplete', 'admin'],
        // 10 subscription / repeat
        ['Completed', 'PendingFollowUpBooking', 'system_timer'], // 6-mo email
        ['PendingFollowUpBooking', 'PendingFollowUp', 'calendar'],
        ['PendingFollowUp', 'PendingConsulation', 'calendar'],
        ['PendingPayment', 'RepeatPaymentFailed', 'payment'],
        ['RepeatPaymentFailed', 'PendingPayment', 'patient'],    // retry
        ['RepeatPaymentFailed', 'TwoRepeatFailures', 'payment'], // restored intermediate
        ['TwoRepeatFailures', 'ThreeRepeatFailures', 'payment'],
        ['ThreeRepeatFailures', 'Cancelled', 'system_timer'],    // payment_abandoned
        // wildcard admin
        ['Processing', 'Paused', 'admin'],
        ['Paused', 'Processing', 'admin'],
    ],

    /**
     * RxHub inbound event code -> resulting order status (orders_rxhub_event_map, 5 codes).
     * specs/contro-rebuild §6. Inbound webhook drives the pharmacy pipeline.
     */
    'rxhub_event_map' => [
        'New' => 'PharmacyProcessing',
        'ScriptProcessed' => 'PreparingMedication',
        'ScriptRejected' => 'ClaimRejected',
        'ScriptDispatched' => 'Despatched',
        'ScriptDelivered' => 'Delivered',
    ],

    'initial_status' => 'PendingPayment',

    /**
     * Ops board swimlanes (Order Board / Kanban, specs/contro-rebuild/08 §2.2).
     *
     * The 25 raw statuses are unusable as 25 columns, so the board groups them into ordered,
     * ops-meaningful lanes. This is presentation DATA only — it never affects the state machine.
     * Every status MUST appear in exactly one lane; a coverage test enforces that so a newly added
     * status can never silently vanish from the board.
     */
    'board_lanes' => [
        'awaiting_payment' => [
            'label' => 'Awaiting payment',
            'colour' => 'amber',
            'statuses' => ['PendingPayment', 'PaymentFailed', 'RepeatPaymentFailed', 'TwoRepeatFailures', 'ThreeRepeatFailures'],
        ],
        'booking' => [
            'label' => 'Booking & consult',
            'colour' => 'sky',
            'statuses' => ['PaymentReceived', 'PendingBooking', 'PendingConsulation', 'NoShow'],
        ],
        'clinical_review' => [
            'label' => 'Clinical review',
            'colour' => 'violet',
            'statuses' => ['InReview', 'AwaitingInformation'],
        ],
        'pharmacy' => [
            'label' => 'Pharmacy pipeline',
            'colour' => 'indigo',
            'statuses' => ['Processing', 'PharmacyProcessing', 'PreparingMedication', 'ClaimRejected'],
        ],
        'fulfilment' => [
            'label' => 'Fulfilment',
            'colour' => 'blue',
            'statuses' => ['Despatched', 'Delivered', 'NotDelivered'],
        ],
        'follow_up' => [
            'label' => 'Repeat & follow-up',
            'colour' => 'teal',
            'statuses' => ['Completed', 'PendingFollowUpBooking', 'PendingFollowUp'],
        ],
        'closed' => [
            'label' => 'Closed & exceptions',
            'colour' => 'slate',
            'statuses' => ['Paused', 'Cancelled', 'RefundNeeded', 'RefundComplete'],
        ],
    ],
];
