<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%23006B3F'/%3E%3Ctext x='16' y='22' font-family='Arial,sans-serif' font-size='18' font-weight='bold' fill='white' text-anchor='middle'%3ES%3C/text%3E%3C/svg%3E">
    <title>{{ config('spar.branding.name', 'SPAR Meds') }} — Sign in</title>
    @vite('resources/css/app.css')
    @livewireStyles
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-100 text-gray-900">
    <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
        <div class="mb-6 text-center">
            <img src="{{ asset(config('spar.branding.logo_path', 'img/pharmacy-at-spar-logo.jpg')) }}" alt="{{ config('spar.branding.name', 'SPAR Meds') }}" class="mx-auto mb-3 h-12 w-auto">
            <div class="text-sm text-gray-500">Pharmacy staff sign in</div>
        </div>
        {{ $slot ?? '' }}
        @yield('content')
    </div>
    @livewireScripts
</body>
</html>
