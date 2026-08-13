<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cookie Policy - Zapmed</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=noto-sans:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white">
    @include('partials.public-nav')

    <div class="pt-28 pb-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto prose prose-gray">
            <h1>Cookie Policy</h1>
            <p class="text-sm text-gray-500">Last updated: {{ date('j F Y') }}</p>

            <h2>What Are Cookies?</h2>
            <p>Cookies are small text files stored on your device when you visit a website. They help us provide a better experience and understand how you use our platform.</p>

            <h2>Cookies We Use</h2>

            <h3>Essential Cookies (always active)</h3>
            <p>Required for the website to function. Cannot be disabled.</p>
            <table>
                <thead><tr><th>Cookie</th><th>Purpose</th><th>Duration</th></tr></thead>
                <tbody>
                    <tr><td>zapmed_session</td><td>Keeps you logged in</td><td>2 hours</td></tr>
                    <tr><td>XSRF-TOKEN</td><td>Security (prevents cross-site attacks)</td><td>2 hours</td></tr>
                    <tr><td>zapmed_consent</td><td>Remembers your cookie preference</td><td>1 year</td></tr>
                </tbody>
            </table>

            <h3>Functional Cookies</h3>
            <p>Enhance your experience but are not essential.</p>
            <table>
                <thead><tr><th>Cookie</th><th>Purpose</th><th>Duration</th></tr></thead>
                <tbody>
                    <tr><td>zapmed_ref</td><td>Tracks which partner referred you</td><td>30 days</td></tr>
                </tbody>
            </table>

            <h3>Analytics Cookies (optional)</h3>
            <p>Help us understand how visitors use our site. Only set if you accept "All Cookies".</p>
            <table>
                <thead><tr><th>Cookie</th><th>Purpose</th><th>Duration</th></tr></thead>
                <tbody>
                    <tr><td>_ga, _gid</td><td>Google Analytics (traffic analysis)</td><td>2 years / 24 hours</td></tr>
                </tbody>
            </table>

            <h2>Managing Cookies</h2>
            <p>You can manage your preference via the consent banner that appears on your first visit. You can also clear cookies in your browser settings at any time.</p>

            <h2>Contact</h2>
            <p>Questions about our cookie practices? Email <a href="mailto:privacy@zapmed.co.za">privacy@zapmed.co.za</a></p>
        </div>
    </div>

    @include('partials.public-footer')
</body>
</html>
