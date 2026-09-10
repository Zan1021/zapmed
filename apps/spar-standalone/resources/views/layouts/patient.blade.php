<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('spar.branding.name', 'SPAR Meds') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="//unpkg.com/alpinejs" defer></script>
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
    <div class="mx-auto max-w-md">
        <header class="px-4 py-4 text-center text-white" style="background: {{ config('spar.branding.primary_color', '#006B3F') }};">
            <div class="mx-auto mb-1 inline-block rounded bg-white px-3 py-1">
                <img src="{{ asset('img/spar-logo.jpg') }}" alt="{{ config('spar.branding.name', 'SPAR Meds') }}" class="h-6 w-auto">
            </div>
            <div class="text-xs opacity-90">My Chronic Medication</div>
        </header>

        <main class="px-4 py-4">
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>

    @livewireScripts
</body>
</html>
