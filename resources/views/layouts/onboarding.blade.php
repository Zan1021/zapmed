<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $treatmentName ?? 'Start your assessment' }} — Zapmed</title>
    <meta name="robots" content="noindex"> {{-- onboarding journey: keep out of search --}}

    @include('partials.fonts')
    {{-- Brand tokens + Tailwind only. The journey is styled with the shared design
         tokens (resources/css/app.css) via Tailwind utilities — no bespoke journey
         stylesheet, so it always tracks the site-wide moodboard. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased min-h-screen bg-ivory text-brand font-sans">

    {{-- our existing mega menu stays as the site chrome --}}
    @include('partials.public-nav')

    <main class="pt-16">
        {{ $slot }}
    </main>

</body>
</html>
