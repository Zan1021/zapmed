<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%23006B3F'/%3E%3Ctext x='16' y='22' font-family='Arial,sans-serif' font-size='18' font-weight='bold' fill='white' text-anchor='middle'%3ES%3C/text%3E%3C/svg%3E">
    <title>{{ config('spar.branding.name', 'Pharmacy at SPAR') }} — Staff</title>
    @vite('resources/css/app.css')
    {{-- Alpine is provided by Livewire 3 (@livewireScripts). Do NOT also load a
         standalone Alpine CDN — it double-initialises Alpine AND violates the
         SparSecurityHeaders CSP (external Alpine origins are not allow-listed). --}}
    <style>[x-cloak]{display:none!important;}</style>
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 pb-[88px]">
    <header class="border-b bg-white" style="border-color: {{ config('spar.branding.primary_color', '#006B3F') }}22;">
        {{-- Row 1: full-width brand bar — logo left, account menu right. Kept on
             its own row so the (wide) SPAR logo never competes with the nav for
             horizontal space. --}}
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
            <a href="{{ route('spar.dashboard') }}" class="flex shrink-0 items-center gap-2">
                <img src="{{ asset(config('spar.branding.logo_path', 'img/pharmacy-at-spar-logo.jpg')) }}"
                     alt="{{ config('spar.branding.name', 'Pharmacy at SPAR') }}" class="h-[57.6px] w-auto max-w-none">
            </a>
            @auth
                @php
                    $sparIdentity = app(\Zapmed\SparCore\Contracts\SparIdentityProvider::class);
                    $sparUser = auth()->user();
                    $sparName = $sparUser?->name ?? 'Account';
                    $sparInitials = collect(explode(' ', trim($sparName)))
                        ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
                    $sparRoleLabel = ucwords(str_replace('_', ' ', (string) $sparIdentity->currentRole()));
                @endphp
                <div x-data="{ open: false }" class="relative shrink-0" @keydown.escape.window="open = false">
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
            @endauth
        </div>

        {{-- Row 2: navigation on its own full-width row. Each link is a padded
             pill so the icon + label read as ONE unit and items are clearly
             separated (fixes the run-on "Dashboard-Patients-Orders" squash).
             Wraps gracefully and scrolls on very narrow screens. --}}
        <nav class="border-t" style="border-color: {{ config('spar.branding.primary_color', '#006B3F') }}11;">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-1 overflow-x-auto px-2 py-1.5 text-sm sm:px-4">
                @php $navLink = 'flex items-center gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors'; @endphp
                <a href="{{ route('spar.dashboard') }}" class="{{ $navLink }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10"/></svg>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('spar.patients') }}" class="{{ $navLink }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a3 3 0 10-3-3"/></svg>
                    <span>Patients</span>
                </a>
                {{-- Health Coach inbox — pharmacy-scoped staff only (the counter staff who
                     reply to patients). Group/super admins use the per-patient panel. --}}
                @php
                    $sparCoachIdentity = app(\Zapmed\SparCore\Contracts\SparIdentityProvider::class);
                    $sparCoachPharmacyId = $sparCoachIdentity->currentPharmacyId();
                    $sparCoachUnread = $sparCoachPharmacyId !== null
                        ? (int) \Zapmed\SparCore\Models\SparConversation::visibleToCurrentActor()->sum('staff_unread_count')
                        : 0;
                @endphp
                @if($sparCoachPharmacyId !== null)
                    <a href="{{ route('spar.coach.inbox') }}" class="{{ $navLink }} relative">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.9 9.9 0 01-4-.83L3 20l1.17-3.5A7.6 7.6 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <span>Health Coach</span>
                        @if($sparCoachUnread > 0)
                            <span class="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] font-bold">{{ $sparCoachUnread }}</span>
                        @endif
                    </a>
                @endif
                @auth
                    @php
                        $sparOrdersDue = (int) \Zapmed\SparCore\Models\SparOrder::visibleToCurrentActor()
                            ->whereIn('status', ['requested', 'preparing'])->count();
                        $sparRenewalsDue = (int) \Zapmed\SparCore\Models\SparPrescriptionJourney::visibleToCurrentActor()
                            ->where('status', 'renewal_due')->count();
                    @endphp
                    <a href="{{ route('admin.spar.orders') }}" class="{{ $navLink }}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span>Orders</span>
                        @if($sparOrdersDue > 0)
                            <span class="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] font-bold">{{ $sparOrdersDue }}</span>
                        @endif
                    </a>
                    <a href="{{ route('admin.spar.renewals') }}" class="{{ $navLink }}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Renewals</span>
                        @if($sparRenewalsDue > 0)
                            <span class="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-amber-500 text-white text-[10px] font-bold">{{ $sparRenewalsDue }}</span>
                        @endif
                    </a>
                    <a href="{{ route('admin.spar.broadcast') }}" class="{{ $navLink }}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                        <span>Broadcast</span>
                    </a>
                    <a href="{{ route('admin.spar.stats') }}" class="{{ $navLink }}">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6M15 19V9M21 19V5M3 19h18"/></svg>
                        <span>Insights</span>
                    </a>
                    @if(app(\Zapmed\SparCore\Contracts\SparIdentityProvider::class)->isSuperAdmin())
                        <a href="{{ route('admin.spar.groups') }}" class="{{ $navLink }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01"/></svg>
                            <span>Groups</span>
                        </a>
                    @endif
                    @php $sparIdentityNav = app(\Zapmed\SparCore\Contracts\SparIdentityProvider::class); @endphp
                    @if($sparIdentityNav->isSuperAdmin() || $sparIdentityNav->currentRole() === 'group_admin')
                        <a href="{{ route('admin.spar.banners') }}" class="{{ $navLink }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zM3 16l5-5 4 4 3-3 6 6M9 10a1 1 0 100-2 1 1 0 000 2z"/></svg>
                            <span>Banners</span>
                        </a>
                    @endif
                    @if(auth()->user()?->canManageUsers())
                        <a href="{{ route('admin.staff') }}" class="{{ $navLink }}">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M13 7a4 4 0 11-8 0 4 4 0 018 0zm6 3a3 3 0 11-3-3"/></svg>
                            <span>Staff</span>
                        </a>
                    @endif
                @endauth
            </div>
        </nav>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    {{-- Thin site credit bar — FIXED to the bottom, OUT of the normal layout
         flow so it can never affect page centering/width. Plain div + text
         wordmark (no <footer>/image/"logo" paths) so ad/content blockers don't
         strip it. Shown on staff/admin/auth, NOT the patient mobi app. --}}
    <div role="contentinfo" class="fixed inset-x-0 bottom-0 z-30 bg-black">
        <div class="mx-auto flex max-w-7xl items-center justify-center gap-1.5 px-4 py-2">
            <span class="text-xs text-gray-400">Powered by</span>
            <span class="text-sm font-bold lowercase tracking-tight text-white">zapmed<span class="text-green-400">.</span></span>
        </div>
    </div>

    @livewireScripts
</body>
</html>
