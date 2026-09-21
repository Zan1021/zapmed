<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('spar.branding.name', 'SPAR Meds') }}</title>
    @vite('resources/css/app.css')
    {{-- Alpine is provided by Livewire 3 (@livewireScripts) — no standalone CDN. --}}
    @livewireStyles
</head>
<body class="h-[100dvh] bg-gray-100 text-gray-900">
    <div class="mx-auto max-w-md h-full flex flex-col">
        <header class="px-4 py-4 text-center text-white shrink-0" style="background: {{ config('spar.branding.primary_color', '#006B3F') }};">
            <div class="mx-auto mb-1 inline-block">
                <img src="{{ asset(config('spar.branding.logo_path', 'img/pharmacy-at-spar-logo.jpg')) }}" alt="{{ config('spar.branding.name', 'SPAR Meds') }}" class="h-16 w-auto rounded">
            </div>
            <div class="text-xs opacity-90">My Chronic Medication</div>
        </header>

        <main class="px-4 py-4 flex-1 min-h-0 overflow-y-auto">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>

    @livewireScripts
</body>
</html>
