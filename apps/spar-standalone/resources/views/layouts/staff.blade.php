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
                <a href="{{ route('spar.dashboard') }}" class="hover:underline">Dashboard</a>
                <a href="{{ route('spar.patients') }}" class="hover:underline">Patients</a>
                <a href="{{ route('spar.capture') }}" class="hover:underline">Capture</a>
                @auth
                    <a href="{{ route('admin.spar.stats') }}" class="hover:underline">Stats</a>
                    @if(app(\Zapmed\SparCore\Contracts\SparIdentityProvider::class)->isSuperAdmin())
                        <a href="{{ route('admin.spar.groups') }}" class="hover:underline">Groups</a>
                    @endif
                    @if(auth()->user()?->canManageUsers())
                        <a href="{{ route('admin.staff') }}" class="hover:underline">Staff</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-red-600 hover:underline">Log out</button>
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
