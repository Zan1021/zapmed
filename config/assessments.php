<?php

return [

    'erectile-dysfunction' => [
        [
            'id' => 'ed_duration',
            'text' => 'How long have you experienced erectile dysfunction?',
            'type' => 'select',
            'options' => ['Less than 3 months', '3-6 months', '6-12 months', 'Over 1 year'],
            'required' => true,
        ],
        [
            'id' => 'ed_severity',
            'text' => 'How would you rate the severity?',
            'type' => 'radio',
            'options' => ['Mild', 'Moderate', 'Severe'],
            'required' => true,
        ],
        [
            'id' => 'ed_tried_before',
            'text' => 'Have you tried ED medication before?',
            'type' => 'radio',
            'options' => ['Yes', 'No'],
            'required' => true,
        ],
        [
            'id' => 'ed_which_medication',
            'text' => 'If yes, which medication?',
            'type' => 'text',
            'options' => [],
            'required' => false,
        ],
        [
            'id' => 'ed_conditions',
            'text' => 'Do you have any of the following conditions?',
            'type' => 'checkbox',
            'options' => ['Diabetes', 'Hypertension', 'Heart disease', 'High cholesterol', 'None'],
            'required' => true,
        ],
        [
            'id' => 'ed_medications',
            'text' => 'Are you currently taking any medications?',
            'type' => 'textarea',
            'options' => [],
            'required' => true,
        ],
        [
            'id' => 'ed_smoking',
            'text' => 'Do you smoke?',
            'type' => 'radio',
            'options' => ['Yes', 'No', 'Occasionally'],
            'required' => true,
        ],
        [
            'id' => 'ed_alcohol',
            'text' => 'How often do you consume alcohol?',
            'type' => 'select',
            'options' => ['Never', 'Occasionally', 'Regularly', 'Daily'],
            'required' => true,
        ],
    ],

    'weight-loss' => [
        [
            'id' => 'wl_current_weight',
            'text' => 'What is your current weight in kg?',
            'type' => 'text',
            'options' => [],
            'required' => true,
        ],
        [
            'id' => 'wl_height',
            'text' => 'What is your height in cm?',
            'type' => 'text',
            'options' => [],
            'required' => true,
        ],
        [
            'id' => 'wl_goal_weight',
            'text' => 'What is your goal weight in kg?',
            'type' => 'text',
            'options' => [],
            'required' => true,
        ],
        [
            'id' => 'wl_tried_before',
            'text' => 'Have you tried prescription weight loss medication before?',
            'type' => 'radio',
            'options' => ['Yes', 'No'],
            'required' => true,
        ],
        [
            'id' => 'wl_which_medication',
            'text' => 'If yes, which medication?',
            'type' => 'text',
            'options' => [],
            'required' => false,
        ],
        [
            'id' => 'wl_conditions',
            'text' => 'Do you have any of these conditions?',
            'type' => 'checkbox',
            'options' => ['Diabetes', 'Thyroid disorder', 'Heart disease', 'Kidney disease', 'None'],
            'required' => true,
        ],
        [
            'id' => 'wl_pregnant',
            'text' => 'Are you currently pregnant or breastfeeding?',
            'type' => 'radio',
            'options' => ['Yes', 'No', 'Not applicable'],
            'required' => true,
        ],
        [
            'id' => 'wl_medications',
            'text' => 'Current medications?',
            'type' => 'textarea',
            'options' => [],
            'required' => true,
        ],
    ],

    'birth-control' => [
        [
            'id' => 'bc_type',
            'text' => 'What type of contraception are you looking for?',
            'type' => 'select',
            'options' => ['Combined pill', 'Mini pill', 'Not sure'],
            'required' => true,
        ],
        [
            'id' => 'bc_used_before',
            'text' => 'Have you used hormonal contraception before?',
            'type' => 'radio',
            'options' => ['Yes', 'No'],
            'required' => true,
        ],
        [
            'id' => 'bc_smoking',
            'text' => 'Do you smoke?',
            'type' => 'radio',
            'options' => ['Yes', 'No'],
            'required' => true,
        ],
        [
            'id' => 'bc_over_35',
            'text' => 'Are you over 35?',
            'type' => 'radio',
            'options' => ['Yes', 'No'],
            'required' => true,
        ],
        [
            'id' => 'bc_blood_clots',
            'text' => 'Do you have a history of blood clots?',
            'type' => 'radio',
            'options' => ['Yes', 'No', 'Not sure'],
            'required' => true,
        ],
        [
            'id' => 'bc_medications',
            'text' => 'Current medications?',
            'type' => 'textarea',
            'options' => [],
            'required' => true,
        ],
        [
            'id' => 'bc_allergies',
            'text' => 'Any allergies?',
            'type' => 'textarea',
            'options' => [],
            'required' => true,
        ],
    ],

    'gp-consult' => [
        [
            'id' => 'gp_reason',
            'text' => 'What is the reason for your consultation?',
            'type' => 'textarea',
            'options' => [],
            'required' => true,
        ],
        [
            'id' => 'gp_duration',
            'text' => 'How long have you had this concern?',
            'type' => 'select',
            'options' => ['Today', 'A few days', '1-2 weeks', 'Over 2 weeks', 'Ongoing'],
            'required' => true,
        ],
        [
            'id' => 'gp_medications',
            'text' => 'Are you currently on any medication?',
            'type' => 'textarea',
            'options' => [],
            'required' => true,
        ],
        [
            'id' => 'gp_allergies',
            'text' => 'Do you have any known allergies?',
            'type' => 'textarea',
            'options' => [],
            'required' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Questionnaire
    |--------------------------------------------------------------------------
    |
    | Used for any treatment slug not explicitly defined above.
    |
    */
    'default' => [
        [
            'id' => 'default_treatment',
            'text' => 'What treatment are you seeking?',
            'type' => 'text',
            'options' => [],
            'required' => true,
            'prefill' => 'treatment_name',
        ],
        [
            'id' => 'default_duration',
            'text' => 'How long have you had this concern?',
            'type' => 'select',
            'options' => ['Less than a week', '1-2 weeks', '2-4 weeks', '1-3 months', 'Over 3 months'],
            'required' => true,
        ],
        [
            'id' => 'default_treated_before',
            'text' => 'Have you received treatment for this before?',
            'type' => 'radio',
            'options' => ['Yes', 'No'],
            'required' => true,
        ],
        [
            'id' => 'default_previous_treatment',
            'text' => 'If yes, please describe previous treatment',
            'type' => 'textarea',
            'options' => [],
            'required' => false,
        ],
        [
            'id' => 'default_medications',
            'text' => 'Current medications?',
            'type' => 'textarea',
            'options' => [],
            'required' => true,
        ],
        [
            'id' => 'default_allergies',
            'text' => 'Known allergies?',
            'type' => 'textarea',
            'options' => [],
            'required' => true,
        ],
    ],

];
