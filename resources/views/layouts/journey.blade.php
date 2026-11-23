<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $treatmentName ?? 'Start your assessment' }} - Zapmed</title>
    <meta name="robots" content="noindex"> {{-- booking journey: keep out of search --}}

    @include('partials.fonts')
    {{-- Craig's final booking-journey look (26 Nov handoff), scoped to .zj. No site nav: a focused flow. --}}
    @vite(['resources/css/zapmed-journey.css', 'resources/js/app.js'])
</head>
<body>
    <div class="zj">
        {{ $slot }}
    </div>
</body>
</html>
