<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('spar.branding.name', 'SPAR Meds') }} — Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <header class="border-b bg-white" style="border-color: {{ config('spar.branding.primary_color', '#006B3F') }}22;">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ route('spar.dashboard') }}" class="flex items-center gap-2">
                <img src="{{ asset('img/spar-logo.jpg') }}" alt="{{ config('spar.branding.name', 'SPAR Meds') }}" class="h-8 w-auto">
                <span class="text-lg font-semibold" style="color: {{ config('spar.branding.primary_color', '#006B3F') }};">Meds</span>
            </a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ route('spar.dashboard') }}" class="flex items-center gap-1.5 hover:underline">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10"/></svg>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('spar.patients') }}" class="flex items-center gap-1.5 hover:underline">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a3 3 0 10-3-3"/></svg>
                    <span>Patients</span>
                </a>
                @auth
                    <a href="{{ route('admin.spar.stats') }}" class="flex items-center gap-1.5 hover:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6M15 19V9M21 19V5M3 19h18"/></svg>
                        <span>Stats</span>
                    </a>
                    @if(app(\Zapmed\SparCore\Contracts\SparIdentityProvider::class)->isSuperAdmin())
                        <a href="{{ route('admin.spar.groups') }}" class="flex items-center gap-1.5 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01"/></svg>
                            <span>Groups</span>
                        </a>
                    @endif
                    @php $sparIdentity = app(\Zapmed\SparCore\Contracts\SparIdentityProvider::class); @endphp
                    @if($sparIdentity->isSuperAdmin() || $sparIdentity->currentRole() === 'group_admin')
                        <a href="{{ route('admin.spar.banners') }}" class="flex items-center gap-1.5 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zM3 16l5-5 4 4 3-3 6 6M9 10a1 1 0 100-2 1 1 0 000 2z"/></svg>
                            <span>Banners</span>
                        </a>
                    @endif
                    @if(auth()->user()?->canManageUsers())
                        <a href="{{ route('admin.staff') }}" class="flex items-center gap-1.5 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M13 7a4 4 0 11-8 0 4 4 0 018 0zm6 3a3 3 0 11-3-3"/></svg>
                            <span>Staff</span>
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 text-red-600 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            <span>Log out</span>
                        </button>
                    </form>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    @livewireScripts
</body>
</html>
