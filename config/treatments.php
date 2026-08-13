<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Treatment Categories & Treatments
    |--------------------------------------------------------------------------
    |
    | Slugs match zapmed.co.za live URLs exactly for SEO preservation.
    | Route: /{slug} (root level, no /treatments/ prefix)
    |
    | Pricing:
    | - price_once_off: once-off consultation fee in cents (null = not available)
    | - price_monthly: monthly subscription fee in cents (null = not available)
    | - duration: consultation duration in minutes
    |
    */

    'weight-loss' => [
        'name' => 'Weight Loss',
        'description' => 'GLP-1 medically guided weight loss with Health Coach support',
        'image' => '/images/services/weight-loss.webp',
        'treatments' => [
            'weight-loss' => [
                'name' => 'Weight Loss',
                'price' => 'From R450/month',
                'price_once_off' => null,
                'price_monthly' => 45000,
                'duration' => 30,
                'tagline' => 'Doctor-guided GLP-1 weight loss with personalised coaching.',
                'description' => 'Medical weight-loss programme using clinically approved GLP-1 medication (such as semaglutide), prescribed by a licensed doctor after a private medical assessment. Includes ongoing health coaching from a registered dietitian.',
            ],
            'health-coach-support' => [
                'name' => 'Health Coach',
                'price' => 'Included with subscription',
                'price_once_off' => null,
                'price_monthly' => null,
                'duration' => 30,
                'tagline' => 'Dedicated dietitian support for your weight loss journey.',
                'description' => 'Work with a registered dietitian who checks in via WhatsApp — nutrition guidance, side-effect management, motivation, and real-life adjustments to help you stay on track.',
            ],
        ],
    ],

    'skincare' => [
        'name' => 'Skincare',
        'description' => 'Prescription skincare personalised to you',
        'image' => '/images/services/skincare-routine-cleansing-wipe-woman.webp',
        'treatments' => [
            'acne-treatment' => [
                'name' => 'Acne Treatment',
                'price' => 'From R220/month',
                'price_once_off' => 45000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Clear skin starts with the right prescription.',
                'description' => 'Personalised prescription acne treatment. Our doctors assess your skin and prescribe effective topical or oral medication based on severity — from mild breakouts to severe cystic acne.',
            ],
            'anti-aging-treatment' => [
                'name' => 'Anti-Aging',
                'price' => 'From R220/month',
                'price_once_off' => 45000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Clinically proven anti-ageing treatments prescribed for you.',
                'description' => 'Prescription retinoids, antioxidants, and targeted treatments to reduce fine lines, improve skin texture, and protect against further ageing. Doctor-prescribed, delivered to your door.',
            ],
            'rosacea-treatment' => [
                'name' => 'Rosacea',
                'price' => 'From R250/month',
                'price_once_off' => 45000,
                'price_monthly' => 25000,
                'duration' => 15,
                'tagline' => 'Reduce redness and manage flare-ups effectively.',
                'description' => 'Prescription treatments for rosacea — reduce facial redness, bumps, and irritation. Our doctors create a personalised skincare plan to manage symptoms and prevent flare-ups.',
            ],
            'other-skincare' => [
                'name' => 'Other Skincare',
                'price' => 'From R220/month',
                'price_once_off' => 45000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Personalised prescription skincare for your unique concerns.',
                'description' => 'Hyperpigmentation, melasma, eczema, psoriasis, or other skin concerns? Our doctors assess your needs and prescribe the right treatment plan delivered to your door.',
            ],
        ],
    ],

    'womens-health' => [
        'name' => "Women's Health",
        'description' => 'Contraception, hormonal support, and intimate health',
        'image' => '/images/services/womens-health.webp',
        'treatments' => [
            'bacterial-vaginosis-treatment' => [
                'name' => 'Bacterial Vaginosis',
                'price' => 'From R300',
                'price_once_off' => 30000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Discreet treatment for BV — fast relief, no waiting rooms.',
                'description' => 'Bacterial vaginosis (BV) causes unusual discharge and odour. Our doctors can diagnose and prescribe effective antibiotic treatment discreetly online, with medication delivered to your door.',
            ],
            'birth-control' => [
                'name' => 'Birth Control',
                'price' => 'From R220/month',
                'price_once_off' => 45000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'The right contraception for your body and lifestyle.',
                'description' => 'Combined pill, mini pill, or not sure? Our doctors help you choose the right contraceptive based on your health, lifestyle, and preferences. Prescribed and delivered monthly.',
            ],
            'menopause-management' => [
                'name' => 'Menopause Management',
                'price' => 'From R350/month',
                'price_once_off' => 45000,
                'price_monthly' => 35000,
                'duration' => 15,
                'tagline' => 'Expert support for perimenopause and menopause symptoms.',
                'description' => 'Hot flushes, mood changes, sleep disruption? Our doctors provide personalised HRT and non-hormonal options to manage menopause symptoms effectively and safely.',
            ],
            'other-womens-health' => [
                'name' => "Other Women's Health",
                'price' => 'From R300',
                'price_once_off' => 30000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Comprehensive care for all women\'s health concerns.',
                'description' => 'Period pain, PCOS, hormonal imbalances, or other concerns? Consult with our doctors for diagnosis, treatment, and ongoing management — all online.',
            ],
        ],
    ],

    'mens-health' => [
        'name' => "Men's Health",
        'description' => 'Sexual performance, hair loss, and wellness',
        'image' => '/images/services/mens-health.webp',
        'treatments' => [
            'erectile-dysfunction-treatment' => [
                'name' => 'Erectile Dysfunction',
                'price' => 'From R220/month',
                'price_once_off' => 45000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Discreet, effective ED treatment prescribed online.',
                'description' => 'Clinically proven treatments for erectile dysfunction prescribed by licensed doctors. Choose from daily or on-demand options — Sildenafil, Tadalafil, and more. Delivered discreetly.',
            ],
            'premature-ejaculation-treatment' => [
                'name' => 'Premature Ejaculation',
                'price' => 'From R300',
                'price_once_off' => 30000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Regain control with proven prescription treatments.',
                'description' => 'Prescription treatments including SSRIs and topical solutions to help manage premature ejaculation. Discreet online consultation with medication delivered to your door.',
            ],
            'other-mens-health' => [
                'name' => "Other Men's Health",
                'price' => 'From R300',
                'price_once_off' => 30000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Comprehensive care for men\'s health concerns.',
                'description' => 'Testosterone concerns, prostate health, or general men\'s wellness? Our doctors provide confidential consultations and personalised treatment plans.',
            ],
        ],
    ],

    'sexual-health' => [
        'name' => 'Sexual Health',
        'description' => 'Discreet treatment for sexual health concerns',
        'image' => '/images/services/sexual-health.webp',
        'treatments' => [
            'genital-herpes-101' => [
                'name' => 'Genital Herpes',
                'price' => 'From R300',
                'price_once_off' => 30000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Manage outbreaks and reduce transmission discreetly.',
                'description' => 'Antiviral treatment to manage genital herpes outbreaks, reduce severity, and lower transmission risk. Suppressive or episodic therapy prescribed by our doctors.',
            ],
            'genital-warts-treatment' => [
                'name' => 'Genital Warts',
                'price' => 'From R300',
                'price_once_off' => 30000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Effective prescription treatment for genital warts.',
                'description' => 'Topical prescription treatments to clear genital warts caused by HPV. Discreet online consultation with medication delivered directly to you.',
            ],
            'sti-treatment' => [
                'name' => 'STI',
                'price' => 'From R450',
                'price_once_off' => 45000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Fast, confidential STI testing and treatment.',
                'description' => 'Concerned about an STI? Our doctors provide confidential assessment, testing guidance, and prescription treatment for chlamydia, gonorrhoea, and other common STIs.',
            ],
            'other-sexual-health' => [
                'name' => 'Other Sexual Health',
                'price' => 'From R300',
                'price_once_off' => 30000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Confidential care for all sexual health concerns.',
                'description' => 'Any sexual health concern you need help with — our doctors provide judgement-free, confidential consultations and personalised treatment plans.',
            ],
        ],
    ],

    'general-health' => [
        'name' => 'General Health',
        'description' => 'Virtual GP consultations and everyday treatments',
        'image' => '/images/services/general-health.webp',
        'treatments' => [
            'acid-reflux-treatment' => [
                'name' => 'Acid Reflux',
                'price' => 'From R250',
                'price_once_off' => 25000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Effective relief for heartburn and acid reflux.',
                'description' => 'Prescription PPIs and lifestyle guidance to manage gastro-oesophageal reflux (GORD). Our doctors assess your symptoms and prescribe the right treatment for lasting relief.',
            ],
            'cold-sores-treatment' => [
                'name' => 'Cold Sores',
                'price' => 'From R300',
                'price_once_off' => 30000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Fast-acting antiviral treatment for cold sores.',
                'description' => 'Prescription antivirals to speed healing and reduce the frequency of cold sore outbreaks. Start treatment early for best results.',
            ],
            'gp-consult' => [
                'name' => 'GP Consult',
                'price' => 'R450 once-off',
                'price_once_off' => 45000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'See a doctor online for any health concern.',
                'description' => 'General practitioner consultation via video call. Discuss any health concern, get a diagnosis, prescription, sick note, or medical certificate — all from home.',
            ],
            'haemorrhoids-treatment' => [
                'name' => 'Haemorrhoids',
                'price' => 'From R250',
                'price_once_off' => 25000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Discreet treatment for piles — no embarrassment needed.',
                'description' => 'Prescription creams, suppositories, and oral treatments to manage haemorrhoid symptoms. Our doctors provide discreet, effective care without the awkward in-person visit.',
            ],
            'hair-loss-treatment' => [
                'name' => 'Hair Loss',
                'price' => 'From R220/month',
                'price_once_off' => 45000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Clinically proven hair loss treatments for men.',
                'description' => 'Finasteride, Minoxidil, and combination therapies prescribed by our doctors. Stop hair loss and promote regrowth with treatments delivered monthly.',
            ],
            'uti-treatment' => [
                'name' => 'UTI',
                'price' => 'From R300',
                'price_once_off' => 30000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Fast relief for urinary tract infections.',
                'description' => 'Antibiotics for UTI prescribed quickly after an online assessment. No need to wait in a queue — get treatment started today and feel better within 24-48 hours.',
            ],
            'thrush-treatment' => [
                'name' => 'Thrush',
                'price' => 'From R250',
                'price_once_off' => 25000,
                'price_monthly' => 22000,
                'duration' => 15,
                'tagline' => 'Effective treatment for oral or vaginal thrush.',
                'description' => 'Antifungal treatment prescribed online for candida infections. Our doctors assess your symptoms and prescribe the right treatment — topical or oral — delivered discreetly.',
            ],
        ],
    ],

];
