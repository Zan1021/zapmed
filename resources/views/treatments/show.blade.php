<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $treatment['name'] }} - Zapmed</title>
    <meta name="description" content="{{ $treatment['category_description'] }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white">

    @include('partials.public-nav')

    <!-- Hero Section -->
    <section class="pt-32 pb-16 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-zapmed-50/50 to-white">
        <div class="max-w-4xl mx-auto text-center">
            <span class="inline-flex items-center bg-zapmed-50 text-zapmed-700 px-3 py-1 rounded-full text-xs font-medium mb-4">
                {{ $treatment['category'] }}
            </span>
            <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 tracking-tight">
                {{ $treatment['name'] }}
            </h1>

            @if(View::exists('treatments.content.' . $slug . '-intro'))
                <div class="mt-6 text-lg text-gray-500 max-w-2xl mx-auto leading-relaxed">
                    @include('treatments.content.' . $slug . '-intro')
                </div>
            @elseif(isset($treatment['tagline']))
                <p class="mt-4 text-xl text-zapmed-700 font-medium">{{ $treatment['tagline'] }}</p>
                <p class="mt-4 text-lg text-gray-500 max-w-2xl mx-auto leading-relaxed">
                    {{ $treatment['description'] ?? $treatment['category_description'] }}
                </p>
            @else
                <p class="mt-6 text-lg text-gray-500 max-w-2xl mx-auto leading-relaxed">
                    {{ $treatment['category_description'] }}
                </p>
            @endif

            <div class="mt-8">
                <span class="text-2xl font-bold text-gray-900">{{ $treatment['price'] }}</span>
            </div>
            <div class="mt-6">
                <a href="{{ route('assessment.start', $slug) }}" class="inline-block bg-zapmed-600 hover:bg-zapmed-700 text-white px-8 py-4 rounded-xl text-base font-semibold transition-all shadow-lg shadow-zapmed-200 hover:shadow-xl">
                    Start Your Assessment
                </a>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 text-center mb-12">How It Works</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-zapmed-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-zapmed-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">1. Complete Assessment</h3>
                    <p class="text-sm text-gray-500">Fill out a quick online health questionnaire. No waiting rooms, completely confidential.</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 bg-zapmed-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-zapmed-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">2. Doctor Review</h3>
                    <p class="text-sm text-gray-500">A licensed SA doctor reviews your assessment and prescribes the most suitable treatment.</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 bg-zapmed-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-zapmed-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">3. Discreet Delivery</h3>
                    <p class="text-sm text-gray-500">Your medication is dispensed by a regulated pharmacy and delivered to your door in unbranded packaging.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Treatment-Specific Content -->
    @if(View::exists('treatments.content.' . $slug))
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50">
            <div class="max-w-4xl mx-auto">
                @include('treatments.content.' . $slug)
            </div>
        </section>
    @endif

    <!-- FAQ Section -->
    @if(View::exists('treatments.content.' . $slug . '-faq'))
        <section class="py-16 px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 text-center mb-10">Frequently Asked Questions</h2>
                @include('treatments.content.' . $slug . '-faq')
            </div>
        </section>
    @endif

    <!-- CTA Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto bg-gray-900 rounded-3xl p-12 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold text-white">Ready to get started?</h2>
            <p class="mt-4 text-lg text-gray-400">Complete your assessment in just a few minutes — a licensed doctor will review it the same day.</p>
            <a href="{{ route('assessment.start', $slug) }}" class="mt-8 inline-block bg-zapmed-500 hover:bg-zapmed-600 text-white px-8 py-4 rounded-xl text-base font-semibold transition-all shadow-lg">
                Start Your Assessment
            </a>
        </div>
    </section>

    @include('partials.public-footer')

</body>
</html>
