<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FAQ - Zapmed Help Centre</title>
    <meta name="description" content="Frequently asked questions about Zapmed's online doctor consultations, medication delivery, payments, and treatments.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=noto-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white">

    @include('partials.public-nav')

    <!-- Hero -->
    <section class="mt-16 pt-16 pb-12 bg-gradient-to-b from-zapmed-50 to-white px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900">Help Centre</h1>
            <p class="mt-4 text-lg text-gray-500">Find answers to common questions about Zapmed.</p>
        </div>
    </section>

    <!-- FAQ Layout: Search + Sidebar + Content -->
    <section class="py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl mx-auto" x-data="{ activeCategory: '{{ array_key_first(config('faq')) }}', search: '' }">

            <!-- Search -->
            <div class="max-w-xl mx-auto mb-10">
                <div class="relative">
                    <input x-model="search" type="text"
                        placeholder="Search for answers..."
                        class="w-full rounded-xl border-gray-200 shadow-sm pl-11 pr-4 py-3 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    <svg class="absolute left-4 top-3.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

                <!-- Left: Categories Sidebar -->
                <div class="lg:col-span-1">
                    <div class="sticky top-24 bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-100">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Categories</p>
                        </div>
                        <div class="divide-y divide-gray-50">
                            @foreach(config('faq') as $slug => $category)
                            <button @click="activeCategory = '{{ $slug }}'"
                                class="w-full flex items-center gap-3 px-4 py-3.5 text-left transition-all text-sm"
                                :class="activeCategory === '{{ $slug }}' ? 'bg-zapmed-50 text-zapmed-700 font-semibold border-l-3 border-zapmed-500' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                                    :class="activeCategory === '{{ $slug }}' ? 'bg-zapmed-100' : 'bg-gray-100'">
                                    <svg class="w-4 h-4" :class="activeCategory === '{{ $slug }}' ? 'text-zapmed-600' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $category['icon'] }}"/>
                                    </svg>
                                </div>
                                <span>{{ $category['name'] }}</span>
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Right: Questions & Answers -->
                <div class="lg:col-span-3">
                    @foreach(config('faq') as $slug => $category)
                    <div x-show="activeCategory === '{{ $slug }}' || search.length > 0" x-transition x-cloak>
                        <div class="mb-6">
                            <h2 class="text-xl font-bold text-gray-900">{{ $category['name'] }}</h2>
                            <p class="text-sm text-gray-500 mt-1">{{ $category['description'] }}</p>
                        </div>

                        <div class="space-y-3" x-data="{ open: null }">
                            @foreach($category['questions'] as $index => $faq)
                            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm"
                                x-show="!search || '{{ strtolower(addslashes($faq['q'])) }}'.includes(search.toLowerCase()) || '{{ strtolower(addslashes($faq['a'])) }}'.includes(search.toLowerCase())">
                                <button @click="open = open === {{ $index }} ? null : {{ $index }}"
                                    class="w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 transition-colors">
                                    <span class="text-sm font-medium text-gray-900 pr-4">{{ $faq['q'] }}</span>
                                    <svg class="w-5 h-5 text-gray-400 flex-shrink-0 transition-transform duration-200"
                                        :class="open === {{ $index }} ? 'rotate-180' : ''"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="open === {{ $index }}" x-transition x-cloak
                                    class="px-5 pb-5 border-t border-gray-50">
                                    <p class="text-sm text-gray-600 leading-relaxed pt-3">{{ $faq['a'] }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>

            </div>

            <!-- Still need help? -->
            <div class="mt-16 text-center p-8 bg-zapmed-50 rounded-2xl border border-zapmed-100">
                <h3 class="text-lg font-bold text-gray-900">Still have questions?</h3>
                <p class="text-sm text-gray-600 mt-2">Our team is here to help.</p>
                <a href="mailto:support@zapmed.co.za" class="inline-flex items-center mt-4 px-5 py-2.5 bg-zapmed-600 hover:bg-zapmed-700 text-white rounded-lg text-sm font-semibold transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Email Support
                </a>
            </div>
        </div>
    </section>

    @include('partials.public-footer')
</body>
</html>
