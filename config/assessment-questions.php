<?php

/*
|--------------------------------------------------------------------------
| Treatment-Specific Assessment Questions
|--------------------------------------------------------------------------
|
| Keep it SHORT. Patients should complete this in under 2 minutes.
| Conditional logic means they only see what's relevant to them.
|
| show_if => ['question_id' => 'answer_value']
|   - Show ONLY if referenced question has that answer
|   - For checkboxes, checks if value is in the selected array
|
*/

return [


    // ─── WEIGHT LOSS ─────────────────────────────────────────────────────────
    'weight-loss' => [
        ['id' => 'current_weight', 'type' => 'number', 'label' => 'Current weight (kg)', 'required' => true, 'placeholder' => 'e.g. 95'],
        ['id' => 'height', 'type' => 'number', 'label' => 'Height (cm)', 'required' => true, 'placeholder' => 'e.g. 175'],
        ['id' => 'target_weight', 'type' => 'number', 'label' => 'Target weight (kg)', 'required' => true, 'placeholder' => 'e.g. 75'],
        ['id' => 'medical_conditions', 'type' => 'checkbox', 'label' => 'Do you have any of these?', 'required' => false, 'options' => ['Type 2 Diabetes', 'Thyroid disorder', 'PCOS', 'High blood pressure', 'Heart disease', 'None of the above']],
        ['id' => 'diabetes_meds', 'type' => 'text', 'label' => 'Which diabetes medication?', 'required' => true, 'placeholder' => 'e.g. Metformin 500mg', 'show_if' => ['medical_conditions' => 'Type 2 Diabetes']],
        ['id' => 'thyroid_meds', 'type' => 'text', 'label' => 'Which thyroid medication?', 'required' => true, 'placeholder' => 'e.g. Eltroxin 50mcg', 'show_if' => ['medical_conditions' => 'Thyroid disorder']],
        ['id' => 'bp_meds', 'type' => 'text', 'label' => 'Which blood pressure medication?', 'required' => true, 'placeholder' => 'e.g. Amlodipine 5mg', 'show_if' => ['medical_conditions' => 'High blood pressure']],
        ['id' => 'other_medications', 'type' => 'text', 'label' => 'Any other medications?', 'required' => false, 'placeholder' => 'List any other meds you take'],
        ['id' => 'smoker', 'type' => 'radio', 'label' => 'Do you smoke?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'smoking_per_day', 'type' => 'select', 'label' => 'How many per day?', 'required' => true, 'options' => ['Less than 5', '5-10', '10-20', 'More than 20'], 'show_if' => ['smoker' => 'Yes']],
        ['id' => 'alcohol', 'type' => 'radio', 'label' => 'Do you drink alcohol?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'alcohol_frequency', 'type' => 'select', 'label' => 'How often?', 'required' => true, 'options' => ['Social only', '1-2 times/week', '3-5 times/week', 'Daily'], 'show_if' => ['alcohol' => 'Yes']],
        ['id' => 'glp1_experience', 'type' => 'radio', 'label' => 'Have you used weight-loss medication before (Ozempic, Wegovy, etc.)?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'glp1_which', 'type' => 'text', 'label' => 'Which medication and dose?', 'required' => true, 'placeholder' => 'e.g. Ozempic 0.5mg', 'show_if' => ['glp1_experience' => 'Yes']],
        ['id' => 'glp1_result', 'type' => 'radio', 'label' => 'Did it work for you?', 'required' => true, 'options' => ['Yes — lost weight', 'Somewhat', 'No — stopped due to side effects'], 'show_if' => ['glp1_experience' => 'Yes']],
        ['id' => 'eating_habits', 'type' => 'select', 'label' => 'How would you describe your eating?', 'required' => true, 'options' => ['Healthy but big portions', 'Emotional/snack eater', 'Irregular meals', 'High carb/sugar', 'Other']],
    ],


    'health-coach-support' => [
        ['id' => 'goals', 'type' => 'textarea', 'label' => 'What are your health goals?', 'required' => true, 'placeholder' => 'Weight loss, better energy, managing a condition, etc.'],
        ['id' => 'dietary_restrictions', 'type' => 'text', 'label' => 'Dietary restrictions or food allergies?', 'required' => false, 'placeholder' => 'e.g. vegetarian, lactose intolerant, none'],
        ['id' => 'exercise', 'type' => 'radio', 'label' => 'Do you exercise regularly?', 'required' => true, 'options' => ['Yes', 'No', 'Sometimes']],
        ['id' => 'exercise_type', 'type' => 'text', 'label' => 'What type and how often?', 'required' => true, 'placeholder' => 'e.g. Gym 3x/week, walking daily', 'show_if' => ['exercise' => 'Yes']],
    ],

    // ─── SKINCARE ────────────────────────────────────────────────────────────
    'acne-treatment' => [
        ['id' => 'acne_duration', 'type' => 'select', 'label' => 'How long have you had acne?', 'required' => true, 'options' => ['Less than 6 months', '6-12 months', '1-3 years', 'More than 3 years']],
        ['id' => 'acne_location', 'type' => 'checkbox', 'label' => 'Where is it?', 'required' => true, 'options' => ['Face', 'Chest', 'Back', 'Shoulders']],
        ['id' => 'acne_severity', 'type' => 'radio', 'label' => 'Severity?', 'required' => true, 'options' => ['Mild (few pimples)', 'Moderate (frequent breakouts)', 'Severe (cysts/scarring)']],
        ['id' => 'previous_treatment', 'type' => 'radio', 'label' => 'Tried any treatment before?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'previous_treatment_details', 'type' => 'text', 'label' => 'What did you use?', 'required' => true, 'placeholder' => 'e.g. Roaccutane, benzoyl peroxide cream', 'show_if' => ['previous_treatment' => 'Yes']],
        ['id' => 'previous_treatment_result', 'type' => 'radio', 'label' => 'Did it help?', 'required' => true, 'options' => ['Yes', 'Somewhat', 'No'], 'show_if' => ['previous_treatment' => 'Yes']],
        ['id' => 'pregnant', 'type' => 'radio', 'label' => 'Are you pregnant or planning pregnancy?', 'required' => true, 'options' => ['Yes', 'No', 'Not applicable']],
        ['id' => 'photos', 'type' => 'file', 'label' => 'Upload a photo (optional, helps the doctor)', 'required' => false, 'accept' => 'image/*', 'multiple' => true],
    ],

    'anti-aging-treatment' => [
        ['id' => 'concerns', 'type' => 'checkbox', 'label' => 'Main concerns?', 'required' => true, 'options' => ['Fine lines/wrinkles', 'Sun damage', 'Uneven skin tone', 'Loss of firmness', 'Dark spots', 'Dull skin']],
        ['id' => 'retinoid_experience', 'type' => 'radio', 'label' => 'Used retinoids/retinol before?', 'required' => true, 'options' => ['Yes, currently', 'Yes, in the past', 'No']],
        ['id' => 'retinoid_which', 'type' => 'text', 'label' => 'Which product?', 'required' => false, 'placeholder' => 'e.g. Tretinoin 0.05%, The Ordinary Retinol', 'show_if' => ['retinoid_experience' => 'Yes, currently']],
        ['id' => 'skin_sensitivity', 'type' => 'radio', 'label' => 'Skin sensitivity?', 'required' => true, 'options' => ['Not sensitive', 'Somewhat sensitive', 'Very sensitive']],
    ],


    'rosacea-treatment' => [
        ['id' => 'symptoms', 'type' => 'checkbox', 'label' => 'Symptoms?', 'required' => true, 'options' => ['Facial redness', 'Bumps/pimples', 'Visible blood vessels', 'Burning/stinging', 'Dry/flaky skin', 'Eye irritation']],
        ['id' => 'duration', 'type' => 'select', 'label' => 'How long?', 'required' => true, 'options' => ['Less than 6 months', '6-12 months', '1-3 years', 'More than 3 years']],
        ['id' => 'triggers', 'type' => 'checkbox', 'label' => 'Known triggers?', 'required' => false, 'options' => ['Sun', 'Spicy food', 'Alcohol', 'Hot drinks', 'Stress', 'Exercise', 'Unsure']],
        ['id' => 'previous_treatment', 'type' => 'radio', 'label' => 'Tried treatment before?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'previous_treatment_what', 'type' => 'text', 'label' => 'What did you use?', 'required' => true, 'placeholder' => 'e.g. metronidazole cream, azelaic acid', 'show_if' => ['previous_treatment' => 'Yes']],
    ],

    'other-skincare' => [
        ['id' => 'concern', 'type' => 'text', 'label' => 'Main skin concern?', 'required' => true, 'placeholder' => 'e.g. hyperpigmentation, eczema, melasma'],
        ['id' => 'duration', 'type' => 'select', 'label' => 'How long?', 'required' => true, 'options' => ['Less than 1 month', '1-6 months', '6-12 months', 'More than a year']],
        ['id' => 'previous_treatment', 'type' => 'radio', 'label' => 'Tried anything?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'previous_treatment_what', 'type' => 'text', 'label' => 'What?', 'required' => true, 'placeholder' => 'Products or prescriptions', 'show_if' => ['previous_treatment' => 'Yes']],
        ['id' => 'photos', 'type' => 'file', 'label' => 'Upload photo (optional)', 'required' => false, 'accept' => 'image/*', 'multiple' => true],
    ],

    // ─── WOMEN'S HEALTH ──────────────────────────────────────────────────────
    'bacterial-vaginosis-treatment' => [
        ['id' => 'symptoms', 'type' => 'checkbox', 'label' => 'Symptoms?', 'required' => true, 'options' => ['Unusual discharge', 'Fishy odour', 'Itching', 'Burning during urination']],
        ['id' => 'duration', 'type' => 'select', 'label' => 'How long?', 'required' => true, 'options' => ['A few days', '1-2 weeks', 'More than 2 weeks']],
        ['id' => 'recurring', 'type' => 'radio', 'label' => 'Has this happened before?', 'required' => true, 'options' => ['Yes — keeps coming back', 'Once before', 'First time']],
        ['id' => 'recurring_treatment', 'type' => 'text', 'label' => 'What was used last time?', 'required' => true, 'placeholder' => 'e.g. Flagyl, metronidazole', 'show_if' => ['recurring' => 'Yes — keeps coming back']],
        ['id' => 'pregnant', 'type' => 'radio', 'label' => 'Are you pregnant?', 'required' => true, 'options' => ['Yes', 'No', 'Unsure']],
    ],


    'birth-control' => [
        ['id' => 'reason', 'type' => 'select', 'label' => 'Why are you seeking birth control?', 'required' => true, 'options' => ['New to contraception', 'Switching methods', 'Repeat prescription', 'Managing periods/PMS', 'Other']],
        ['id' => 'current_method', 'type' => 'text', 'label' => 'Current method?', 'required' => true, 'placeholder' => 'e.g. the pill, Mirena, condoms', 'show_if' => ['reason' => 'Switching methods']],
        ['id' => 'switching_why', 'type' => 'text', 'label' => 'Why switching?', 'required' => true, 'placeholder' => 'Side effects, convenience, etc.', 'show_if' => ['reason' => 'Switching methods']],
        ['id' => 'repeat_which', 'type' => 'text', 'label' => 'Which prescription do you need repeated?', 'required' => true, 'placeholder' => 'e.g. Yasmin, NuvaRing', 'show_if' => ['reason' => 'Repeat prescription']],
        ['id' => 'medical_history', 'type' => 'checkbox', 'label' => 'Any of these conditions?', 'required' => false, 'options' => ['Migraines with aura', 'Blood clots (you or family)', 'High blood pressure', 'Breast cancer history', 'None']],
        ['id' => 'smoker', 'type' => 'radio', 'label' => 'Do you smoke?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'smoker_age', 'type' => 'radio', 'label' => 'Are you over 35?', 'required' => true, 'options' => ['Yes', 'No'], 'show_if' => ['smoker' => 'Yes']],
    ],

    'menopause-management' => [
        ['id' => 'symptoms', 'type' => 'checkbox', 'label' => 'Symptoms?', 'required' => true, 'options' => ['Hot flushes', 'Night sweats', 'Sleep problems', 'Mood changes', 'Vaginal dryness', 'Low libido', 'Brain fog', 'Joint pain']],
        ['id' => 'severity', 'type' => 'radio', 'label' => 'How bad is it?', 'required' => true, 'options' => ['Mild', 'Moderate', 'Severe — affecting daily life']],
        ['id' => 'last_period', 'type' => 'select', 'label' => 'Last period?', 'required' => true, 'options' => ['Still having periods (irregular)', '3-6 months ago', '6-12 months ago', 'More than a year ago']],
        ['id' => 'hrt_experience', 'type' => 'radio', 'label' => 'Tried HRT before?', 'required' => true, 'options' => ['Yes, currently on it', 'Yes, stopped', 'No']],
        ['id' => 'hrt_why_stopped', 'type' => 'text', 'label' => 'Why did you stop?', 'required' => true, 'placeholder' => 'Side effects, doctor advised, etc.', 'show_if' => ['hrt_experience' => 'Yes, stopped']],
    ],

    'other-womens-health' => [
        ['id' => 'concern', 'type' => 'textarea', 'label' => 'What\'s going on?', 'required' => true, 'placeholder' => 'Describe briefly — the doctor will ask more during consultation.'],
        ['id' => 'pregnant', 'type' => 'radio', 'label' => 'Pregnant or could be?', 'required' => true, 'options' => ['Yes', 'No', 'Unsure']],
    ],


    // ─── MEN'S HEALTH ────────────────────────────────────────────────────────
    'erectile-dysfunction-treatment' => [
        ['id' => 'duration', 'type' => 'select', 'label' => 'How long has this been happening?', 'required' => true, 'options' => ['Less than 3 months', '3-6 months', '6-12 months', 'More than a year']],
        ['id' => 'frequency', 'type' => 'radio', 'label' => 'How often?', 'required' => true, 'options' => ['Every time', 'Most of the time', 'Sometimes', 'Rarely']],
        ['id' => 'morning_erections', 'type' => 'radio', 'label' => 'Still get morning erections?', 'required' => true, 'options' => ['Yes', 'Sometimes', 'Rarely/never']],
        ['id' => 'conditions', 'type' => 'checkbox', 'label' => 'Any of these?', 'required' => false, 'options' => ['Heart disease', 'High blood pressure', 'Diabetes', 'High cholesterol', 'None']],
        ['id' => 'bp_meds', 'type' => 'text', 'label' => 'BP medication name?', 'required' => true, 'placeholder' => 'e.g. Amlodipine, Enalapril', 'show_if' => ['conditions' => 'High blood pressure']],
        ['id' => 'nitrates', 'type' => 'radio', 'label' => 'Do you use nitrate medication (GTN spray, isosorbide)?', 'required' => true, 'options' => ['Yes', 'No', 'Unsure']],
        ['id' => 'tried_ed_meds', 'type' => 'radio', 'label' => 'Tried ED medication before?', 'required' => true, 'options' => ['Yes — worked', 'Yes — didn\'t work', 'No']],
        ['id' => 'ed_med_which', 'type' => 'text', 'label' => 'Which one and what dose?', 'required' => true, 'placeholder' => 'e.g. Sildenafil 50mg', 'show_if' => ['tried_ed_meds' => 'Yes — worked']],
        ['id' => 'ed_med_failed', 'type' => 'text', 'label' => 'Which one failed and at what dose?', 'required' => true, 'placeholder' => 'e.g. Viagra 50mg — no effect', 'show_if' => ['tried_ed_meds' => 'Yes — didn\'t work']],
        ['id' => 'smoker', 'type' => 'radio', 'label' => 'Smoker?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'smoking_amount', 'type' => 'select', 'label' => 'How many/day?', 'required' => true, 'options' => ['Less than 5', '5-10', '10-20', '20+'], 'show_if' => ['smoker' => 'Yes']],
    ],

    'premature-ejaculation-treatment' => [
        ['id' => 'pe_type', 'type' => 'radio', 'label' => 'Has this always been an issue or did it start recently?', 'required' => true, 'options' => ['Always (lifelong)', 'Started recently', 'Varies']],
        ['id' => 'pe_trigger', 'type' => 'text', 'label' => 'What triggered it?', 'required' => true, 'placeholder' => 'New partner, stress, medication change, etc.', 'show_if' => ['pe_type' => 'Started recently']],
        ['id' => 'timing', 'type' => 'select', 'label' => 'Average time?', 'required' => true, 'options' => ['Under 1 minute', '1-2 minutes', '2-5 minutes', 'Varies a lot']],
        ['id' => 'distress', 'type' => 'radio', 'label' => 'How much does this bother you?', 'required' => true, 'options' => ['A little', 'Moderately', 'A lot']],
        ['id' => 'tried_treatment', 'type' => 'radio', 'label' => 'Tried anything for it?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'treatment_what', 'type' => 'text', 'label' => 'What did you try?', 'required' => true, 'placeholder' => 'Sprays, techniques, pills, etc.', 'show_if' => ['tried_treatment' => 'Yes']],
        ['id' => 'also_ed', 'type' => 'radio', 'label' => 'Do you also have trouble getting/keeping an erection?', 'required' => true, 'options' => ['Yes', 'No', 'Sometimes']],
    ],

    'other-mens-health' => [
        ['id' => 'concern', 'type' => 'textarea', 'label' => 'What\'s going on?', 'required' => true, 'placeholder' => 'Describe briefly — the doctor will ask more.'],
        ['id' => 'duration', 'type' => 'text', 'label' => 'How long?', 'required' => true, 'placeholder' => 'e.g. 2 weeks, 3 months'],
    ],


    // ─── SEXUAL HEALTH ───────────────────────────────────────────────────────
    'genital-herpes-101' => [
        ['id' => 'status', 'type' => 'radio', 'label' => 'Your situation?', 'required' => true, 'options' => ['Currently having an outbreak', 'Want to prevent outbreaks', 'Think I have it (not diagnosed)']],
        ['id' => 'outbreak_frequency', 'type' => 'select', 'label' => 'How often do you get outbreaks?', 'required' => true, 'options' => ['Monthly', 'Every few months', 'Once or twice a year', 'First time'], 'show_if' => ['status' => 'Want to prevent outbreaks']],
        ['id' => 'current_treatment', 'type' => 'radio', 'label' => 'On antiviral treatment?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'which_antiviral', 'type' => 'text', 'label' => 'Which one?', 'required' => true, 'placeholder' => 'e.g. Valacyclovir 500mg', 'show_if' => ['current_treatment' => 'Yes']],
    ],

    'genital-warts-treatment' => [
        ['id' => 'status', 'type' => 'radio', 'label' => 'Have you been diagnosed?', 'required' => true, 'options' => ['Yes, by a doctor', 'No — I think I have them', 'They came back after treatment']],
        ['id' => 'previous_treatment_type', 'type' => 'text', 'label' => 'What treatment was used?', 'required' => true, 'placeholder' => 'e.g. cryotherapy, Aldara cream', 'show_if' => ['status' => 'They came back after treatment']],
        ['id' => 'location', 'type' => 'text', 'label' => 'Where are they?', 'required' => true, 'placeholder' => 'General area'],
    ],

    'sti-treatment' => [
        ['id' => 'reason', 'type' => 'radio', 'label' => 'Why are you here?', 'required' => true, 'options' => ['I have symptoms', 'No symptoms — want testing', 'Partner was diagnosed']],
        ['id' => 'symptoms', 'type' => 'checkbox', 'label' => 'Which symptoms?', 'required' => true, 'options' => ['Unusual discharge', 'Pain when urinating', 'Sores/blisters', 'Itching', 'Other'], 'show_if' => ['reason' => 'I have symptoms']],
        ['id' => 'exposure_when', 'type' => 'select', 'label' => 'When was potential exposure?', 'required' => true, 'options' => ['Within the last week', '1-2 weeks ago', '2-4 weeks ago', 'More than a month ago', 'Unsure']],
        ['id' => 'previous_sti', 'type' => 'radio', 'label' => 'Had an STI before?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'which_sti', 'type' => 'text', 'label' => 'Which one?', 'required' => false, 'placeholder' => 'e.g. chlamydia, gonorrhoea', 'show_if' => ['previous_sti' => 'Yes']],
    ],

    'other-sexual-health' => [
        ['id' => 'concern', 'type' => 'textarea', 'label' => 'What\'s your concern?', 'required' => true, 'placeholder' => '100% confidential. Describe what you\'re experiencing.'],
        ['id' => 'duration', 'type' => 'text', 'label' => 'How long?', 'required' => true, 'placeholder' => 'e.g. a few days, 2 weeks'],
    ],


    // ─── GENERAL HEALTH ──────────────────────────────────────────────────────
    'acid-reflux-treatment' => [
        ['id' => 'symptoms', 'type' => 'checkbox', 'label' => 'Symptoms?', 'required' => true, 'options' => ['Heartburn', 'Acid taste in mouth', 'Difficulty swallowing', 'Chest pain', 'Bloating', 'Nausea']],
        ['id' => 'frequency', 'type' => 'select', 'label' => 'How often?', 'required' => true, 'options' => ['Daily', 'Several times a week', 'Once a week', 'Occasionally']],
        ['id' => 'duration', 'type' => 'select', 'label' => 'How long?', 'required' => true, 'options' => ['Less than a month', '1-6 months', '6-12 months', 'More than a year']],
        ['id' => 'tried_medication', 'type' => 'radio', 'label' => 'Tried any medication for it?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'which_medication', 'type' => 'text', 'label' => 'What?', 'required' => true, 'placeholder' => 'e.g. Gaviscon, omeprazole, Nexium', 'show_if' => ['tried_medication' => 'Yes']],
        ['id' => 'medication_helped', 'type' => 'radio', 'label' => 'Did it help?', 'required' => true, 'options' => ['Yes', 'Somewhat', 'No'], 'show_if' => ['tried_medication' => 'Yes']],
    ],

    'cold-sores-treatment' => [
        ['id' => 'current_outbreak', 'type' => 'radio', 'label' => 'Do you have one right now?', 'required' => true, 'options' => ['Yes', 'No — want prevention']],
        ['id' => 'frequency', 'type' => 'select', 'label' => 'How often do you get them?', 'required' => true, 'options' => ['First time', 'Once or twice a year', 'Every few months', 'Monthly or more']],
        ['id' => 'tried_treatment', 'type' => 'radio', 'label' => 'Used treatment before?', 'required' => true, 'options' => ['Yes', 'No'], 'show_if' => ['current_outbreak' => 'No — want prevention']],
        ['id' => 'which_treatment', 'type' => 'text', 'label' => 'What?', 'required' => true, 'placeholder' => 'e.g. Zovirax cream, valacyclovir', 'show_if' => ['tried_treatment' => 'Yes']],
    ],

    'gp-consult' => [
        ['id' => 'reason', 'type' => 'textarea', 'label' => 'What do you need help with?', 'required' => true, 'placeholder' => 'Keep it brief — the doctor will ask more during the consultation.'],
        ['id' => 'duration', 'type' => 'text', 'label' => 'How long has this been going on?', 'required' => false, 'placeholder' => 'e.g. 3 days, 2 weeks, ongoing'],
        ['id' => 'need_sick_note', 'type' => 'radio', 'label' => 'Need a sick note?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'sick_note_days', 'type' => 'select', 'label' => 'How many days?', 'required' => true, 'options' => ['1 day', '2 days', '3 days', 'More than 3'], 'show_if' => ['need_sick_note' => 'Yes']],
        ['id' => 'need_prescription', 'type' => 'radio', 'label' => 'Need a repeat prescription?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'prescription_what', 'type' => 'text', 'label' => 'Which medication?', 'required' => true, 'placeholder' => 'e.g. chronic meds, contraception', 'show_if' => ['need_prescription' => 'Yes']],
    ],


    'haemorrhoids-treatment' => [
        ['id' => 'symptoms', 'type' => 'checkbox', 'label' => 'Symptoms?', 'required' => true, 'options' => ['Pain', 'Itching', 'Bleeding', 'Lump/swelling', 'Discomfort sitting']],
        ['id' => 'duration', 'type' => 'select', 'label' => 'How long?', 'required' => true, 'options' => ['A few days', '1-2 weeks', '2-4 weeks', 'More than a month']],
        ['id' => 'recurring', 'type' => 'radio', 'label' => 'Has this happened before?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'tried_treatment', 'type' => 'radio', 'label' => 'Tried any treatment?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'treatment_what', 'type' => 'text', 'label' => 'What?', 'required' => true, 'placeholder' => 'e.g. Anusol, Scheriproct', 'show_if' => ['tried_treatment' => 'Yes']],
    ],

    'hair-loss-treatment' => [
        ['id' => 'pattern', 'type' => 'select', 'label' => 'Where is it thinning?', 'required' => true, 'options' => ['Receding hairline', 'Crown/top', 'Overall thinning', 'Patches', 'Temples']],
        ['id' => 'duration', 'type' => 'select', 'label' => 'How long?', 'required' => true, 'options' => ['Less than 6 months', '6-12 months', '1-3 years', 'More than 3 years']],
        ['id' => 'family_history', 'type' => 'radio', 'label' => 'Family history of hair loss?', 'required' => true, 'options' => ['Yes', 'No', 'Unsure']],
        ['id' => 'tried_treatment', 'type' => 'radio', 'label' => 'Tried treatment?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'treatment_which', 'type' => 'checkbox', 'label' => 'Which?', 'required' => true, 'options' => ['Finasteride (Propecia)', 'Minoxidil (Regaine)', 'Supplements', 'Other'], 'show_if' => ['tried_treatment' => 'Yes']],
        ['id' => 'treatment_result', 'type' => 'radio', 'label' => 'Did it help?', 'required' => true, 'options' => ['Yes', 'Somewhat', 'No'], 'show_if' => ['tried_treatment' => 'Yes']],
    ],

    'uti-treatment' => [
        ['id' => 'symptoms', 'type' => 'checkbox', 'label' => 'Symptoms?', 'required' => true, 'options' => ['Burning when urinating', 'Frequent urination', 'Urgency', 'Cloudy/smelly urine', 'Blood in urine', 'Lower abdominal pain']],
        ['id' => 'fever', 'type' => 'radio', 'label' => 'Fever or back pain?', 'required' => true, 'options' => ['Yes', 'No']],
        ['id' => 'recurring', 'type' => 'radio', 'label' => 'Recurring UTIs?', 'required' => true, 'options' => ['Yes (3+ per year)', 'Happened before', 'First time']],
        ['id' => 'recurring_prevention', 'type' => 'radio', 'label' => 'Want to discuss prevention?', 'required' => true, 'options' => ['Yes', 'No, just treat this one'], 'show_if' => ['recurring' => 'Yes (3+ per year)']],
        ['id' => 'pregnant', 'type' => 'radio', 'label' => 'Pregnant?', 'required' => true, 'options' => ['Yes', 'No', 'Not applicable']],
    ],

    'thrush-treatment' => [
        ['id' => 'type', 'type' => 'radio', 'label' => 'Type?', 'required' => true, 'options' => ['Vaginal', 'Oral', 'Penile', 'Unsure']],
        ['id' => 'symptoms', 'type' => 'checkbox', 'label' => 'Symptoms?', 'required' => true, 'options' => ['Itching', 'Discharge', 'Redness/swelling', 'Pain', 'White patches (oral)']],
        ['id' => 'recurring', 'type' => 'radio', 'label' => 'Is this recurring?', 'required' => true, 'options' => ['Yes (4+ per year)', 'Happened before', 'First time']],
        ['id' => 'recurring_treatment', 'type' => 'text', 'label' => 'What\'s been used before?', 'required' => true, 'placeholder' => 'e.g. Canesten, fluconazole', 'show_if' => ['recurring' => 'Yes (4+ per year)']],
        ['id' => 'pregnant', 'type' => 'radio', 'label' => 'Pregnant?', 'required' => true, 'options' => ['Yes', 'No', 'Not applicable']],
    ],

];
