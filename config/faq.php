<?php

/*
|--------------------------------------------------------------------------
| FAQ Content — organized by category
|--------------------------------------------------------------------------
|
| Each category has a name, description, icon (SVG path), and array of Q&As.
| Edit this file to update FAQ content. No code changes needed.
|
*/

return [

    'about-us' => [
        'name' => 'About Us',
        'description' => 'Learn more about Zapmed, our mission, and the team behind your care.',
        'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'questions' => [
            [
                'q' => 'What is Zapmed?',
                'a' => 'Zapmed is a licensed South African telehealth platform that connects you with registered doctors for online consultations. We cover weight loss, sexual health, skincare, chronic care, and general GP consultations — all from your phone or computer.',
            ],
            [
                'q' => 'How does Zapmed\'s service work?',
                'a' => 'You sign up, complete a quick health assessment, book a consultation with a licensed doctor (video, audio, or text), receive your prescription digitally, pay for your medication, and it gets delivered to your door in 1-3 business days.',
            ],
            [
                'q' => 'Are your doctors real and registered?',
                'a' => 'Yes. All Zapmed doctors are fully registered with the Health Professions Council of South Africa (HPCSA) and are experienced in telehealth consultations.',
            ],
            [
                'q' => 'Is Zapmed legal in South Africa?',
                'a' => 'Absolutely. Telehealth consultations are legal in South Africa. Our practice complies with HPCSA telehealth guidelines, POPIA data protection laws, and all pharmacy regulations.',
            ],
        ],
    ],

    'my-account' => [
        'name' => 'My Account',
        'description' => 'Manage your Zapmed account details, login, and personal information.',
        'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'questions' => [
            [
                'q' => 'How do I create an account?',
                'a' => 'Click "Start Your Assessment" on our homepage. You\'ll need an email address and password. The whole process takes about 30 seconds.',
            ],
            [
                'q' => 'How do I reset my password?',
                'a' => 'Click "Forgot Password" on the login page. We\'ll send a reset link to your email. If you don\'t see it, check your spam folder.',
            ],
            [
                'q' => 'How do I update my personal details?',
                'a' => 'Log into your dashboard, click on your profile icon, and select "Profile". You can update your name, phone number, address, and medical information there.',
            ],
            [
                'q' => 'How do I delete my account?',
                'a' => 'Contact us at support@zapmed.co.za and request account deletion. We\'ll process it within 7 days. Note: we retain medical records as required by law.',
            ],
        ],
    ],

    'orders-and-delivery' => [
        'name' => 'My Orders & Delivery',
        'description' => 'Track and manage your orders, delivery, and cancellations.',
        'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
        'questions' => [
            [
                'q' => 'How do I track my medication delivery?',
                'a' => 'Once your medication is dispatched, you\'ll receive an SMS and email with tracking details. You can also check your order status in your patient dashboard.',
            ],
            [
                'q' => 'How long does delivery take?',
                'a' => 'Delivery takes 1-3 business days nationwide (all 9 provinces). Metro areas typically receive within 1-2 days.',
            ],
            [
                'q' => 'Is the packaging discreet?',
                'a' => 'Yes. All medication arrives in plain, unbranded packaging. There\'s no indication of the contents or that it\'s from a healthcare provider.',
            ],
            [
                'q' => 'Can I cancel or pause my order?',
                'a' => 'If your order hasn\'t been dispatched yet, contact us immediately and we can pause or cancel it. Once dispatched, it cannot be recalled.',
            ],
            [
                'q' => 'What if my medication doesn\'t arrive?',
                'a' => 'Contact support@zapmed.co.za with your order reference. We\'ll investigate with the courier and arrange a re-send if necessary at no extra cost.',
            ],
        ],
    ],

    'consultations' => [
        'name' => 'Doctors & Consultations',
        'description' => 'Everything about our doctors and how consultations work.',
        'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
        'questions' => [
            [
                'q' => 'How do consultations work?',
                'a' => 'After booking, your doctor reviews your health assessment. At your scheduled time, you connect via video call, audio call, or text chat (your choice). The consultation typically lasts 15-30 minutes.',
            ],
            [
                'q' => 'Can I choose between video, audio, or text?',
                'a' => 'Yes! During booking, you select your preferred communication method. Audio-only and text-only options are perfect for sensitive conditions where you\'d prefer privacy.',
            ],
            [
                'q' => 'How much does a consultation cost?',
                'a' => 'Once-off consultations start at R450. Monthly subscription plans (including consultations) start at R220/month. Medication costs are separate.',
            ],
            [
                'q' => 'Can I see the same doctor again?',
                'a' => 'We prioritise continuity of care. If you\'ve seen a doctor before, our system will try to book you with them again when they\'re available.',
            ],
            [
                'q' => 'What happens if I miss my appointment?',
                'a' => 'If you miss your scheduled time without cancelling at least 2 hours before, it counts as a no-show and the fee is not refunded. We send reminders 24 hours, 1 hour, and 15 minutes before.',
            ],
        ],
    ],

    'pharmacy' => [
        'name' => 'Pharmacy & Medication',
        'description' => 'Information about your medication, our pharmacy partner, and prescriptions.',
        'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z',
        'questions' => [
            [
                'q' => 'Which pharmacy dispenses my medication?',
                'a' => 'We partner with SAPC-registered pharmacies that specialise in dispensing telehealth prescriptions. All pharmacies are fully licensed and regulated.',
            ],
            [
                'q' => 'How do I pay for my medication?',
                'a' => 'After your doctor signs your prescription, you\'ll receive an email/SMS with a payment link. Pay via card, EFT, or SnapScan. Once paid, the pharmacy dispatches your medication.',
            ],
            [
                'q' => 'Can I use my medical aid?',
                'a' => 'Currently we accept private payment only. We\'re working on medical aid integration. You may be able to claim back from your savings via your medical aid — check with your scheme.',
            ],
            [
                'q' => 'What if I need a refill?',
                'a' => 'For chronic prescriptions with repeats, you can request a refill from your dashboard without needing a new consultation. The pharmacy will process it and deliver as usual.',
            ],
        ],
    ],

    'payments' => [
        'name' => 'Help & Payments',
        'description' => 'Help with common issues including payments, bookings, and accounts.',
        'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        'questions' => [
            [
                'q' => 'What payment methods do you accept?',
                'a' => 'We accept credit/debit cards (Visa, Mastercard), instant EFT, and SnapScan — all processed securely through PayFast.',
            ],
            [
                'q' => 'Can I get a refund?',
                'a' => 'Consultation fees are non-refundable once the consultation has taken place. If a consultation is cancelled more than 2 hours before, a full refund is issued. Medication refunds depend on whether it has been dispatched.',
            ],
            [
                'q' => 'Is my payment information secure?',
                'a' => 'Yes. We never store your card details. All payments are processed through PayFast, a PCI DSS Level 1 certified payment gateway with 256-bit encryption.',
            ],
            [
                'q' => 'How do I cancel my subscription?',
                'a' => 'Go to your dashboard → Subscription → Cancel. Cancellation takes effect at the end of your current billing cycle. No penalty fees.',
            ],
        ],
    ],

    'weight-loss' => [
        'name' => 'Weight Loss',
        'description' => 'Our weight-loss programme, treatment options, and health coach support.',
        'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
        'questions' => [
            [
                'q' => 'How does the weight loss programme work?',
                'a' => 'After your assessment and consultation, your doctor may prescribe GLP-1 medication (like semaglutide) alongside a personalised plan from your dedicated health coach. You\'ll have ongoing support via monthly check-ins.',
            ],
            [
                'q' => 'What is GLP-1 medication?',
                'a' => 'GLP-1 receptor agonists (like Ozempic/Wegovy) are injectable medications that reduce appetite and help with weight loss. They\'re clinically proven and prescribed by your doctor based on your health profile.',
            ],
            [
                'q' => 'Will I get a health coach?',
                'a' => 'Yes. Our weight loss programme includes a dedicated registered dietitian who provides ongoing nutritional guidance, accountability, and lifestyle coaching alongside your medical treatment.',
            ],
            [
                'q' => 'How much weight can I expect to lose?',
                'a' => 'Results vary, but clinical studies show average weight loss of 10-15% of body weight over 12 months with GLP-1 medication combined with lifestyle changes.',
            ],
        ],
    ],

    'sexual-health' => [
        'name' => 'Sexual Health',
        'description' => 'Discreet care for ED, PE, STIs, herpes, and genital warts.',
        'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
        'questions' => [
            [
                'q' => 'Is sexual health treatment discreet?',
                'a' => 'Completely. Your consultation can be audio-only or text-only. Medication arrives in plain packaging. Your records are encrypted and confidential.',
            ],
            [
                'q' => 'How does ED treatment work?',
                'a' => 'Your doctor assesses your health history, checks for contraindications (like nitrate medications), and prescribes appropriate treatment. Common options include sildenafil or tadalafil.',
            ],
            [
                'q' => 'Can I get STI treatment without an in-person test?',
                'a' => 'For some STIs with clear symptoms, your doctor can prescribe treatment based on your consultation. For others, they may recommend a lab test first and treat once results confirm.',
            ],
            [
                'q' => 'Do I have to show my face on camera?',
                'a' => 'No. You can choose audio-only or text-chat consultation. Many patients with sexual health concerns prefer this — and that\'s perfectly fine.',
            ],
        ],
    ],

    'skincare' => [
        'name' => 'Skincare',
        'description' => 'Acne, anti-aging, rosacea, and general skin treatments.',
        'icon' => 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01',
        'questions' => [
            [
                'q' => 'Can a doctor treat acne online?',
                'a' => 'Yes. Many acne treatments can be prescribed after a telehealth consultation. Upload photos of your skin during your assessment so the doctor can assess severity.',
            ],
            [
                'q' => 'What about prescription retinoids?',
                'a' => 'Our doctors can prescribe tretinoin and other prescription-strength retinoids after assessing your skin type, sensitivity, and pregnancy status.',
            ],
            [
                'q' => 'Do I need to upload photos?',
                'a' => 'Photos are optional but highly recommended for skin conditions. They help your doctor assess the condition more accurately during a telehealth consultation.',
            ],
        ],
    ],

    'general-health' => [
        'name' => 'General Health',
        'description' => 'GP consultations, sick notes, UTIs, acid reflux, and more.',
        'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        'questions' => [
            [
                'q' => 'Can I get a sick note online?',
                'a' => 'Yes. After your GP consultation, your doctor can issue a digital sick note. It\'s legally valid and available for download from your dashboard immediately.',
            ],
            [
                'q' => 'What conditions can you treat online?',
                'a' => 'We treat UTIs, acid reflux, cold sores, thrush, haemorrhoids, hair loss, and many other conditions. If your condition requires in-person examination, your doctor will advise you accordingly.',
            ],
            [
                'q' => 'Can I get a repeat prescription?',
                'a' => 'Yes. Book a GP consultation, select "Repeat prescription" as your reason, and tell us which medication you need. Your doctor can issue a new prescription if appropriate.',
            ],
        ],
    ],

];
