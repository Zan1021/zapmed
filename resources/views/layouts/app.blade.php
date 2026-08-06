<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Zapmed') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen flex bg-gray-50" x-data="{ sidebarOpen: false }">

            <!-- Mobile sidebar overlay -->
            <div x-show="sidebarOpen" x-transition:enter="transition-opacity ease-linear duration-300"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-linear duration-300"
                 x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-40 bg-gray-600/75 lg:hidden" @click="sidebarOpen = false">
            </div>

            <!-- Sidebar -->
            <aside class="fixed inset-y-0 left-0 z-50 w-64 bg-sidebar transform transition-transform duration-300 lg:translate-x-0 lg:static lg:inset-auto"
                   :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">

                <!-- Logo -->
                <div class="flex items-center h-16 px-6 border-b border-slate-700">
                    <a href="{{ route('dashboard') }}" class="flex items-center">
                        <span class="text-2xl font-bold text-white tracking-tight">zapmed<span class="text-zapmed-400">.</span></span>
                    </a>
                </div>

                <!-- Navigation -->
                <nav class="mt-6 px-3 space-y-1">
                    @include('layouts.partials.sidebar-nav')
                </nav>

                <!-- User info at bottom -->
                <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-slate-700">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-9 h-9 rounded-full bg-zapmed-600 flex items-center justify-center">
                                <span class="text-sm font-medium text-white">
                                    {{ substr(auth()->user()->first_name, 0, 1) }}{{ substr(auth()->user()->last_name, 0, 1) }}
                                </span>
                            </div>
                        </div>
                        <div class="ml-3 min-w-0 flex-1">
                            <p class="text-sm font-medium text-white truncate">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-400 truncate">{{ auth()->user()->role->label() }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-slate-400 hover:text-white transition-colors" title="Logout">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            <!-- Main content area -->
            <div class="flex-1 flex flex-col min-w-0">
                <!-- Top bar -->
                <header class="sticky top-0 z-30 flex items-center h-16 px-4 bg-white border-b border-gray-200 sm:px-6 lg:px-8">
                    <!-- Mobile menu button -->
                    <button @click="sidebarOpen = true" class="text-gray-500 hover:text-gray-700 lg:hidden">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    @if (isset($header))
                        <h1 class="ml-4 lg:ml-0 text-lg font-semibold text-gray-900">
                            {{ $header }}
                        </h1>
                    @endif

                    <div class="ml-auto flex items-center space-x-4">
                        <!-- Notifications placeholder -->
                        <button class="relative text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span class="absolute top-0 right-0 block h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                        </button>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
