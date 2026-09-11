<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('spar.branding.name', 'Pharmacy at SPAR') }} — Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important;}</style>
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <header class="border-b bg-white" style="border-color: {{ config('spar.branding.primary_color', '#006B3F') }}22;">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ route('spar.dashboard') }}" class="flex items-center gap-2">
                <img src="{{ asset(config('spar.branding.logo_path', 'img/pharmacy-at-spar-logo.jpg')) }}"
                     alt="{{ config('spar.branding.name', 'Pharmacy at SPAR') }}" class="h-14 w-auto">
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
                        <span>Insights</span>
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
                    <div class="ml-2 flex items-center gap-2 border-l pl-4" style="border-color: #e5e7eb;">
                        @php
                            $sparUser = auth()->user();
                            $sparName = $sparUser?->name ?? 'Account';
                            $sparInitials = collect(explode(' ', trim($sparName)))
                                ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
                            $sparRoleLabel = ucwords(str_replace('_', ' ', (string) $sparIdentity->currentRole()));
                        @endphp
                        <div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
                            <button type="button" @click="open = !open"
                                    class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-1"
                                    style="--tw-ring-color: {{ config('spar.branding.primary_color', '#006B3F') }};"
                                    :aria-expanded="open" aria-haspopup="true">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold text-white"
                                      style="background-color: {{ config('spar.branding.primary_color', '#006B3F') }};">{{ $sparInitials ?: 'AC' }}</span>
                                <span class="hidden sm:block max-w-[10rem] truncate text-sm font-medium text-gray-800">{{ $sparName }}</span>
                                <svg class="h-4 w-4 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-cloak x-show="open" @click.outside="open = false"
                                 x-transition.origin.top.right
                                 class="absolute right-0 z-20 mt-2 w-56 origin-top-right rounded-lg border border-gray-100 bg-white py-1 shadow-lg"
                                 role="menu">
                                <div class="border-b border-gray-100 px-4 py-3">
                                    <p class="truncate text-sm font-semibold text-gray-900">{{ $sparName }}</p>
                                    @if($sparRoleLabel)
                                        <p class="truncate text-xs text-gray-500">{{ $sparRoleLabel }}</p>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('logout') }}" role="none">
                                    @csrf
                                    <button type="submit" role="menuitem"
                                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                        <span>Log out</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
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
