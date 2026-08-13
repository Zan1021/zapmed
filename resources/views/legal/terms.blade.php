<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Service - Zapmed</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=noto-sans:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white">
    @include('partials.public-nav')

    <div class="pt-28 pb-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto prose prose-gray">
            <h1>Terms of Service</h1>
            <p class="text-sm text-gray-500">Last updated: {{ date('j F Y') }}</p>

            <h2>1. About Zapmed</h2>
            <p>Zapmed (Pty) Ltd provides an online telehealth platform connecting patients with licensed, HPCSA-registered doctors for virtual consultations, prescriptions, and medication delivery across South Africa.</p>

            <h2>2. Eligibility</h2>
            <ul>
                <li>You must be 18 years or older to use this service</li>
                <li>You must be located in South Africa at the time of consultation</li>
                <li>You must provide accurate personal and medical information</li>
            </ul>

            <h2>3. Our Service</h2>
            <p>Zapmed facilitates:</p>
            <ul>
                <li>Online medical assessments</li>
                <li>Video, audio, or text consultations with licensed doctors</li>
                <li>Electronic prescriptions</li>
                <li>Medication dispensing via regulated pharmacy partners</li>
                <li>Medication delivery to your address</li>
            </ul>

            <h2>4. Medical Disclaimer</h2>
            <ul>
                <li>Zapmed is NOT an emergency service. For emergencies, call 10177 or go to your nearest hospital.</li>
                <li>Our doctors make clinical decisions independently based on your presentation.</li>
                <li>Not all conditions can be treated via telehealth. Your doctor may refer you for in-person care.</li>
                <li>A prescription is not guaranteed — it depends on clinical assessment.</li>
            </ul>

            <h2>5. Payments & Refunds</h2>
            <ul>
                <li>Consultation fees are charged at the time of booking.</li>
                <li>Medication costs are separate and charged after prescription.</li>
                <li>Consultation fees are non-refundable once the consultation has occurred.</li>
                <li>Cancellations more than 2 hours before the appointment qualify for a full refund.</li>
                <li>No-shows are charged the full consultation fee.</li>
            </ul>

            <h2>6. Subscriptions</h2>
            <ul>
                <li>Monthly subscriptions renew automatically.</li>
                <li>Cancel anytime — takes effect at end of billing cycle.</li>
                <li>No penalty or cancellation fees.</li>
                <li>Medication costs are billed separately from subscription fees.</li>
            </ul>

            <h2>7. Your Responsibilities</h2>
            <ul>
                <li>Provide truthful, accurate medical information.</li>
                <li>Do not share your account with others.</li>
                <li>Follow your doctor's instructions for medication use.</li>
                <li>Report adverse reactions immediately.</li>
                <li>Keep your login credentials secure.</li>
            </ul>

            <h2>8. Intellectual Property</h2>
            <p>All content, design, and software on this platform is owned by Zapmed (Pty) Ltd and may not be reproduced without permission.</p>

            <h2>9. Limitation of Liability</h2>
            <p>Zapmed facilitates access to healthcare professionals but does not itself provide medical treatment. Our doctors are independent practitioners. Zapmed is not liable for clinical outcomes or adverse reactions to prescribed medication.</p>

            <h2>10. Privacy</h2>
            <p>Your data is processed in accordance with our <a href="/privacy-policy">Privacy Policy</a> and POPIA.</p>

            <h2>11. Governing Law</h2>
            <p>These terms are governed by the laws of the Republic of South Africa. Disputes will be resolved through mediation before litigation.</p>

            <h2>12. Changes</h2>
            <p>We may update these terms. Continued use after changes constitutes acceptance. Material changes will be communicated via email.</p>

            <h2>13. Contact</h2>
            <p>Questions? Email <a href="mailto:support@zapmed.co.za">support@zapmed.co.za</a></p>
        </div>
    </div>

    @include('partials.public-footer')
</body>
</html>
