<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Zapmed - Online Doctor-Guided Medical Treatments</title>
    <meta name="description" content="Doctor-led online treatment for weight loss, sexual health, skincare, and chronic care. Prescribed by licensed SA doctors, delivered to your door.">

    @include('partials.fonts')

    {{-- app.css = global brand tokens + Tailwind (nav, shared UI). zapmed-home.css = the
         homepage look vendored 1:1 from Craig's mockup (2026-10-30), scoped to .zapmed-home. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/zapmed-home.css', 'resources/js/zapmed-home.js'])
    @include('partials.analytics')
    @include('partials.pwa-meta')
</head>
<body class="antialiased bg-white">

    {{-- Our existing mega menu stays (Captain Zan decision) --}}
    @include('partials.public-nav')

    {{--
        Homepage ported from Craig's complete website mockup (Zapmed-Complete-Website-Mockup-2026-10-30),
        matched 1:1. Styles are scoped to .zapmed-home (resources/css/zapmed-home.css); interactive
        behaviour (categories/benefits/team/reviews/faq injection, BMI + weight calculators, scrollers)
        comes from resources/js/zapmed-home.js. Assets live in public/mockup-assets/.

        NOTE: the hero (#home) is a placeholder — Captain Zan is redesigning it separately.
        CTAs point at our real flow (/treatments). pt-16 clears our fixed mega menu.
    --}}
    <div class="zapmed-home pt-16">
@include('partials.home-body')
    </div>

</body>
</html>
