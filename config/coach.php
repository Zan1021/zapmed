<?php

/**
 * Zapmed Coach App settings (patient tracking + health coaching).
 *
 * Source: Craig's System Build Guide (19 Nov 2026), Appendix C. Design: .kiro/specs/dietitian-coach/.
 *
 * PROVISIONAL: every clinical number below was approved by Captain Zan only. Dr Anke / Amy have NOT
 * signed them off. They live here (not in code) so a clinician can change them without a release.
 * Flip 'provisional' to false once Dr Anke signs off.
 */
return [

    'provisional' => true,

    /*
     * Units. South Africa uses kg, so the app is kg only. Craig's guide lists a kg/lb switch as optional; it stays
     * built but is OFF. Set allow_lb to true to bring the switch back (nothing else needs to change).
     */
    'units' => ['allow_lb' => env('COACH_ALLOW_LB', false)],

    /* Patients are in South Africa. "Today", reminder times and cycle days use this zone (app timezone is UTC). */
    'timezone' => 'Africa/Johannesburg',

    /*
     * Who gets the coach app. A patient has access when they have an ACTIVE coaching assignment
     * or an active subscription on this plan (the R450/month weight-loss membership).
     */
    'plan_slug' => env('COACH_PLAN_SLUG', 'weight-loss'),
    'price_cents' => 45000, // R450/month, shown in copy only; billing is the subscription plan

    /* Daily targets (a starting point; Amy and the doctor set per-patient values later). */
    'targets' => [
        'kcal' => 1450,
        'protein_g' => 92,
        'fibre_g' => 28,
        'carbs_g' => 130,
        'fat_g' => 50,
        'water_ml' => 2200,
    ],

    /* Side-effect escalation to Dr Anke (no queue). Guide section 7. */
    'flags' => [
        'abdominal_symptom' => 'Abdominal pain',
        'abdominal_level' => 7,   // Abdominal pain at this score or more
        'any_level' => 9,         // Any symptom at this score or more

        /*
         * Who is told. Comma separated emails in COACH_FLAG_NOTIFY (e.g. Dr Anke). The member's assigned coach is
         * always told as well. If nobody is configured and there is no coach, active admins are told, so a flag
         * never goes nowhere. Messages carry NO health detail (member number only).
         */
        'notify_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('COACH_FLAG_NOTIFY', ''))))),
        'channels' => ['email', 'sms'],
        'dedupe_hours' => 24,
    ],

    /* Side effect saved screens (design screen 9). PROVISIONAL wording, Amy / Dr Anke to review. */
    'side_effect_copy' => [
        'normal_title' => 'This is part of the process',
        'common_title' => 'This is common',
        'common_body' => 'Many people feel this in the first weeks. It usually eases as your body adjusts.',
        'manage_title' => 'How to manage',
        'manage_body' => 'Small, protein-rich meals. Sip water through the day. Rest when you need to.',
        'luteal_line' => 'In your luteal phase, cravings and nausea can feel stronger.',
        'follow_up' => 'If symptoms last more than 48 hours or get worse, message Amy.',
        'flag_title' => "This needs a doctor's eyes",
        'flag_body' => 'Thank you for telling us. Dr Anke has been alerted. Please do not wait. You can reach a doctor now, with no queue.',
        'flag_confirmed' => 'Dr Anke has been alerted again and will contact you as soon as possible.',
        'emergency' => 'If you think this is an emergency, call 112 or 10177 now, or go to your nearest emergency unit.',
    ],

    'default_symptoms' => [
        'Nausea', 'Fatigue', 'Hair loss', 'Muscle loss', 'Injection anxiety',
        'Loose skin', 'Constipation', 'Reflux', 'Abdominal pain',
    ],

    'weight' => [
        'min_goal_kg' => 45,        // goal cannot be below this
        'valid_kg' => [20, 400],    // plausible range accepted when logging
        'tips' => [
            // PROVISIONAL wording (Amy / Dr Anke to review). {kg} is filled in.
            'luteal' => 'You are in your luteal phase. Water can hide fat loss right now. Compare the same phase from month to month.',
            'plateau' => 'A plateau is normal. Keep logging, it still counts.',
            'lost' => 'You lost {kg} this week. Keep your protein up.',
            'gained' => 'Your weight is up {kg} this week. Day-to-day changes are normal, so look at the trend.',
            'not_enough' => 'Keep logging. After a week of weigh-ins we can show you your trend.',
        ],
        'log_step_kg' => 0.1,
        'goal_step_kg' => 1,
        'ranges_days' => [14, 30, 90],
        'plateau_kg_7d' => 0.4,     // under this over 7 days = "plateau is normal"
        'prediction_days' => 90,    // average daily loss window for the predicted goal date
    ],

    'medication' => [
        'dose_interval_days' => 7,
        'half_life_days' => 5,      // ILLUSTRATIVE curve only; never presented as a blood level
        'chart_past_days' => 14,
        'chart_future_days' => 7,
        'sites' => [
            'Left upper arm', 'Right upper arm', 'Left stomach', 'Right stomach', 'Left thigh', 'Right thigh',
        ],
        'pain_max' => 10,
        'history_count' => 8,
        // PROVISIONAL wording (Amy / Dr Anke to review). Never names the medication, never advises a dose change.
        'tips' => [
            'appetite' => 'Appetite is usually lowest in the first two days after a dose.',
            'luteal' => 'In your luteal phase, nausea or cravings can feel stronger.',
        ],
        'no_dose_advice' => 'We never advise changing your dose. Speak to your doctor about that.',
    ],

    'water' => [
        'quick_adds_ml' => ['Glass' => 250, 'Bottle' => 350, 'Large' => 500, 'Flask' => 1000],
        'recent_log_count' => 5,
    ],

    'cycle' => [
        'default_length' => 28,
        'default_period' => 5,
        'min_length' => 21,
        'max_length' => 35,
        'ovulation_start_days_before_end' => 14,
        'ovulation_end_days_before_end' => 12,

        /* PROVISIONAL phase effects (weight, hunger, water, mood): up | down | steady. Amy / Dr Anke to review. */
        'phase_arrows' => [
            'menstrual' => ['weight' => 'up', 'hunger' => 'up', 'water' => 'steady', 'mood' => 'down'],
            'follicular' => ['weight' => 'down', 'hunger' => 'down', 'water' => 'steady', 'mood' => 'up'],
            'ovulation' => ['weight' => 'steady', 'hunger' => 'down', 'water' => 'steady', 'mood' => 'up'],
            'luteal' => ['weight' => 'up', 'hunger' => 'up', 'water' => 'up', 'mood' => 'down'],
        ],
        'phase_labels' => [
            'menstrual' => 'Period', 'follicular' => 'Follicular phase',
            'ovulation' => 'Ovulation', 'luteal' => 'Luteal phase',
        ],
    ],

    /* Reminder types and their defaults. Copy never names the medication or any health detail. */
    'reminders' => [
        'hydration' => ['enabled' => true, 'every_hours' => 2, 'from' => '08:00', 'until' => '20:00', 'smart_skip' => true, 'channel' => 'both'],
        'weigh_in' => ['enabled' => true, 'day' => 'monday', 'frequency' => 'weekly'],
        'dose_day' => ['enabled' => true, 'time' => '20:00'],
        'meals' => ['enabled' => true],
        'cycle' => ['enabled' => true],
    ],
    'reminder_channels' => ['push', 'whatsapp', 'both'],

    /* Call booking with the coach. */
    'calls' => [
        'lengths_minutes' => [15, 30],
        'days_ahead' => 5,
    ],

    /*
     * Onboarding (design screens 1 and 2). One worry only; Amy's reply changes with the choice.
     * Wording is provisional until Amy / Dr Anke review it. No medicine names, no health detail.
     */
    'onboarding' => [
        'worries' => [
            'side_effects' => [
                'label' => 'Side effects',
                'reply' => "That's completely normal to worry about. Most side effects are mild and short. I'll check in with you often and you can log anything in a few taps.",
            ],
            'what_to_eat' => [
                'label' => 'Not knowing what to eat',
                'reply' => "No stress. We'll build simple meals around foods you already enjoy, and I'll guide you step by step.",
            ],
            'motivation' => [
                'label' => 'Staying motivated',
                'reply' => "I'll be in your corner. Small wins every week add up, and we'll celebrate them together.",
            ],
            'cost' => [
                'label' => 'Cost of medicine',
                'reply' => "I hear you. We'll keep your plan practical and affordable, and we can talk it through on a call.",
            ],
        ],
        'amy_intro' => "Hi, I'm Amy. I'm a registered dietitian and I'll be your coach. I check in with you each week and I'm here between visits. Want a quick welcome call?",
        'plan_note' => 'A starting point. Amy and your doctor adjust it for you.',
    ],

    /* Every insight / estimate screen must carry this. */
    'disclaimers' => [
        'insight' => 'Insights are general information and not medical advice.',
        'medication_curve' => 'This curve is illustrative. It is not a blood test.',
        'bmi' => 'BMI is a general guide and does not diagnose any condition.',
        'cycle' => 'Predictions are estimates. Tell your doctor if your cycle changes a lot on treatment.',
    ],
];
