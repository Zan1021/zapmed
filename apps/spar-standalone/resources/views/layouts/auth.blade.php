<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('spar.branding.name', 'SPAR Meds') }} — Sign in</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-100 text-gray-900">
    <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
        <div class="mb-6 text-center">
            <img src="{{ asset('img/spar-logo.jpg') }}" alt="{{ config('spar.branding.name', 'SPAR Meds') }}" class="mx-auto mb-3 h-12 w-auto">
            <div class="text-sm text-gray-500">Pharmacy staff sign in</div>
        </div>
        {{ $slot ?? '' }}
        @yield('content')
    </div>
    @livewireScripts
</body>
</html>
