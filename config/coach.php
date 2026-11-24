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

    /*
     * Buying the add-on. A doctor can attach it at a consultation: that creates an unpaid (pending) membership and
     * the member pays on /coach/join through PayFast. Nothing is switched on until the first payment clears.
     * The message telling the member about the doctor's offer is OFF until COACH_ADDON_NOTIFY=true.
     */
    'addon' => [
        'notify_enabled' => env('COACH_ADDON_NOTIFY', false),
    ],

    /*
     * Auto-assigning a coach (a registered dietitian with the health_coach role) when the add-on starts. The member
     * goes to the active coach with the fewest members. max_members 0 = no limit; set a number once capacity is
     * agreed with Craig. If nobody has room the member waits, and the hourly coach:assign-pending retries.
     */
    'assignment' => [
        'max_members' => (int) env('COACH_MAX_MEMBERS_PER_COACH', 0),
    ],

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
        'exact_min_ml' => 10,
        'exact_max_ml' => 3000,
        'luteal_tip' => 'In your luteal phase, drinking enough water helps with water retention.',
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
        /* PROVISIONAL one-sentence effects for the four cards and Amy's tip per phase. No medicine names, no dose advice. */
        'phase_text' => [
            'menstrual' => [
                'weight' => 'Water weight can be a little higher. It is not fat gain.',
                'hunger' => 'Appetite may be higher.',
                'water' => 'Keep sipping through the day.',
                'mood' => 'Mood can dip. Be kind to yourself.',
            ],
            'follicular' => [
                'weight' => 'Weight is often steadier or a little lower.',
                'hunger' => 'Appetite is usually lower.',
                'water' => 'Your usual amount works well.',
                'mood' => 'Energy and mood often lift.',
            ],
            'ovulation' => [
                'weight' => 'Weight is usually steady.',
                'hunger' => 'Appetite is often lower.',
                'water' => 'Your usual amount works well.',
                'mood' => 'Mood and energy are often good.',
            ],
            'luteal' => [
                'weight' => 'Water retention can push the scale up. It is temporary.',
                'hunger' => 'Cravings and appetite can rise.',
                'water' => 'Aim for your full goal. It can ease bloating.',
                'mood' => 'Mood can dip before your period.',
            ],
        ],
        'tips' => [
            'menstrual' => 'Go gently. A walk and iron-rich meals can help you feel better. Weigh in as usual but read the scale with the cycle in mind.',
            'follicular' => 'A good time to try something new. Plan your protein early in the day and keep your water up.',
            'ovulation' => 'Energy is often highest now. Keep your meals regular and your protein steady.',
            'luteal' => 'Cravings are normal now. Plan a protein snack, keep your water up, and do not judge the scale this week.',
        ],
        'phase_colours' => ['menstrual' => '#E7B7B0', 'follicular' => '#DCE5DA', 'ovulation' => '#9CC3C7', 'luteal' => '#729D94'],
        'min_period' => 2,
        'max_period' => 10,
        'moods' => [1 => ['label' => 'Very low', 'emoji' => '😞'], 2 => ['label' => 'Low', 'emoji' => '🙁'], 3 => ['label' => 'Okay', 'emoji' => '😐'], 4 => ['label' => 'Good', 'emoji' => '🙂'], 5 => ['label' => 'Great', 'emoji' => '😄']],
        'symptoms' => ['cramps' => 'Cramps', 'bloating' => 'Bloating', 'cravings' => 'Cravings', 'headache' => 'Headache', 'tender' => 'Tender', 'low_mood' => 'Low mood', 'poor_sleep' => 'Poor sleep'],
        'notice' => 'These are estimates. Cycles vary. Tell your doctor if your cycle changes a lot while you are on treatment.',
        'phase_labels' => [
            'menstrual' => 'Period', 'follicular' => 'Follicular phase',
            'ovulation' => 'Ovulation', 'luteal' => 'Luteal phase',
        ],
    ],

    /* Reminder types and their defaults. Copy never names the medication or any health detail. */
    'reminders' => [
        'hydration' => ['enabled' => true, 'every_hours' => 2, 'from' => '08:00', 'until' => '20:00', 'smart_skip' => true, 'channel' => 'whatsapp'],
        'weigh_in' => ['enabled' => true, 'day' => 'monday', 'frequency' => 'weekly', 'time' => '08:00'],
        'dose_day' => ['enabled' => true, 'time' => '20:00'],
        'meals' => ['enabled' => true],
        'cycle' => ['enabled' => true],
    ],

    /* What a member can pick (build guide 5.7). Push is not built; WhatsApp falls back to email until Meta is set up. */
    'reminder_options' => [
        'every_hours' => [1, 2, 3],
        'from' => ['06:00', '07:00', '08:00', '09:00', '10:00'],
        'until' => ['18:00', '19:00', '20:00', '21:00', '22:00'],
        'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        'frequencies' => ['weekly', 'daily'],
        'dose_times' => ['08:00', '12:00', '18:00', '20:00'],
        'channels' => ['whatsapp' => 'WhatsApp (email if it cannot be sent)', 'email' => 'Email', 'sms' => 'SMS'],
    ],

    /*
     * Sending. MASTER SWITCH is off until Captain Zan turns it on (COACH_REMINDERS_SEND=true), so nobody is messaged
     * by accident while the app is being tested. Meal nudges stay off until meal logging exists.
     */
    'reminder_sending' => [
        'enabled' => env('COACH_REMINDERS_SEND', false),
        'window_minutes' => 10,          // a slot is sent if the scheduler sees it within this many minutes after it
        'meal_times' => ['12:30', '18:30'],
        'meals_enabled' => false,
        'cycle_time' => '09:00',
        'cycle_days_before_period' => 2,
    ],

    /* Reminder wording. Never names the medication, carries no health detail (build guide section 8). PROVISIONAL. */
    'reminder_text' => [
        'subject' => 'Zapmed reminder',
        'hydration' => 'Time for a glass of water.',
        'weigh_in' => 'Weigh-in day. Log your weight in the app.',
        'dose_day' => 'Zapmed: you have a reminder waiting in the app.',
        'meals' => 'Time to log your meal.',
        'cycle' => 'Zapmed: you have a heads-up waiting in the app.',
    ],

    /*
     * Messaging between a member and their coach (in-app thread). Hours are the coach's published reply hours,
     * in South African time; "online" on screen means inside these hours (PROVISIONAL, not live presence).
     * Notifications about new messages are OFF until COACH_MESSAGE_NOTIFY=true; they never carry the message text.
     */
    'messaging' => [
        'coach_name' => 'Amy',
        'hours' => ['days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], 'from' => '08:00', 'until' => '17:00'],
        'max_length' => 1000,
        'per_hour_limit' => 20,
        'notify_enabled' => env('COACH_MESSAGE_NOTIFY', false),
        'thread_limit' => 100,
        'online_text' => 'Online now',
        'offline_text' => 'Replies within one working day',
        'urgent_text' => "Messages are answered on weekdays from 08:00 to 17:00. If something feels wrong, use \"Something feels wrong\" in the coach menu.",
        'unassigned_text' => 'Your coach will be assigned soon. If something feels wrong, use "Something feels wrong" in the coach menu.',
    ],

    /* Call booking with the coach. */
    /*
     * Calls with the coach (build guide 5.15). Slots start on the half hour inside the coach's call hours and skip the
     * breaks. "days_ahead" counts working days from today. A member holds one upcoming call at a time.
     * Confirmation and reminder messages stay OFF until COACH_CALL_NOTIFY=true.
     */
    'calls' => [
        'lengths_minutes' => [15, 30],
        'days_ahead' => 5,
        'step_minutes' => 30,
        'min_notice_hours' => 2,
        'hours' => ['days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], 'from' => '08:30', 'until' => '16:30'],
        'breaks' => [['12:00', '13:00']],
        'reminder_minutes_before' => 60,
        'notify_enabled' => env('COACH_CALL_NOTIFY', false),
        'purposes' => ['welcome', 'check_in', 'maintenance', 'support'],
        'fully_booked_note' => 'Greyed times are already taken. If nothing suits, send a message and your coach will find a time.',
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

    /*
     * Profile (design screen 22) choices. Lists are fixed so saved values are always known keys.
     * PROVISIONAL until Amy / Dr Anke review. The diet and allergy lists feed the meal plan later.
     */
    'profile' => [
        'target_months' => [3, 6, 9, 12],
        'height_cm' => [100, 230],
        'health_concerns' => [
            'pcos' => 'PCOS',
            'type2_diabetes' => 'Type 2 diabetes',
            'high_blood_pressure' => 'High blood pressure',
            'thyroid' => 'Thyroid condition',
        ],
        'diets' => [
            'everything' => 'I eat everything',
            'vegetarian' => 'Vegetarian',
            'vegan' => 'Vegan',
            'pescatarian' => 'Pescatarian',
            'halal' => 'Halal',
            'low_carb' => 'Low carb',
        ],
        'allergies' => [
            'gluten' => 'Gluten', 'dairy' => 'Dairy', 'eggs' => 'Eggs', 'nuts' => 'Nuts',
            'peanuts' => 'Peanuts', 'soy' => 'Soy', 'fish' => 'Fish', 'shellfish' => 'Shellfish',
        ],
        'avoids' => [
            'red_meat' => 'Red meat', 'pork' => 'Pork', 'chicken' => 'Chicken', 'fish' => 'Fish',
            'dairy' => 'Dairy', 'eggs' => 'Eggs', 'spicy' => 'Spicy food', 'sugar' => 'Added sugar',
        ],
        'activity_levels' => [
            'sedentary' => 'Sedentary',
            'light' => 'Lightly active',
            'moderate' => 'Moderately active',
            'active' => 'Active',
            'very_active' => 'Very active',
        ],
    ],

    /* Staff board (coach portal + doctor summary). */
    'board' => [
        'quiet_days' => 7,            // no weigh-in for this many days = "quiet" badge on the coach board
        'history_count' => 8,
        'side_effect_days' => 30,
        'note_max_length' => 2000,
    ],

    /* Milestone (design screen 25). PROVISIONAL wording; {coach} is the coach's first name. */
    'milestone' => [
        'note' => "I'm proud of you. Every log you make is part of this. Keep going, and tell me if anything gets in the way.",
        'empty' => 'Your first milestone is on its way. Log your weight and keep going. Every entry counts.',
    ],

    /*
     * Recipes (design screen 16). Members only ever see recipes a dietitian has approved. Serving stepper 1 to 4.
     */
    'recipes' => [
        'servings_min' => 1,
        'servings_max' => 4,
        'slots' => ['breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner', 'snack' => 'Snack'],
        'diet_tags' => ['vegetarian' => 'Vegetarian', 'vegan' => 'Vegan', 'pescatarian' => 'Pescatarian', 'halal' => 'Halal'],
        'photo_disk' => env('COACH_RECIPE_DISK', 'public'),   // where recipe photos are stored
        'photo_max_kb' => 2048,
        'demo_note' => 'Sample recipe. Real recipes and nutrition values are checked by our dietitian before launch.',
        'estimate_note' => 'Nutrition values are estimates.',
    ],

    /*
     * Meal plan (design screens 17 and 18, build guide 5.12). PROVISIONAL: the template limits and the "on target"
     * band are working numbers for Amy to set. Hard rules from the guide (diet, allergies, avoids, locks, no repeat of
     * yesterday's meal in a slot, protein boost) are in code and are NOT settings.
     *
     * Template limits (all optional):
     *   max_kcal    per slot, upper limit for one serving
     *   min_protein per slot, lower limit for one serving (protein in grams)
     *   max_carbs   per slot, upper limit for one serving (carbs in grams)
     * A limit is dropped for a slot when it would leave nothing to choose from; diet, allergy and avoid rules never are.
     */
    'meal_plan' => [
        'default_template' => 'treatment_friendly',
        'days' => 7,
        'new_meals_per_day' => [1, 2],         // non-favourite meals added each day (breakfast, lunch or dinner)
        'new_meal_slots' => ['breakfast', 'lunch', 'dinner'],
        'low_carb_diet_max_carbs_g' => 28,     // the "Low carb" diet uses the same limit as the Low Carb template
        'on_target' => [
            'kcal_min_pct' => 85, 'kcal_max_pct' => 110, 'protein_min_pct' => 90,
        ],
        'templates' => [
            'treatment_friendly' => [
                'label' => 'Treatment Friendly',
                'blurb' => 'Gentle, smaller portions with steady protein.',
                'limits' => [
                    'breakfast' => ['max_kcal' => 350], 'lunch' => ['max_kcal' => 430],
                    'dinner' => ['max_kcal' => 450], 'snack' => ['max_kcal' => 200],
                ],
            ],
            'balanced' => [
                'label' => 'Balanced',
                'blurb' => 'An even mix of carbs, protein and fat.',
                'limits' => [],
            ],
            'high_protein' => [
                'label' => 'High Protein',
                'blurb' => 'Lunch and dinner have 30 g or more protein.',
                'limits' => ['lunch' => ['min_protein' => 30], 'dinner' => ['min_protein' => 30]],
            ],
            'low_carb' => [
                'label' => 'Low Carb',
                'blurb' => 'Meals have 28 g carbs or less.',
                'limits' => [
                    'breakfast' => ['max_carbs' => 28], 'lunch' => ['max_carbs' => 28],
                    'dinner' => ['max_carbs' => 28], 'snack' => ['max_carbs' => 28],
                ],
            ],
        ],
    ],

    /*
     * Activity (design screens 12 and 13, build guide 5.8). Calories for a workout = MET x weight (kg) x hours, using
     * the member's latest logged weight (default_weight_kg when none). MET values are rounded figures from the public
     * Compendium of Physical Activities: PROVISIONAL, Amy to review. Steps are typed in by the member for now
     * (Apple Health / Google Fit sync needs a native app); calories from steps = steps x kcal_per_step.
     */
    'activity' => [
        'default_weight_kg' => 75,
        'kcal_per_step' => 0.034,
        'steps_max' => 100000,
        'minutes' => ['min' => 5, 'max' => 300, 'quick' => [15, 30, 45, 60]],
        'groups' => ['cardio' => 'Cardio', 'strength' => 'Strength', 'mind_body' => 'Mind and body', 'sports' => 'Sports'],
        'workouts' => [
            // Cardio (8)
            'walk' => ['name' => 'Walk', 'group' => 'cardio', 'met' => 3.5, 'emoji' => '🚶'],
            'brisk_walk' => ['name' => 'Brisk walk', 'group' => 'cardio', 'met' => 4.3, 'emoji' => '🚶'],
            'jog' => ['name' => 'Jog', 'group' => 'cardio', 'met' => 7.0, 'emoji' => '🏃'],
            'run' => ['name' => 'Run', 'group' => 'cardio', 'met' => 9.8, 'emoji' => '🏃'],
            'cycle' => ['name' => 'Cycling', 'group' => 'cardio', 'met' => 7.5, 'emoji' => '🚴'],
            'swim' => ['name' => 'Swimming', 'group' => 'cardio', 'met' => 6.0, 'emoji' => '🏊'],
            'elliptical' => ['name' => 'Elliptical', 'group' => 'cardio', 'met' => 5.0, 'emoji' => '🏋️'],
            'dance' => ['name' => 'Dance cardio', 'group' => 'cardio', 'met' => 5.5, 'emoji' => '💃'],
            // Strength (4)
            'weights' => ['name' => 'Weight training', 'group' => 'strength', 'met' => 3.5, 'emoji' => '🏋️'],
            'bodyweight' => ['name' => 'Bodyweight circuit', 'group' => 'strength', 'met' => 5.0, 'emoji' => '💪'],
            'bands' => ['name' => 'Resistance bands', 'group' => 'strength', 'met' => 3.5, 'emoji' => '💪'],
            'kettlebell' => ['name' => 'Kettlebell', 'group' => 'strength', 'met' => 6.0, 'emoji' => '🏋️'],
            // Mind and body (3)
            'yoga' => ['name' => 'Yoga', 'group' => 'mind_body', 'met' => 2.5, 'emoji' => '🧘'],
            'pilates' => ['name' => 'Pilates', 'group' => 'mind_body', 'met' => 3.0, 'emoji' => '🧘'],
            'stretch' => ['name' => 'Stretching', 'group' => 'mind_body', 'met' => 2.3, 'emoji' => '🤸'],
            // Sports (4)
            'tennis' => ['name' => 'Tennis', 'group' => 'sports', 'met' => 7.3, 'emoji' => '🎾'],
            'football' => ['name' => 'Football', 'group' => 'sports', 'met' => 7.0, 'emoji' => '⚽'],
            'netball' => ['name' => 'Netball', 'group' => 'sports', 'met' => 6.5, 'emoji' => '🏀'],
            'golf' => ['name' => 'Golf (walking)', 'group' => 'sports', 'met' => 4.3, 'emoji' => '⛳'],
        ],
    ],

    /*
     * Insights (design screen 21, build guide 5.13). Rule-based text written from the member's own data. PROVISIONAL:
     * Amy and the doctor review these rules and wording before real members. No medicine names, never a dose change.
     * Placeholders: {symptom} {when} {kg}.
     */
    'insights' => [
        'notify_enabled' => env('COACH_CHECKIN_NOTIFY', false),   // tell the coach by message when a member says "Worse"
        'recent_symptom_days' => 3,
        'weigh_in_days' => 7,
        'answers' => ['better' => 'Better', 'same' => 'Same', 'worse' => 'Worse', 'gone' => 'Gone'],
        'answer_replies' => [
            'better' => 'Good to hear. Keep doing what is working.',
            'same' => 'Thanks. Keep logging so we can spot any change.',
            'gone' => 'Great news. Keep your meals and water steady.',
        ],
        'worse_reply_with_coach' => 'Thanks for telling us. {coach} has been told and will follow up. If something feels wrong, use "Something feels wrong" in the Coach menu.',
        'worse_reply_no_coach' => 'Thanks for telling us. We have noted it. If something feels wrong, use "Something feels wrong" in the Coach menu.',
        'headlines' => [
            'tough_symptom' => ['You logged a hard symptom', 'This one needs a closer look. Message your coach, or use "Something feels wrong" in the Coach menu so a doctor can see it.'],
            'symptom_recent' => ['Side effects usually settle', 'You logged {symptom} {when}. These often ease as your body adjusts. Small meals, protein first and your water goal can help.'],
            'dose_day' => ['Today is your usual dose day', 'Log your dose once you have taken it so your plan stays accurate.'],
            'after_dose' => ['A few days after your dose', 'Appetite is often lower for a day or two. Protein first, then vegetables, and keep sipping water.'],
            'weigh_in' => ['Time for a weigh-in', 'It has been a while. One number a week is enough to see the trend.'],
            'steady' => ['You are on track', 'Keep logging. Small steady habits add up.'],
        ],
        'goals' => [
            'weigh' => ['Log your weight today', 'Log weight'],
            'meal' => ['Log your first meal today', 'Log a meal'],
            'water' => ['Have a glass of water', 'Add water'],
            'protein' => ['Add a protein snack', 'Log a snack'],
            'walk' => ['Take a short walk', 'Log activity'],
        ],
        'water_nudge_after_hour' => 14,   // local hour after which low water becomes the goal
        'headlines_weekly' => [
            'strong' => 'A strong week',
            'steady' => 'A steady week',
            'start' => 'Every log counts',
        ],
    ],
];
