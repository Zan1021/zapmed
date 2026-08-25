<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>My Meds — SPAR Pharmacy</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        body { background: #f8faf9; }
        .spar-green { color: #15803d; }
        .spar-bg { background: linear-gradient(135deg, #15803d 0%, #14532d 100%); }
    </style>
</head>
<body class="h-full font-sans antialiased">
    <div class="min-h-full flex flex-col">
        <!-- Header -->
        <header class="spar-bg text-white shadow-lg">
            <div class="max-w-lg mx-auto px-4 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-white/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold leading-tight">My Meds</h1>
                        <p class="text-green-200 text-xs">SPAR Pharmacy</p>
                    </div>
                </div>
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-green-200 hover:text-white text-xs font-medium">Sign Out</button>
                    </form>
                @endauth
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 max-w-lg mx-auto w-full px-4 py-6">
            {{ $slot }}
        </main>

        <!-- Footer -->
        <footer class="max-w-lg mx-auto w-full px-4 py-6 text-center">
            <p class="text-xs text-gray-400">Powered by <span class="font-medium text-gray-500">ZapMed</span></p>
            <p class="text-[10px] text-gray-300 mt-1">Your data is encrypted and protected under POPIA.</p>
        </footer>
    </div>

    @livewireScripts
</body>
</html>
