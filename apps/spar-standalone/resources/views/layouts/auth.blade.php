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
<body class="flex min-h-screen flex-col items-center justify-center bg-cover bg-center text-gray-900"
      style="background-color: {{ config('spar.branding.primary_color', '#038c46') }}; background-image: linear-gradient(rgba(3,140,70,0.55), rgba(2,90,52,0.75)), url('{{ asset('img/pharmacy-login.webp') }}'); background-size: cover; background-position: center;">
    <div class="mb-6 max-w-xl px-4 text-center" style="text-shadow: 0 1px 3px rgba(0,0,0,0.4);">
        <h1 class="text-2xl font-bold text-white sm:text-3xl">Welcome, SPAR stars!</h1>
        <p class="mt-1 text-base text-white/90">Big smiles. Good care. Let's go.</p>
    </div>

    <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow">
        <div class="mb-6 text-center">
            <img src="{{ asset(config('spar.branding.logo_path', 'img/pharmacy-at-spar-logo.jpg')) }}" alt="{{ config('spar.branding.name', 'SPAR Meds') }}" class="mx-auto mb-3 h-[80.64px] w-auto">
            <div class="text-sm text-gray-500">Pharmacy staff sign in</div>
        </div>
        {{ $slot ?? '' }}
        @yield('content')
    </div>

    {{-- Thin site credit bar, pinned to the bottom. Plain div + text wordmark
         (no <footer>/image/"logo" path) so ad/content blockers don't strip it. --}}
    <div role="contentinfo" class="fixed inset-x-0 bottom-0 bg-black">
        <div class="mx-auto flex max-w-7xl items-center justify-center gap-1.5 px-4 py-2">
            <span class="text-xs text-gray-400">Powered by</span>
            <span class="text-sm font-bold lowercase tracking-tight text-white">zapmed<span class="text-green-400">.</span></span>
        </div>
    </div>
    @livewireScripts
</body>
</html>
