<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Zapmed - Online Doctor-Guided Medical Treatments</title>
    <meta name="description" content="Doctor-led online treatment for weight loss, sexual health, skincare, and chronic care. Prescribed by licensed SA doctors, delivered to your door.">

    @include('partials.fonts')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.analytics')
    @include('partials.pwa-meta')
</head>
<body class="font-sans antialiased bg-white">

    @include('partials.public-nav')

    <!-- Hero -->
    <section class="mt-16 pb-16 lg:pb-20 relative overflow-hidden">
        {{-- video background: autoplay, muted, looped, poster = static hero for first frame / fallback --}}
        <video class="absolute inset-0 w-full h-full object-cover" autoplay muted loop playsinline
               poster="/images/hero-coastal-wellness-3.jpg">
            <source src="/videos/hero-bg.mp4" type="video/mp4">
        </video>
        <div class="absolute inset-0 bg-gradient-to-r from-black/50 via-black/20 to-transparent"></div>
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 lg:pt-28">
            <div class="flex justify-start">
                <div class="text-left max-w-xl lg:max-w-2xl">
                    <p class="text-xs sm:text-sm font-medium uppercase tracking-[0.25em] text-sage-200 mb-6">
                        Better health. Brighter tomorrows.
                    </p>
                    <h1 class="font-serif font-normal text-4xl sm:text-5xl lg:text-6xl text-white tracking-wide leading-[1.05]">
                        Your health journey, supported
                        <span class="block mt-2 heading-italic text-sage-300">every step of the way.</span>
                    </h1>
                    <p class="mt-6 text-lg text-gray-100/85 leading-relaxed max-w-lg">
                        Personalised treatment, expert care and ongoing support — so you can feel your best, inside and out.
                    </p>
                    <div class="mt-8">
                        <a href="{{ route('register') }}" class="inline-flex items-center gap-3 bg-white hover:bg-sage-100 text-zapmed-900 px-8 py-4 rounded-full text-base font-semibold transition-colors shadow-lg">
                            Start your free assessment
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    </div>

                    {{-- Trust row --}}
                    <div class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-4 text-white">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-6 h-6 text-sage-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span class="text-sm font-medium leading-tight">Clinically<br>supported</span>
                        </div>
                        <span class="h-9 w-px bg-white/20"></span>
                        <div class="flex items-center gap-2.5">
                            <svg class="w-6 h-6 text-sage-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <span class="text-sm font-medium leading-tight">Private<br>and secure</span>
                        </div>
                        <span class="h-9 w-px bg-white/20"></span>
                        <div class="flex items-center gap-2.5">
                            <svg class="w-6 h-6 text-sage-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                            <span class="text-sm font-medium leading-tight">Ongoing<br>support</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Feature strip (under hero) — small, inline, no boxes -->
    <section id="feature-cards" class="w-full px-4 sm:px-6 lg:px-8 py-6 bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-x-6 gap-y-5">
            @foreach ([
                ['icon-consultations.svg', 'Online Consultations', 'Speak to a registered doctor from home.'],
                ['icon-doctors.svg', 'SA-Registered Doctors', 'Expert, compassionate care you can trust.'],
                ['icon-treatment.svg', 'Prescribed Treatments', 'Delivered discreetly to your door.'],
                ['icon-support.svg', 'Ongoing Support', 'Guidance when you need it.'],
                ['icon-progress.svg', 'Track Your Progress', 'See real progress along the way.'],
                ['icon-privacy.svg', 'Discreet & Private', 'Your health, your information, protected.'],
            ] as $card)
                {{-- inline item: icon + heading + description, no box/background --}}
                <div class="flex items-start gap-3">
                    <img src="/images/features/{{ $card[0] }}" alt="" class="w-10 h-10 mt-0.5 shrink-0 opacity-80" aria-hidden="true">
                    <div>
                        <h3 class="text-xs font-semibold text-brand leading-tight">{{ $card[1] }}</h3>
                        <p class="mt-0.5 text-[11px] text-gray-500 leading-snug">{{ $card[2] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- AI Health Assistant -->
    <section class="px-4 sm:px-6 lg:px-8 py-10 bg-white">
        <div class="max-w-2xl mx-auto" x-data="aiChatWidget()">
            <div class="bg-white rounded-2xl shadow-lg border border-zapmed-900/10 overflow-hidden">
                {{-- header uses the hero's "every step of the way." sage colour (sage-300) --}}
                <div class="p-5 bg-sage-300">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 bg-white rounded-lg flex items-center justify-center overflow-hidden">
                            <img src="/images/aiavatar.webp" alt="AI Doctor" class="w-9 h-9">
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-brand">AI Health Assistant</h3>
                            <p class="text-xs text-brand/70">Ask me anything about our treatments</p>
                        </div>
                    </div>
                </div>

                <!-- Response area -->
                <template x-if="messages.length > 0">
                    <div class="p-5 bg-sage-100/40 border-b border-sage-100 max-h-[300px] overflow-y-auto space-y-3">
                        <template x-for="(msg, i) in messages" :key="i">
                            <div>
                                <template x-if="msg.role === 'user'">
                                    <div class="flex justify-end">
                                        <div class="bg-zapmed-900 text-white rounded-xl rounded-tr-sm px-3 py-2 max-w-[85%]">
                                            <p class="text-sm" x-text="msg.text"></p>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="msg.role === 'ai'">
                                    <div class="flex items-start space-x-2">
                                        <div class="w-7 h-7 rounded-full flex-shrink-0 overflow-hidden">
                                            <img src="/images/aiavatar.webp" alt="AI" class="w-7 h-7">
                                        </div>
                                        <div class="max-w-[85%]">
                                            <div class="bg-white rounded-xl rounded-tl-sm px-3 py-2 border border-sage-200">
                                                <p class="text-sm text-gray-700 whitespace-pre-line" x-text="msg.text"></p>
                                            </div>
                                            <template x-if="msg.treatmentUrl">
                                                <a :href="msg.treatmentUrl" class="mt-2 inline-flex items-center px-3 py-1.5 bg-zapmed-900 hover:bg-zapmed-950 text-white text-xs font-medium rounded-lg transition-colors">
                                                    <span x-text="'View ' + msg.treatmentName + ' →'"></span>
                                                </a>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Input -->
                <form @submit.prevent="sendMessage" class="p-4 flex items-center space-x-3">
                    <input x-model="message" type="text"
                        class="flex-1 rounded-xl border-sage-200 text-sm focus:border-zapmed-700 focus:ring-zapmed-700 placeholder-gray-400"
                        placeholder="e.g. I want to lose weight, I need help with acne..."
                        :disabled="loading" maxlength="500">
                    <button type="submit" :disabled="loading || !message.trim()"
                        class="flex-shrink-0 w-10 h-10 bg-zapmed-900 hover:bg-zapmed-950 disabled:bg-gray-300 text-white rounded-xl flex items-center justify-center transition-colors">
                        <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                        <svg x-show="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </form>
            </div>
            <p class="text-center text-xs text-gray-400 mt-3">Powered by AI. Not a substitute for medical advice — always consult a doctor.</p>
        </div>
    </section>


    <!-- Services -->
    <section id="services" class="py-20 lg:py-24 bg-white px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-14">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-zapmed-700 mb-4">Our Treatments</p>
                <h2 class="heading text-brand">
                    Doctor-led care for <span class="heading-italic text-zapmed-700">a healthier you.</span>
                </h2>
                <p class="mt-4 text-lg text-gray-500 max-w-2xl mx-auto">Personalised, discreet and delivered, for real life.</p>
            </div>

            @php
                $categoryIcons = [
                    'weight-loss'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 3a9 9 0 100 18 9 9 0 000-18zm0 0v4m0 0l2.5-2.5M12 7L9.5 4.5"/>',
                    'skincare'       => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 3c2.5 3 4 5.5 4 8a4 4 0 11-8 0c0-2.5 1.5-5 4-8z"/>',
                    'womens-health'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 3a4.5 4.5 0 100 9 4.5 4.5 0 000-9zm0 9v8m-3-3h6"/>',
                    'mens-health'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M10 14a4 4 0 100-8 4 4 0 000 8zm2.8-6.8L19 4m0 0h-4m4 0v4"/>',
                    'sexual-health'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 20s-7-4.5-7-9.5A3.5 3.5 0 0112 7a3.5 3.5 0 017 3.5C19 15.5 12 20 12 20z"/>',
                    'general-health' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3zm-2 8h4m-2-2v4"/>',
                ];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-5">
                @foreach(config('treatments') as $categorySlug => $category)
                <div class="group flex flex-col">
                    {{-- Image card with icon badge + serif category name overlaid at the bottom --}}
                    <div class="relative rounded-2xl overflow-hidden aspect-[4/5] shadow-sm">
                        <img src="{{ $category['image'] }}" alt="{{ $category['name'] }} treatments - Zapmed online telehealth South Africa"
                             class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">
                        {{-- dark green gradient so the badge + name read clearly --}}
                        <div class="absolute inset-0 bg-gradient-to-t from-brand/85 via-brand/20 to-transparent"></div>
                        <div class="absolute inset-x-0 bottom-0 p-4 flex items-center gap-3">
                            <span class="shrink-0 w-11 h-11 rounded-full bg-sage-100 flex items-center justify-center text-brand">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $categoryIcons[$category['icon'] ?? $categorySlug] ?? '' !!}</svg>
                            </span>
                            <h3 class="text-lg font-serif text-white leading-tight">{{ $category['name'] }}</h3>
                        </div>
                    </div>
                    {{-- treatment links --}}
                    <ul class="mt-2">
                        @foreach($category['treatments'] as $treatmentSlug => $treatment)
                            <li class="border-b border-gray-100">
                                <a href="{{ route('treatments.show', $treatmentSlug) }}"
                                   class="flex items-center justify-between gap-2 text-sm text-gray-700 hover:text-zapmed-700 py-2.5 transition-colors">
                                    <span>{{ $treatment['name'] }}</span>
                                    <svg class="w-4 h-4 text-gray-400 group-hover:text-zapmed-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </div>
        </div>
    </section>


    @include('partials.how-it-works', ['bookingUrl' => '/treatments'])

    <!-- Pricing -->
    <section id="pricing" class="py-20 bg-gray-50 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="heading text-brand">Get your personalised consultation</h2>
                <p class="mt-4 text-lg text-gray-500">Transparent pricing. No hidden fees. Cancel anytime.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <!-- Subscription -->
                <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">Monthly Subscription</h3>
                    <p class="text-sm text-gray-500 mt-1">Ongoing treatment & support</p>
                    <div class="mt-6">
                        <span class="text-4xl font-bold text-gray-900">R220</span>
                        <span class="text-gray-500">/month</span>
                    </div>
                    <ul class="mt-6 space-y-3 text-sm text-gray-600">
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Doctor consultation included</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Prescription fees included</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Free delivery nationwide</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Cancel anytime</li>
                    </ul>
                    <a href="{{ route('register') }}" class="mt-8 block w-full text-center bg-gray-100 hover:bg-gray-200 text-gray-800 py-3 rounded-lg text-sm font-semibold transition-colors">
                        Get Started
                    </a>
                </div>

                <!-- Weight Loss (featured) -->
                <div class="bg-white rounded-2xl p-8 shadow-lg border-2 border-brand relative">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-brand text-white text-xs font-semibold px-3 py-1 rounded-full">Most Popular</div>
                    <h3 class="text-lg font-semibold text-gray-900">Weight Loss Programme</h3>
                    <p class="text-sm text-gray-500 mt-1">Doctor + Health Coach</p>
                    <div class="mt-6">
                        <span class="text-4xl font-bold text-gray-900">R450</span>
                        <span class="text-gray-500">/month</span>
                    </div>
                    <ul class="mt-6 space-y-3 text-sm text-gray-600">
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Partner Doctor consultation</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Dedicated Health Coach</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>GLP-1 medication (if prescribed)</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Ongoing WhatsApp support</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Discreet delivery</li>
                    </ul>
                    <p class="mt-4 text-xs text-gray-400">Medication costs billed separately based on prescribed dosage.</p>
                    <a href="{{ route('register') }}" class="mt-6 block w-full text-center bg-brand hover:bg-brand-hover text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                        Start Assessment
                    </a>
                </div>

                <!-- Once-off -->
                <div class="bg-white rounded-2xl p-8 shadow-sm border border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">Once-Off Consultation</h3>
                    <p class="text-sm text-gray-500 mt-1">Single GP or specialist visit</p>
                    <div class="mt-6">
                        <span class="text-4xl font-bold text-gray-900">R450</span>
                        <span class="text-gray-500">once-off</span>
                    </div>
                    <ul class="mt-6 space-y-3 text-sm text-gray-600">
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Virtual doctor consultation</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>E-prescription emailed</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Sick notes & medical certificates</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Optional delivery add-on</li>
                    </ul>
                    <a href="{{ route('register') }}" class="mt-8 block w-full text-center bg-gray-100 hover:bg-gray-200 text-gray-800 py-3 rounded-lg text-sm font-semibold transition-colors">
                        Book Now
                    </a>
                </div>
            </div>
        </div>
    </section>


    @include('partials.calculators')

    <!-- Doctor-Trusted Solutions Carousel -->
    <section class="py-20 bg-gradient-to-b from-gray-50 to-white overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-14">
            <div class="text-center">
                <h2 class="heading text-brand">Doctor-Trusted Solutions</h2>
                <p class="mt-3 text-lg text-gray-500">Personalised For You</p>
            </div>
        </div>

        <div x-data="trustCarousel()" class="relative px-4 sm:px-6">
            <!-- Cards container - full width -->
            <div class="overflow-hidden">
                <div class="flex transition-transform duration-700 ease-out" :style="'transform: translateX(-' + (current * slideWidth) + '%)'">
                    <!-- Card 1 -->
                    <div class="w-full sm:w-1/2 lg:w-1/3 flex-shrink-0 px-3">
                        <div class="h-full rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow relative" style="min-height: 380px; background: url('/images/trust-slides/professional-female-doctor-banner.webp') right center / cover no-repeat;">
                            <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/50 to-transparent"></div>
                            <div class="relative h-full p-6 sm:p-8 flex flex-col justify-center max-w-full sm:max-w-[55%]">
                                <h3 class="text-lg font-bold text-white mb-2">Prescribed by Licensed Providers</h3>
                                <p class="text-sm text-white/80 leading-relaxed">Every medication prescribed by HPCSA-registered doctors and dispensed by regulated pharmacy partners.</p>
                                <a href="{{ route('register') }}" class="mt-4 inline-block bg-brand hover:bg-brand-hover text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors w-fit">Start Assessment</a>
                            </div>
                        </div>
                    </div>
                    <!-- Card 2 -->
                    <div class="w-full sm:w-1/2 lg:w-1/3 flex-shrink-0 px-3">
                        <div class="h-full rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow relative" style="min-height: 380px; background: url('/images/trust-slides/Sliders_Images65.jpg') right center / cover no-repeat;">
                            <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/50 to-transparent"></div>
                            <div class="relative h-full p-6 sm:p-8 flex flex-col justify-center max-w-full sm:max-w-[55%]">
                                <h3 class="text-lg font-bold text-white mb-2">Qualified SA Doctors</h3>
                                <p class="text-sm text-white/80 leading-relaxed">Fully registered with the Health Professions Council with experience in telehealth and chronic care.</p>
                                <a href="{{ route('register') }}" class="mt-4 inline-block bg-brand hover:bg-brand-hover text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors w-fit">Start Assessment</a>
                            </div>
                        </div>
                    </div>
                    <!-- Card 3 -->
                    <div class="w-full sm:w-1/2 lg:w-1/3 flex-shrink-0 px-3">
                        <div class="h-full rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow relative" style="min-height: 380px; background: url('/images/trust-slides/Sliders_Images909.jpg') right center / cover no-repeat;">
                            <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/50 to-transparent"></div>
                            <div class="relative h-full p-6 sm:p-8 flex flex-col justify-center max-w-full sm:max-w-[55%]">
                                <h3 class="text-lg font-bold text-white mb-2">Private & Confidential</h3>
                                <p class="text-sm text-white/80 leading-relaxed">POPIA compliant. Encrypted records. Discreet, unbranded packaging. Your health stays your business.</p>
                                <a href="{{ route('register') }}" class="mt-4 inline-block bg-brand hover:bg-brand-hover text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors w-fit">Start Assessment</a>
                            </div>
                        </div>
                    </div>
                    <!-- Card 4 -->
                    <div class="w-full sm:w-1/2 lg:w-1/3 flex-shrink-0 px-3">
                        <div class="h-full rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow relative" style="min-height: 380px; background: url('/images/trust-slides/healthy-nutrition-meal-prep-containers.webp') right center / cover no-repeat;">
                            <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/50 to-transparent"></div>
                            <div class="relative h-full p-6 sm:p-8 flex flex-col justify-center max-w-full sm:max-w-[55%]">
                                <h3 class="text-lg font-bold text-white mb-2">Fast Delivery Nationwide</h3>
                                <p class="text-sm text-white/80 leading-relaxed">Medication delivered to your door in 1-3 business days. All 9 provinces. Plain packaging, no questions asked.</p>
                                <a href="{{ route('register') }}" class="mt-4 inline-block bg-brand hover:bg-brand-hover text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors w-fit">Start Assessment</a>
                            </div>
                        </div>
                    </div>
                    <!-- Card 5 -->
                    <div class="w-full sm:w-1/2 lg:w-1/3 flex-shrink-0 px-3">
                        <div class="h-full rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow relative" style="min-height: 380px; background: url('/images/trust-slides/professional-female-doctor-banner.webp') right center / cover no-repeat;">
                            <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/50 to-transparent"></div>
                            <div class="relative h-full p-6 sm:p-8 flex flex-col justify-center max-w-full sm:max-w-[55%]">
                                <h3 class="text-lg font-bold text-white mb-2">Ongoing Support & Care</h3>
                                <p class="text-sm text-white/80 leading-relaxed">Continuous check-ins, dose adjustments, and a dedicated health coach to guide you every step of the way.</p>
                                <a href="{{ route('register') }}" class="mt-4 inline-block bg-brand hover:bg-brand-hover text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors w-fit">Start Assessment</a>
                            </div>
                        </div>
                    </div>
                    <!-- Card 6 -->
                    <div class="w-full sm:w-1/2 lg:w-1/3 flex-shrink-0 px-3">
                        <div class="h-full rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow relative" style="min-height: 380px; background: url('/images/trust-slides/Sliders_Images65.jpg') right center / cover no-repeat;">
                            <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/50 to-transparent"></div>
                            <div class="relative h-full p-6 sm:p-8 flex flex-col justify-center max-w-full sm:max-w-[55%]">
                                <h3 class="text-lg font-bold text-white mb-2">Affordable & Transparent</h3>
                                <p class="text-sm text-white/80 leading-relaxed">No hidden fees. Clear pricing from R220/month. Cancel anytime. Medical aid invoices provided.</p>
                                <a href="{{ route('register') }}" class="mt-4 inline-block bg-brand hover:bg-brand-hover text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors w-fit">Start Assessment</a>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-center mt-10 space-x-4">
                    <button @click="prev()" class="w-10 h-10 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:text-brand hover:border-sage-300 transition-colors shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <div class="flex space-x-2">
                        <template x-for="i in dots" :key="i">
                            <button @click="goTo(i - 1)" class="w-2.5 h-2.5 rounded-full transition-all duration-300" :class="current === (i - 1) ? 'bg-brand w-6' : 'bg-gray-300'"></button>
                        </template>
                    </div>
                    <button @click="next()" class="w-10 h-10 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:text-brand hover:border-sage-300 transition-colors shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <script>
    function trustCarousel() {
        return {
            current: 0,
            totalSlides: 6,
            interval: null,
            get slideWidth() {
                if (window.innerWidth >= 1024) return 33.333;
                if (window.innerWidth >= 640) return 50;
                return 100;
            },
            get maxSlide() {
                if (window.innerWidth >= 1024) return this.totalSlides - 3;
                if (window.innerWidth >= 640) return this.totalSlides - 2;
                return this.totalSlides - 1;
            },
            get dots() {
                return this.maxSlide + 1;
            },
            init() {
                this.interval = setInterval(() => this.next(), 4000);
            },
            next() {
                this.current = this.current >= this.maxSlide ? 0 : this.current + 1;
            },
            prev() {
                this.current = this.current <= 0 ? this.maxSlide : this.current - 1;
            },
            goTo(index) {
                this.current = index;
            }
        };
    }
    </script>

    <!-- Lose weight for the last time (weight-loss hero banner) -->
    <section id="weight-loss-banner" class="relative overflow-hidden bg-brand min-h-[560px] flex items-center">
        {{-- Background photo (zapmed-weight-loss-background.webp) --}}
        <div class="absolute inset-0 bg-cover bg-center"
             style="background-image: url('/images/weight-loss-banner.webp');">
        </div>
        {{-- Left scrim so copy stays legible over the photo --}}
        <div class="absolute inset-0 bg-gradient-to-r from-brand/85 via-brand/40 to-transparent"></div>

        <div class="relative max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-20">
            <div class="max-w-xl">
                <h2 class="text-5xl sm:text-6xl font-bold text-white leading-[1.05]">
                    Lose weight
                    <span class="block mt-1 font-serif italic font-normal text-sage-300">for the last time.</span>
                </h2>

                <p class="mt-8 text-xs font-semibold uppercase tracking-[0.2em] text-sage-300">Why?</p>
                <p class="mt-3 text-lg text-zapmed-50 leading-relaxed">
                    Weight loss is biology. Keeping it off is support.<br class="hidden sm:block">
                    That's what we're here for.
                </p>

                <button type="button"
                        class="mt-8 inline-flex items-center bg-zapmed-50 hover:bg-white text-zapmed-900 px-8 py-3.5 rounded-full text-sm font-semibold transition-colors shadow-lg">
                    Get Started
                </button>

                {{-- Trust / stat row --}}
                <div class="mt-12 flex flex-wrap items-center gap-x-8 gap-y-6 text-white">
                    <div class="flex items-center gap-3">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-zapmed-200">Over</p>
                            <p class="text-3xl font-bold leading-none">22%</p>
                        </div>
                        <p class="max-w-[11rem] text-xs text-zapmed-100/80 leading-snug">
                            average body-weight loss with tirzepatide 15&nbsp;mg at 72&nbsp;weeks*
                        </p>
                    </div>

                    <span class="hidden sm:block h-10 w-px bg-white/20"></span>

                    <div class="flex items-center gap-3">
                        <svg class="w-7 h-7 text-zapmed-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <p class="max-w-[11rem] text-xs text-zapmed-100/80 leading-snug">
                            SAHPRA-registered medicine when appropriate
                        </p>
                    </div>

                    <span class="hidden sm:block h-10 w-px bg-white/20"></span>

                    <div class="flex items-center gap-3">
                        <svg class="w-7 h-7 text-zapmed-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8h4l3 3v5a1 1 0 01-1 1h-1m-4 0a1 1 0 001 1h2"/>
                        </svg>
                        <p class="max-w-[11rem] text-xs text-zapmed-100/80 leading-snug">
                            Fast, discreet delivery to your door
                        </p>
                    </div>
                </div>

                {{-- Fine print --}}
                <p class="mt-10 max-w-3xl text-[11px] text-zapmed-100/50 leading-relaxed">
                    *SURMOUNT-1: 22.5% average body-weight loss at 72 weeks with tirzepatide 15&nbsp;mg plus dietary and
                    activity support, in adults without diabetes who had obesity or overweight with a weight-related
                    condition. This analysis used results before treatment discontinuation; the estimate accounting for
                    discontinuations was 20.9%. Individual results vary.
                    <a href="{{ route('treatments.show', 'weight-loss') }}" class="underline hover:text-zapmed-200">Study information</a>
                </p>
            </div>
        </div>
    </section>

    <!-- Doctors -->
    <section id="doctors" class="py-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="heading text-brand">Discreet Care for Everyday Health Needs</h2>
                <p class="mt-4 text-lg text-gray-500">Doctor-led online treatment for weight loss, ED, STI care, birth control and more — simple, private, and personalised.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Dr Anke van Zyl -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100" x-data="{ expanded: false }">
                    <div class="w-24 h-24 sm:w-40 sm:h-40 rounded-full overflow-hidden mb-4 border-2 border-zapmed-100">
                        <img src="/images/Dr_Anke.webp" alt="Dr Anke van Zyl" class="w-full h-full object-cover">
                    </div>
                    <h3 class="text-base font-semibold text-gray-900">Dr Anke van Zyl</h3>
                    <p class="text-sm text-zapmed-600 font-medium">MBChB (Stell) | DipObs (CMSA) – Partner Doctor</p>
                    <div class="mt-3">
                        <p class="text-sm text-gray-600 leading-relaxed" :class="expanded ? '' : 'line-clamp-3'">I am a compassionate medical doctor and advocate for patient-centred, inclusive healthcare in South Africa. With experience spanning rural Eastern Cape communities to building Lumiya Health, I combine clinical excellence with a holistic, human approach to care. I have a special interest in women's health, mental wellness, sexual health, aesthetic medicine, emergency care, and preventative health. I lead with integrity, warmth, and empathy, and believe medicine is both a science and an art, grounded in trust, understanding, and genuine connection. I am committed to reimagining primary care by creating safe, inclusive spaces where patients feel heard, supported, and empowered to take ownership of their health.</p>
                        <button @click="expanded = !expanded" class="mt-2 text-sm font-medium text-zapmed-600 hover:text-zapmed-700 transition-colors">
                            <span x-text="expanded ? 'Read less' : 'Read more'"></span>
                        </button>
                    </div>
                </div>

                <!-- Amy Burger -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100" x-data="{ expanded: false }">
                    <div class="w-24 h-24 sm:w-40 sm:h-40 rounded-full overflow-hidden mb-4 border-2 border-zapmed-100">
                        <img src="/images/Dr_Amy.webp" alt="Amy Burger" class="w-full h-full object-cover">
                    </div>
                    <h3 class="text-base font-semibold text-gray-900">Amy Burger</h3>
                    <p class="text-sm text-zapmed-600 font-medium">Wellness Dietitian</p>
                    <div class="mt-3">
                        <p class="text-sm text-gray-600 leading-relaxed" :class="expanded ? '' : 'line-clamp-3'">I'm Amy, a dedicated Registered Dietitian with a strong background in weight management, health technology, and patient-centered care. With experience supporting individuals on their weight loss journeys, I love to combine evidence-based nutrition support with a compassionate, realistic approach to healthy eating. I have worked extensively in the digital health space, helping to build accessible and affordable care that empowers people to take control of their health wherever they are! I'm passionate about supporting patients on their journey towards achieving their health goals and believe that with the right tools, education, and encouragement, lasting change is possible for everyone.</p>
                        <button @click="expanded = !expanded" class="mt-2 text-sm font-medium text-zapmed-600 hover:text-zapmed-700 transition-colors">
                            <span x-text="expanded ? 'Read less' : 'Read more'"></span>
                        </button>
                    </div>
                </div>

                <!-- Dr Frances Earle -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100" x-data="{ expanded: false }">
                    <div class="w-24 h-24 sm:w-40 sm:h-40 rounded-full overflow-hidden mb-4 border-2 border-zapmed-100">
                        <img src="/images/Dr_francis.webp" alt="Dr Frances Earle" class="w-full h-full object-cover">
                    </div>
                    <h3 class="text-base font-semibold text-gray-900">Dr Frances Earle</h3>
                    <p class="text-sm text-zapmed-600 font-medium">MBChB (UP) | DipHIVMan (CMSA) | DipPEC (CMSA)</p>
                    <div class="mt-3">
                        <p class="text-sm text-gray-600 leading-relaxed" :class="expanded ? '' : 'line-clamp-3'">I am a hands-on medical doctor committed to delivering comprehensive, patient-centred care with empathy and insight. I am a well-rounded clinician who focuses on preventative medicine and overall wellbeing. I believe in creating a supportive space where you are heard and actively involved in your healthcare decisions. My interests lie in women's health, chronic disease management, and primary emergency care.</p>
                        <button @click="expanded = !expanded" class="mt-2 text-sm font-medium text-zapmed-600 hover:text-zapmed-700 transition-colors">
                            <span x-text="expanded ? 'Read less' : 'Read more'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How membership works — pricing / plans -->
    <section id="membership" class="px-4 sm:px-6 lg:px-8 py-20 lg:py-24 bg-zapmed-50">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-3xl mx-auto">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zapmed-700">How membership works</p>
                <h2 class="mt-4 text-4xl sm:text-5xl font-bold text-gray-900 leading-tight">
                    A plan for your health.
                    <span class="block mt-1 font-serif italic font-normal text-sage-500">People for your journey.</span>
                </h2>
                <p class="mt-5 text-lg text-gray-600">Subscribe once. Receive medication and support monthly. Cancel anytime.</p>
            </div>

            <div class="mt-14 grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-8 items-start">

                {{-- Phone mockup --}}
                <div class="lg:col-span-3 flex flex-col items-center">
                    <img src="/images/health-coach-call.png" alt="ZapMed Health Coach video call on an iPhone"
                         class="w-[240px] h-auto drop-shadow-2xl" loading="lazy">
                    <p class="mt-4 text-sm text-gray-500 text-center">Your Health Coach, wherever you are.</p>
                </div>

                {{-- Everyday subscriptions --}}
                <div class="lg:col-span-4 flex flex-col h-full">
                    <p class="text-sm font-semibold text-zapmed-800">Everyday subscriptions</p>
                    <p class="mt-2 flex items-baseline gap-1">
                        <span class="text-5xl font-bold text-zapmed-900">R220</span>
                        <span class="text-sm text-gray-500">/ month</span>
                    </p>
                    <div class="mt-6 space-y-3 flex-1">
                        @foreach ([
                            ['Free support, any time.', 'Help whenever you need it.'],
                            ['Free monthly delivery.', 'Fast and discreet, straight to your door.'],
                            ['Repeat prescription fee included.', 'One less thing to arrange.'],
                            ['Skip the pharmacy queue.', 'No awkward waits or trips to collect your medication.'],
                        ] as $feat)
                            <div class="flex items-start justify-between gap-3 bg-zapmed-100/50 rounded-xl px-5 py-4">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $feat[0] }}</p>
                                    <p class="mt-0.5 text-sm text-gray-600">{{ $feat[1] }}</p>
                                </div>
                                <svg class="w-5 h-5 text-zapmed-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="mt-6 w-full bg-zapmed-900 hover:bg-zapmed-950 text-white px-6 py-4 rounded-full text-sm font-semibold transition-colors shadow">
                        Explore subscriptions
                    </button>
                </div>

                {{-- Weight-loss subscriptions --}}
                <div class="lg:col-span-5 flex flex-col h-full">
                    <p class="text-sm font-semibold text-zapmed-800">Weight-loss subscriptions</p>
                    <p class="mt-2 flex items-baseline gap-1">
                        <span class="text-5xl font-bold text-zapmed-900">R450</span>
                        <span class="text-sm text-gray-500">/ month</span>
                    </p>
                    <div class="mt-6 space-y-3 flex-1">
                        @foreach ([
                            ['Free support, any time.', 'Help whenever you need it.'],
                            ['Free monthly delivery.', 'Fast, discreet and private, straight to your door.'],
                            ['Unlimited Health Coach support.', 'A person in your corner, every step.'],
                            ['Nutrition, movement and mindset.', 'Recipes, exercise guides and health courses.'],
                            ['Partner benefits and discounts.', 'More value for your everyday wellbeing.'],
                            ['Your own private community.', 'Connect with people on a similar journey.'],
                        ] as $feat)
                            <div class="flex items-start justify-between gap-3 bg-zapmed-100 rounded-xl px-5 py-4">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $feat[0] }}</p>
                                    <p class="mt-0.5 text-sm text-gray-600">{{ $feat[1] }}</p>
                                </div>
                                <svg class="w-5 h-5 text-zapmed-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="mt-6 w-full bg-zapmed-900 hover:bg-zapmed-950 text-white px-6 py-4 rounded-full text-sm font-semibold transition-colors shadow">
                        Explore weight-loss care
                    </button>
                </div>

            </div>

            <p class="mt-10 text-center text-xs text-gray-500">
                Medication is billed separately and prescribed when appropriate. Confirm current prices when you join.
            </p>
        </div>
    </section>

    <!-- Tailored care — Men's / Women's health split -->
    <section id="tailored-care" class="px-4 sm:px-6 py-16 lg:py-20 bg-white">
        <div class="w-full grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">

            {{-- Men's health (full-bleed image background) --}}
            <div class="relative overflow-hidden rounded-3xl min-h-[460px]">
                {{-- full-card background image --}}
                <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('/images/mens-health-banner.webp');"></div>
                {{-- scrim so the copy stays readable over the photo --}}
                <div class="absolute inset-0 bg-gradient-to-r from-white/90 via-white/70 to-transparent"></div>
                <div class="relative p-8 lg:p-10 max-w-xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zapmed-700">Tailored for Him</p>
                    <h2 class="mt-3 text-4xl sm:text-5xl font-bold text-gray-900">Men's <span class="text-zapmed-700">health</span></h2>
                    <p class="mt-4 text-base text-gray-700 leading-relaxed">
                        Feel stronger, sharper and more confident with discreet, doctor-led treatments for men.
                    </p>

                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ([
                            ['M6.5 6.5h11M6.5 17.5h11M4 9v6M20 9v6M8 7v10M16 7v10', 'Energy & Performance'],
                            ['M19 5l-5 5m5-5h-4m4 0v4M10 14a4 4 0 11-5.657 5.657A4 4 0 0110 14z', 'Testosterone Support'],
                            ['M12 6a3 3 0 00-3 3c0 1 .5 1.5-1 3-1 1-1 3 0 4 .5.5 1.5 1 3 1s2.5-.5 3-1c1-1 1-3 0-4-1.5-1.5-1-2-1-3a3 3 0 00-1-2z', 'Mental Focus & Mood'],
                            ['M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', 'Sexual Health & Confidence'],
                        ] as $card)
                            <div class="flex items-center justify-between gap-3 bg-white/90 backdrop-blur-sm rounded-xl px-4 py-3.5 shadow-sm border border-zapmed-100/60">
                                <div class="flex items-center gap-3">
                                    <svg class="w-6 h-6 text-zapmed-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $card[0] }}"/></svg>
                                    <span class="text-sm font-semibold text-gray-800 leading-tight">{{ $card[1] }}</span>
                                </div>
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" class="mt-8 inline-flex items-center gap-2 bg-zapmed-900 hover:bg-zapmed-950 text-white px-7 py-3.5 rounded-full text-sm font-semibold transition-colors shadow">
                        Explore Men's Health
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </button>
                </div>
            </div>

            {{-- Women's health (full-bleed image background) --}}
            <div class="relative overflow-hidden rounded-3xl min-h-[460px]">
                <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('/images/womens-health-banner.webp');"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-white/90 via-white/70 to-transparent"></div>
                <div class="relative p-8 lg:p-10 max-w-xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zapmed-700">Tailored for Her</p>
                        <h2 class="mt-3 text-4xl sm:text-5xl font-bold text-gray-900">Women's <span class="text-zapmed-700">health</span></h2>
                        <p class="mt-4 text-base text-gray-700 leading-relaxed">
                            Personalised care for every stage of life — from weight loss and hormonal balance to skin, energy and overall wellbeing.
                        </p>

                        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ([
                                ['M12 3C9 7 7 9 7 13a5 5 0 0010 0c0-4-2-6-5-10z', 'Weight Loss & Body Confidence'],
                                ['M9 7a3 3 0 100 6 3 3 0 000-6zM15 11a3 3 0 100 6 3 3 0 000-6zM11 9l2 2', 'Hormone Health & Menopause'],
                                ['M15 9a6 6 0 00-6 6c0 2 1 3 1 5h4M18 7l1.5-1.5M19 11h2M17.5 4.5L18 4', 'Skin, Hair & Aesthetics'],
                                ['M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', 'Energy, Mood & Wellbeing'],
                            ] as $card)
                                <div class="flex items-center justify-between gap-3 bg-white/90 backdrop-blur-sm rounded-xl px-4 py-3.5 shadow-sm border border-sage-200/60">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-6 h-6 text-zapmed-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $card[0] }}"/></svg>
                                        <span class="text-sm font-semibold text-gray-800 leading-tight">{{ $card[1] }}</span>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" class="mt-8 inline-flex items-center gap-2 bg-zapmed-700 hover:bg-zapmed-800 text-white px-7 py-3.5 rounded-full text-sm font-semibold transition-colors shadow">
                            Explore Women's Health
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </button>
                </div>
            </div>

        </div>
    </section>

    <!-- Testimonials / Google Reviews -->
    <section class="py-20 bg-gray-50 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Our customers love Zapmed</h2>
                <p class="mt-4 text-lg text-gray-500">Safe, secure, and trusted.</p>
            </div>

            @livewire('google-reviews')
        </div>
    </section>

    <!-- Your ZapMed Health Coach -->
    <section id="health-coach" class="relative overflow-hidden bg-zapmed-950">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">

                {{-- Left column: copy + numbered benefits --}}
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zapmed-300">Your Zapmed Health Coach</p>

                    <h2 class="mt-5 text-4xl sm:text-5xl font-bold text-white leading-tight">
                        The difference?
                        <span class="block mt-1 font-serif italic font-normal text-sage-300">Someone in your corner.</span>
                    </h2>

                    <p class="mt-6 max-w-xl text-base sm:text-lg text-zapmed-100/80 leading-relaxed">
                        This is the support that makes Zapmed personal. Your Health Coach helps you turn
                        treatment into everyday habits, with nutrition, movement and mindset guidance for
                        lasting progress.
                    </p>

                    <button type="button"
                            class="mt-8 inline-flex items-center bg-zapmed-50 hover:bg-white text-zapmed-900 px-7 py-3.5 rounded-full text-sm font-semibold transition-colors shadow-lg">
                        Meet your Health Coach
                    </button>

                    {{-- Numbered benefits list --}}
                    <dl class="mt-12 border-t border-white/10">
                        @foreach ([
                            ['Movement guides', 'Find movement that fits your day.'],
                            ['Tracking tools', 'Notice your progress, beyond the scale.'],
                            ['Unlimited practitioner support', 'Keep your questions close to the people who can help.'],
                            ['Health coaching', 'Personal guidance, with someone in your corner.'],
                            ['A private community', 'Make space for connection and encouragement.'],
                            ['Meal plans', 'Bring practical nutrition into your everyday routine.'],
                            ['Partner benefits', 'Including Wellness Warehouse, IV Bar and more.'],
                        ] as $i => $item)
                            <div class="flex items-baseline gap-5 py-5 border-b border-white/10">
                                <span class="text-xs font-medium text-zapmed-300 tabular-nums pt-1">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                <div>
                                    <dt class="text-xl sm:text-2xl font-semibold text-white">{{ $item[0] }}</dt>
                                    <dd class="mt-1 text-sm text-zapmed-100/70">{{ $item[1] }}</dd>
                                </div>
                            </div>
                        @endforeach
                    </dl>

                    <p class="mt-10 font-serif italic text-2xl sm:text-3xl text-sage-300">More ways to feel supported.</p>
                </div>

                {{-- Right column: Health Coach video-call phone mockup (full device render) --}}
                <div class="flex justify-center lg:justify-end">
                    <img src="/images/health-coach-call.png" alt="ZapMed Health Coach video call on an iPhone"
                         class="w-[392px] sm:w-[476px] h-auto drop-shadow-2xl" loading="lazy">
                </div>

            </div>
        </div>
    </section>

    <!-- FAQs — your questions, expertly answered -->
    <section id="faqs" class="relative overflow-hidden px-4 sm:px-6 lg:px-8 py-20 lg:py-24 bg-zapmed-50">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16">

            {{-- Left: intro + search + help card --}}
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zapmed-700">FAQs</p>
                <h2 class="mt-4 heading text-brand">
                    Your questions,
                    <span class="block mt-1 heading-italic text-sage-500">expertly answered.</span>
                </h2>
                <p class="mt-5 text-lg text-gray-600 leading-relaxed">
                    Clear, honest answers about how ZapMed works, our treatments, pricing and your privacy.
                </p>

                {{-- Search (non-functional placeholder, matches design) --}}
                <div class="mt-8 flex items-center gap-2 bg-white rounded-full shadow-sm border border-zapmed-100 p-2 max-w-md">
                    <div class="flex items-center flex-1 px-3">
                        <svg class="w-5 h-5 text-zapmed-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" placeholder="Search your question..." class="w-full border-0 focus:ring-0 bg-transparent text-sm text-gray-700 placeholder-gray-400">
                    </div>
                    <button type="button" class="bg-zapmed-900 hover:bg-zapmed-950 text-white px-6 py-2.5 rounded-full text-sm font-semibold transition-colors">Search</button>
                </div>

                {{-- Still need help card --}}
                <div class="mt-8 bg-zapmed-100/60 rounded-2xl p-6 max-w-md">
                    <div class="flex items-start gap-4">
                        <div class="flex -space-x-3 shrink-0">
                            @for ($i = 0; $i < 3; $i++)
                                <span class="w-11 h-11 rounded-full bg-zapmed-200 border-2 border-zapmed-50 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-zapmed-700" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v2h18v-2c0-3.5-4-6-9-6z"/></svg>
                                </span>
                            @endfor
                        </div>
                        <div>
                            <p class="text-lg font-semibold text-gray-900">Still need help?</p>
                            <p class="mt-1 text-sm text-gray-600">Chat to our healthcare team for personalised guidance.</p>
                            <button type="button" class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-zapmed-800 hover:text-zapmed-900 transition-colors">
                                Speak to a Doctor
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: accordion --}}
            <div x-data="{ open: 0 }" class="space-y-3">
                @foreach ([
                    ['How does online care work?', 'You start with a quick online assessment, then a registered healthcare professional reviews your information. If suitable, you\'ll receive a personalised treatment plan with ongoing support — all from the comfort and privacy of your home.'],
                    ['Can I choose my own medication?', 'Treatment is always prescribed by a registered professional based on what\'s clinically appropriate for you. You can share your preferences, and your prescriber will discuss the best option for your needs.'],
                    ['How much does the weight-loss programme cost?', 'Weight-loss subscriptions start at R450 per month, which includes support, delivery and your Health Coach. Medication is billed separately and only when prescribed. Confirm current prices when you join.'],
                    ['What can my Health Coach help with?', 'Your Health Coach supports you with nutrition, movement and mindset — turning your treatment into lasting everyday habits, with someone in your corner every step of the way.'],
                    ['How soon will my treatment arrive?', 'Once your plan is approved, treatment is delivered fast, discreetly and privately, straight to your door — with free monthly delivery on subscriptions.'],
                    ['Will the calculator predict my results?', 'Our calculator gives an evidence-based estimate to help set expectations. It\'s a guide, not a guarantee — individual results vary depending on your treatment, biology and lifestyle.'],
                    ['Are my consultations private?', 'Yes. Your consultations and information are confidential and handled in line with POPIA. Everything happens discreetly, with your privacy protected throughout.'],
                    ['Can I cancel my membership?', 'Absolutely. You can cancel anytime — there are no lock-in contracts. Your subscription simply stops at the end of your current billing cycle.'],
                    ['Who can use the service?', 'ZapMed is available to adults in South Africa. During your assessment we\'ll confirm the service is appropriate and safe for you before any treatment is prescribed.'],
                    ['Is this an emergency service?', 'No. ZapMed is not for medical emergencies. If you are experiencing a medical emergency, please call your local emergency services or go to your nearest emergency department immediately.'],
                ] as $i => $faq)
                    <div class="bg-white rounded-xl border border-zapmed-100 overflow-hidden">
                        <button type="button" @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                                class="w-full flex items-center gap-4 px-5 py-4 text-left">
                            <span class="w-9 h-9 rounded-lg bg-zapmed-100 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-zapmed-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                            <span class="flex-1 text-base font-semibold text-gray-900">{{ $faq[0] }}</span>
                            <svg class="w-5 h-5 text-zapmed-700 shrink-0 transition-transform" :class="open === {{ $i }} ? 'rotate-45' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                        <div x-show="open === {{ $i }}" x-collapse x-cloak>
                            <p class="px-5 pb-5 pl-[4.5rem] text-sm text-gray-600 leading-relaxed">{{ $faq[1] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </section>

    <!-- Free guide — lead magnet banner -->
    <section id="free-guide" class="px-4 sm:px-6 lg:px-8 py-16 lg:py-20 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="relative overflow-hidden rounded-3xl bg-zapmed-950">
                {{-- banner background image (book + tips are baked into this artwork) --}}
                <div class="absolute inset-0 bg-cover bg-center"
                     style="background-image: url('/images/guide-banner-bg.webp');"></div>
                {{-- soft left scrim so the copy stays legible over the green side --}}
                <div class="absolute inset-0 bg-gradient-to-r from-zapmed-950/80 via-zapmed-950/30 to-transparent"></div>

                <div class="relative grid grid-cols-1 lg:grid-cols-12 gap-8 p-8 sm:p-10 lg:p-14 items-center">

                    {{-- Left: copy + CTA (book + tips live in the background image) --}}
                    <div class="lg:col-span-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zapmed-300">Free guide</p>
                        <h2 class="mt-4 text-4xl sm:text-5xl font-bold text-white leading-[1.05]">
                            Start your weight-loss journey
                            <span class="block mt-1 font-serif italic font-normal text-sage-300">with confidence.</span>
                        </h2>
                        <p class="mt-5 text-base sm:text-lg text-zapmed-100/80 leading-relaxed max-w-md">
                            Get our free guide with expert tips, practical advice and everything you need to know before you begin.
                        </p>

                        <button type="button" class="mt-8 inline-flex items-center gap-3 bg-white hover:bg-zapmed-50 text-zapmed-900 px-7 py-4 rounded-full text-sm font-semibold transition-colors shadow-lg">
                            Get my free guide
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </button>

                        <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-3 text-zapmed-100/70 text-sm">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-zapmed-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Free PDF
                            </span>
                            <span class="hidden sm:block h-4 w-px bg-white/20"></span>
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-zapmed-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Yours to keep
                            </span>
                            <span class="hidden sm:block h-4 w-px bg-white/20"></span>
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-zapmed-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                No spam
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Trust strip --}}
            <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-6 px-2">
                @foreach ([
                    ['M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'Evidence-based information', 'Trusted and up to date'],
                    ['M12 3C9 7 7 9 7 13a5 5 0 0010 0c0-4-2-6-5-10z', 'Designed for real life', 'Practical and achievable'],
                    ['M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'Created by healthcare professionals', 'You can trust'],
                ] as $badge)
                    <div class="flex items-center gap-3">
                        <svg class="w-8 h-8 text-zapmed-800 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $badge[0] }}"/></svg>
                        <div>
                            <p class="text-sm font-semibold text-zapmed-900">{{ $badge[1] }}</p>
                            <p class="text-xs text-gray-500">{{ $badge[2] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @include('partials.public-footer')

</body>
</html>
