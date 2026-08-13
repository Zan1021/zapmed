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
    @include('partials.analytics')
    @include('partials.pwa-meta')
</head>
<body class="font-sans antialiased bg-white">

    @include('partials.public-nav')

    <!-- Hero Section (Split Layout) -->
    @php
        $treatmentImageSlug = $contentSlug ?? $slug;
        $treatmentImagePath = public_path("images/treatments/{$treatmentImageSlug}.webp");
        $hasImage = file_exists($treatmentImagePath);
    @endphp
    <section class="pt-32 pb-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">
            <div class="grid grid-cols-1 {{ $hasImage ? 'lg:grid-cols-2' : '' }} gap-12 items-center">
                <div>
                    <span class="inline-flex items-center bg-zapmed-50 text-zapmed-700 px-3 py-1 rounded-full text-xs font-medium mb-4">
                        {{ $treatment['category'] }}
                    </span>
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-gray-900 tracking-tight leading-tight">
                        {{ $treatment['name'] }}
                    </h1>

                    @if(View::exists('treatments.content.' . ($contentSlug ?? $slug) . '-intro'))
                        <div class="mt-6 text-lg text-gray-600 leading-relaxed">
                            @include('treatments.content.' . ($contentSlug ?? $slug) . '-intro')
                        </div>
                    @elseif(isset($treatment['tagline']))
                        <p class="mt-4 text-xl text-zapmed-700 font-medium">{{ $treatment['tagline'] }}</p>
                        <p class="mt-4 text-lg text-gray-600 leading-relaxed">
                            {{ $treatment['description'] ?? $treatment['category_description'] }}
                        </p>
                    @else
                        <p class="mt-6 text-lg text-gray-600 leading-relaxed">
                            {{ $treatment['category_description'] }}
                        </p>
                    @endif

                    <div class="mt-8">
                        @if($treatment['price_monthly'] && $treatment['price_once_off'])
                            <div class="inline-flex items-center gap-3 bg-gray-50 rounded-xl p-1">
                                <div class="bg-white rounded-lg px-4 py-2 shadow-sm border border-zapmed-200">
                                    <p class="text-xs text-gray-500">Monthly</p>
                                    <p class="text-lg font-bold text-zapmed-600">{{ currency($treatment['price_monthly']) }}<span class="text-xs font-normal text-gray-400">/pm</span></p>
                                </div>
                                <span class="text-xs text-gray-400">or</span>
                                <div class="bg-white rounded-lg px-4 py-2 border border-gray-200">
                                    <p class="text-xs text-gray-500">Once-off</p>
                                    <p class="text-lg font-bold text-gray-900">{{ currency($treatment['price_once_off']) }}</p>
                                </div>
                            </div>
                        @elseif($treatment['price_monthly'])
                            <span class="text-2xl font-bold text-gray-900">{{ currency($treatment['price_monthly']) }}<span class="text-base font-normal text-gray-500">/month</span></span>
                        @elseif($treatment['price_once_off'])
                            <span class="text-2xl font-bold text-gray-900">{{ currency($treatment['price_once_off']) }}</span>
                        @else
                            <span class="text-2xl font-bold text-gray-900">{{ $treatment['price'] }}</span>
                        @endif
                    </div>
                    <div class="mt-6">
                        <a href="{{ route('patient.book', ['category' => $treatment['category_slug'], 'treatment' => $slug]) }}" class="inline-block bg-zapmed-600 hover:bg-zapmed-700 text-white px-8 py-4 rounded-xl text-base font-semibold transition-all shadow-lg">
                            Start Your Assessment
                        </a>
                    </div>
                </div>
                @if($hasImage)
                <div class="hidden lg:block">
                    <img src="{{ asset("images/treatments/{$treatmentImageSlug}.webp") }}" alt="{{ $treatment['name'] }}" class="w-full h-auto object-cover rounded-2xl">
                </div>
                @endif
            </div>
        </div>
    </section>

    <!-- How It Works -->
    @include('partials.how-it-works', ['bookingUrl' => route('patient.book', ['category' => $treatment['category_slug'], 'treatment' => $slug])])

    <!-- Treatment-Specific Content -->
    @if(View::exists('treatments.content.' . ($contentSlug ?? $slug)))
        <section class="py-16 px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                @include('treatments.content.' . ($contentSlug ?? $slug))
            </div>
        </section>
    @endif

    <!-- FAQ Section -->
    @if(View::exists('treatments.content.' . ($contentSlug ?? $slug) . '-faq'))
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50">
            <div class="max-w-3xl mx-auto">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 text-center mb-10">Frequently Asked Questions</h2>
                @include('treatments.content.' . ($contentSlug ?? $slug) . '-faq')
            </div>
        </section>
    @endif

    <!-- CTA Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto bg-gray-900 rounded-3xl p-12 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold text-white">Ready to get started?</h2>
            <p class="mt-4 text-lg text-gray-400">Complete your assessment in just a few minutes — a licensed doctor will review it the same day.</p>
            <a href="{{ route('patient.book', ['category' => $treatment['category_slug'], 'treatment' => $slug]) }}" class="mt-8 inline-block bg-zapmed-500 hover:bg-zapmed-600 text-white px-8 py-4 rounded-xl text-base font-semibold transition-all shadow-lg">
                Start Your Assessment
            </a>
        </div>
    </section>

    @include('partials.public-footer')

</body>
</html>
