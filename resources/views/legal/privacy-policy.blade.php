<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy - Zapmed</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=noto-sans:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white">
    @include('partials.public-nav')

    <div class="pt-28 pb-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto prose prose-gray">
            <h1>Privacy Policy</h1>
            <p class="text-sm text-gray-500">Last updated: {{ date('j F Y') }}</p>

            <h2>1. Introduction</h2>
            <p>Zapmed (Pty) Ltd ("Zapmed", "we", "us") is committed to protecting your personal information in accordance with the Protection of Personal Information Act 4 of 2013 (POPIA) and applicable data protection laws.</p>
            <p>This policy explains how we collect, use, store, and protect your personal and health information when you use our telehealth platform.</p>

            <h2>2. Information We Collect</h2>
            <h3>Personal Information:</h3>
            <ul>
                <li>Full name, email address, phone number</li>
                <li>Date of birth, gender, ID number</li>
                <li>Physical address (for medication delivery)</li>
                <li>Payment information (processed by PayFast — we do not store card details)</li>
            </ul>
            <h3>Health Information (Special Personal Information):</h3>
            <ul>
                <li>Medical history, current medications, allergies</li>
                <li>Consultation notes, diagnoses, prescriptions</li>
                <li>Assessment questionnaire responses</li>
                <li>Photos uploaded for medical assessment</li>
            </ul>

            <h2>3. How We Use Your Information</h2>
            <ul>
                <li>To provide telehealth consultations with licensed doctors</li>
                <li>To issue prescriptions and facilitate medication delivery</li>
                <li>To communicate appointment reminders and health updates</li>
                <li>To improve our services and your experience</li>
                <li>To comply with legal and regulatory requirements</li>
            </ul>

            <h2>4. Legal Basis for Processing</h2>
            <p>We process your information based on:</p>
            <ul>
                <li><strong>Consent</strong> — you provide explicit consent during registration</li>
                <li><strong>Contract</strong> — processing necessary to provide our services</li>
                <li><strong>Legal obligation</strong> — medical record retention requirements</li>
                <li><strong>Legitimate interest</strong> — improving our platform and preventing fraud</li>
            </ul>

            <h2>5. Data Sharing</h2>
            <p>We share your information only with:</p>
            <ul>
                <li><strong>Your assigned doctor</strong> — for consultation purposes</li>
                <li><strong>Our pharmacy partner</strong> — to dispense your prescription</li>
                <li><strong>Payment provider (PayFast)</strong> — to process payments</li>
                <li><strong>Delivery partner</strong> — your delivery address only</li>
            </ul>
            <p>We do NOT sell your data to third parties. Ever.</p>

            <h2>6. Data Security</h2>
            <ul>
                <li>All data encrypted in transit (TLS/SSL) and at rest</li>
                <li>Access controls — doctors only see their own patients</li>
                <li>Medical data access is logged and auditable</li>
                <li>Regular security assessments</li>
                <li>Hosted on AWS with POPIA-compliant infrastructure</li>
            </ul>

            <h2>7. Data Retention</h2>
            <ul>
                <li>Medical records: retained for 5 years (as required by the Health Professions Act)</li>
                <li>Payment records: retained for 5 years (tax requirements)</li>
                <li>Account data: retained while your account is active</li>
                <li>Marketing preferences: until you unsubscribe</li>
            </ul>

            <h2>8. Your Rights (POPIA)</h2>
            <p>You have the right to:</p>
            <ul>
                <li><strong>Access</strong> — request a copy of all your personal data</li>
                <li><strong>Correction</strong> — request corrections to inaccurate data</li>
                <li><strong>Deletion</strong> — request deletion of your data (subject to legal retention requirements)</li>
                <li><strong>Object</strong> — object to processing for marketing purposes</li>
                <li><strong>Withdraw consent</strong> — withdraw consent at any time</li>
            </ul>
            <p>To exercise these rights, email <a href="mailto:privacy@zapmed.co.za">privacy@zapmed.co.za</a>.</p>

            <h2>9. Cookies</h2>
            <p>We use essential cookies for functionality and optional cookies for analytics. See our <a href="/cookie-policy">Cookie Policy</a> for details.</p>

            <h2>10. Information Officer</h2>
            <p>Our Information Officer can be contacted at:<br>
            Email: <a href="mailto:privacy@zapmed.co.za">privacy@zapmed.co.za</a><br>
            Address: Zapmed (Pty) Ltd, South Africa</p>

            <h2>11. Changes to This Policy</h2>
            <p>We may update this policy from time to time. We will notify you of significant changes via email or a notice on our platform.</p>

            <h2>12. Complaints</h2>
            <p>If you believe your privacy rights have been violated, you may lodge a complaint with the Information Regulator:<br>
            Website: <a href="https://inforegulator.org.za" target="_blank" rel="noopener">inforegulator.org.za</a></p>
        </div>
    </div>

    @include('partials.public-footer')
</body>
</html>
