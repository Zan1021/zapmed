<!-- Navigation -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-lg border-b border-gray-100" x-data="{ megaOpen: false, mobileOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center">
                <a href="/" class="flex items-center">
                    <img src="/images/zapmed-logo.png" alt="Zapmed - Online Doctor-Guided Medical Treatments South Africa" class="h-8 w-auto">
                </a>
            </div>
            <div class="hidden md:flex items-center space-x-8">
                <!-- All Treatments Mega Menu Trigger -->
                <button @mouseenter="megaOpen = true" class="relative text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors flex items-center gap-1 pb-1 group">
                    All Treatments
                    <svg class="w-4 h-4 transition-transform" :class="megaOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-zapmed-600 group-hover:w-full transition-all duration-300"></span>
                </button>
                <a href="/#how-it-works" class="relative text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors pb-1 group">How It Works<span class="absolute bottom-0 left-0 w-0 h-0.5 bg-zapmed-600 group-hover:w-full transition-all duration-300"></span></a>
                <a href="/#pricing" class="relative text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors pb-1 group">Pricing<span class="absolute bottom-0 left-0 w-0 h-0.5 bg-zapmed-600 group-hover:w-full transition-all duration-300"></span></a>
                <a href="/#doctors" class="relative text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors pb-1 group">Our Doctors<span class="absolute bottom-0 left-0 w-0 h-0.5 bg-zapmed-600 group-hover:w-full transition-all duration-300"></span></a>
                <a href="{{ route('help') }}" class="relative text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors pb-1 group">FAQ<span class="absolute bottom-0 left-0 w-0 h-0.5 bg-zapmed-600 group-hover:w-full transition-all duration-300"></span></a>
                <a href="{{ route('blog') }}" class="relative text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors pb-1 group">Blog<span class="absolute bottom-0 left-0 w-0 h-0.5 bg-zapmed-600 group-hover:w-full transition-all duration-300"></span></a>
            </div>
            <div class="flex items-center space-x-4">
                @auth
                    <!-- Authenticated User Avatar Dropdown -->
                    <div class="relative" x-data="{ userMenuOpen: false }">
                        <button @click="userMenuOpen = !userMenuOpen" class="flex items-center space-x-2 focus:outline-none">
                            @if(auth()->user()->avatar_url)
                                <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}" class="w-9 h-9 rounded-full object-cover border-2 border-gray-200 hover:border-zapmed-500 transition-colors">
                            @else
                                <div class="w-9 h-9 rounded-full bg-zapmed-600 flex items-center justify-center text-white text-sm font-semibold shadow-sm hover:bg-zapmed-700 transition-colors">
                                    {{ auth()->user()->initials }}
                                </div>
                            @endif
                            <span class="hidden md:block text-sm font-medium text-gray-700">{{ auth()->user()->first_name ?? auth()->user()->name }}</span>
                            <svg class="hidden md:block w-4 h-4 text-gray-400 transition-transform" :class="userMenuOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <!-- Dropdown Menu -->
                        <div x-show="userMenuOpen"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             @click.outside="userMenuOpen = false"
                             x-cloak
                             class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-100 py-2 z-50">

                            <!-- User info header -->
                            <div class="px-4 py-2 border-b border-gray-100">
                                <p class="text-sm font-semibold text-gray-900">{{ auth()->user()->first_name ?? auth()->user()->name }} {{ auth()->user()->last_name ?? '' }}</p>
                                <p class="text-xs text-gray-500">{{ auth()->user()->email }}</p>
                            </div>

                            <!-- Role-based menu items -->
                            <div class="py-1">
                                @if(auth()->user()->role === 'admin')
                                    <a href="{{ route('admin.dashboard') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-zapmed-600 transition-colors">
                                        <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                        Dashboard
                                    </a>
                                    <a href="{{ route('admin.users') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-zapmed-600 transition-colors">
                                        <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                        User Management
                                    </a>
                                    <a href="{{ route('admin.appointments') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-zapmed-600 transition-colors">
                                        <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        Appointments
                                    </a>
                                    <a href="{{ route('admin.analytics') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-zapmed-600 transition-colors">
                                        <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                        Analytics
                                    </a>
                                @elseif(auth()->user()->role === 'doctor')
                                    <a href="{{ route('doctor.dashboard') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-zapmed-600 transition-colors">
                                        <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                        Dashboard
                                    </a>
                                    <a href="{{ route('doctor.patients') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-zapmed-600 transition-colors">
                                        <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        My Patients
                                    </a>
                                    <a href="{{ route('doctor.prescriptions') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-zapmed-600 transition-colors">
                                        <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        Prescriptions
                                    </a>
                                    <a href="{{ route('doctor.messages') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-zapmed-600 transition-colors">
                                        <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                        Messages
                                    </a>
                                @else
                                    <a href="{{ route('dashboard') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-zapmed-600 transition-colors">
                                        <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                        Dashboard
                                    </a>
                                    <a href="{{ route('patient.appointments') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-zapmed-600 transition-colors">
                                        <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        My Appointments
                                    </a>
                                    <a href="{{ route('patient.prescriptions') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-zapmed-600 transition-colors">
                                        <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        My Prescriptions
                                    </a>
                                    <a href="{{ route('patient.subscription') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-zapmed-600 transition-colors">
                                        <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                        My Subscription
                                    </a>
                                    <a href="{{ route('patient.progress') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-zapmed-600 transition-colors">
                                        <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                        My Progress
                                    </a>
                                @endif
                            </div>

                            <!-- Profile & Logout -->
                            <div class="border-t border-gray-100 py-1">
                                <a href="{{ route('profile') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-zapmed-600 transition-colors">
                                    <svg class="w-4 h-4 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    My Profile
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                        Log Out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="hidden sm:inline-block text-sm font-medium text-gray-600 hover:text-zapmed-700 border border-zapmed-600 px-4 py-2 rounded-lg transition-colors">Log in</a>
                    <a href="{{ route('register') }}" class="bg-zapmed-600 hover:bg-zapmed-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                        Start Assessment
                    </a>
                @endauth
                <!-- Mobile menu button -->
                <button @click="mobileOpen = !mobileOpen" class="md:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path x-show="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        <path x-show="mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mega Menu Dropdown (Desktop) -->
    <div
        x-show="megaOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        @mouseleave="megaOpen = false"
        x-cloak
        class="hidden md:block absolute top-full left-0 right-0 bg-white border-b border-gray-200 shadow-lg"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-8">
                @foreach(config('treatments') as $categorySlug => $category)
                    <div class="group">
                        <h3 class="text-sm font-semibold text-gray-900 mb-2 relative inline-block">
                            {{ $category['name'] }}
                            <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-zapmed-600 group-hover:w-full transition-all duration-300"></span>
                        </h3>
                        <!-- Category Image (rectangular, small) -->
                        <div class="mb-3 rounded-lg overflow-hidden h-16 bg-gray-100">
                            <img src="{{ $category['image'] }}" alt="{{ $category['name'] }} treatments - Zapmed online telehealth South Africa" class="w-full h-full object-cover" loading="lazy">
                        </div>
                        <ul class="space-y-0">
                            @foreach($category['treatments'] as $treatmentSlug => $treatment)
                                <li class="border-b border-gray-100 last:border-b-0">
                                    <a href="{{ route('treatments.show', $treatmentSlug) }}" class="flex items-center gap-1.5 text-sm text-gray-600 hover:text-zapmed-600 transition-colors py-1.5">
                                        <span class="text-zapmed-600 text-xs">›</span>
                                        {{ $treatment['name'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div x-show="mobileOpen" x-transition x-cloak class="md:hidden bg-white border-b border-gray-200 shadow-lg max-h-[80vh] overflow-y-auto">
        <div class="px-4 py-4 space-y-4">
            <a href="/#how-it-works" class="block text-sm font-medium text-gray-600 hover:text-gray-900">How It Works</a>
            <a href="/#pricing" class="block text-sm font-medium text-gray-600 hover:text-gray-900">Pricing</a>
            <a href="/#doctors" class="block text-sm font-medium text-gray-600 hover:text-gray-900">Our Doctors</a>
            <a href="{{ route('faq') }}" class="block text-sm font-medium text-gray-600 hover:text-gray-900">FAQ</a>
            <a href="{{ route('blog') }}" class="block text-sm font-medium text-gray-600 hover:text-gray-900">Blog</a>
            <div class="border-t border-gray-100 pt-4">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Treatments</p>
                @foreach(config('treatments') as $categorySlug => $category)
                    <div class="mb-2" x-data="{ open: false }">
                        <button @click="open = !open" class="w-full flex items-center justify-between py-2 text-sm font-semibold text-gray-900">
                            {{ $category['name'] }}
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="open" x-collapse x-cloak class="pl-3 border-l-2 border-zapmed-100">
                            @foreach($category['treatments'] as $treatmentSlug => $treatment)
                                <a href="{{ route('treatments.show', $treatmentSlug) }}" class="block text-sm text-gray-600 hover:text-zapmed-600 py-1.5">
                                    {{ $treatment['name'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</nav>
