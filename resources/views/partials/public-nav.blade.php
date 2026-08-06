<!-- Navigation -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-lg border-b border-gray-100" x-data="{ megaOpen: false, mobileOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center">
                <a href="/" class="text-2xl font-bold text-gray-900 tracking-tight">zapmed<span class="text-zapmed-500">.</span></a>
            </div>
            <div class="hidden md:flex items-center space-x-8">
                <!-- All Treatments Mega Menu Trigger -->
                <div class="relative" @mouseenter="megaOpen = true" @mouseleave="megaOpen = false">
                    <button class="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors flex items-center gap-1">
                        All Treatments
                        <svg class="w-4 h-4 transition-transform" :class="megaOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                </div>
                <a href="/#how-it-works" class="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">How It Works</a>
                <a href="/#pricing" class="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">Pricing</a>
                <a href="/#doctors" class="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">Our Doctors</a>
            </div>
            <div class="flex items-center space-x-4">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hidden sm:inline-block text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">Log in</a>
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
        @mouseenter="megaOpen = true"
        @mouseleave="megaOpen = false"
        class="hidden md:block absolute top-full left-0 right-0 bg-white border-b border-gray-200 shadow-lg"
    >
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-8">
                @foreach(config('treatments') as $categorySlug => $category)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ $category['name'] }}</h3>
                        <ul class="space-y-2">
                            @foreach($category['treatments'] as $treatmentSlug => $treatment)
                                <li>
                                    <a href="{{ route('treatments.show', $treatmentSlug) }}" class="text-sm text-gray-600 hover:text-zapmed-600 transition-colors">
                                        {{ $treatment['name'] }}
                                    </a>
                                    <span class="block text-xs text-gray-400">{{ $treatment['price'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div x-show="mobileOpen" x-transition class="md:hidden bg-white border-b border-gray-200 shadow-lg">
        <div class="px-4 py-4 space-y-4">
            <a href="/#how-it-works" class="block text-sm font-medium text-gray-600 hover:text-gray-900">How It Works</a>
            <a href="/#pricing" class="block text-sm font-medium text-gray-600 hover:text-gray-900">Pricing</a>
            <a href="/#doctors" class="block text-sm font-medium text-gray-600 hover:text-gray-900">Our Doctors</a>
            <div class="border-t border-gray-100 pt-4">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Treatments</p>
                @foreach(config('treatments') as $categorySlug => $category)
                    <div class="mb-4">
                        <p class="text-sm font-semibold text-gray-900 mb-1">{{ $category['name'] }}</p>
                        @foreach($category['treatments'] as $treatmentSlug => $treatment)
                            <a href="{{ route('treatments.show', $treatmentSlug) }}" class="block text-sm text-gray-600 hover:text-zapmed-600 py-1 pl-3">
                                {{ $treatment['name'] }}
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</nav>
