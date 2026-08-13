<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Zapmed - Online Doctor-Guided Medical Treatments</title>
    <meta name="description" content="Doctor-led online treatment for weight loss, sexual health, skincare, and chronic care. Prescribed by licensed SA doctors, delivered to your door.">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=noto-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.analytics')
    @include('partials.pwa-meta')
</head>
<body class="font-sans antialiased bg-white">

    @include('partials.public-nav')

    <!-- Hero -->
    <section class="mt-16 pb-16 lg:pb-20 bg-cover bg-center bg-no-repeat relative" style="background-image: url('/images/Hero.webp');">
        <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/60 to-transparent"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 lg:pt-24">
            <div class="flex justify-start">
                <div class="text-left max-w-xl lg:max-w-lg">
                    <div class="inline-flex items-center bg-zapmed-600/20 text-zapmed-300 border border-zapmed-500/30 px-4 py-1.5 rounded-full text-sm font-medium mb-8">
                        <span class="w-2 h-2 bg-zapmed-400 rounded-full mr-2 animate-pulse"></span>
                        Medically-Backed, Tech-Powered
                    </div>
                    <h1 class="text-3xl sm:text-5xl lg:text-6xl font-bold text-white tracking-tight leading-tight">
                        Online Doctor-Guided<br>
                        <span class="text-zapmed-400">Medical Treatments</span>
                    </h1>
                    <p class="mt-6 text-lg text-gray-300 leading-relaxed">
                        Doctor-led GLP-1 weight loss with personalised coaching — plus discreet sexual, mental, skin and chronic care, all in one seamless platform.
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row items-center justify-start gap-4">
                        <a href="{{ route('register') }}" class="w-full sm:w-auto bg-zapmed-600 hover:bg-zapmed-700 text-white px-8 py-4 rounded-xl text-base font-semibold transition-all shadow-lg">
                            Start Your Assessment
                        </a>
                        <a href="#how-it-works" class="w-full sm:w-auto flex items-center justify-center text-gray-300 hover:text-white px-8 py-4 rounded-xl text-base font-semibold transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>
                            </svg>
                            See How It Works
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Trust badges (white background, above AI chat) -->
    <div class="py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl mx-auto grid grid-cols-3 md:grid-cols-3 lg:grid-cols-6 gap-4 sm:gap-6">
            <!-- Online in Minutes -->
            <div class="flex flex-col items-center text-center group">
                <div class="w-full rounded-xl overflow-hidden mb-3 shadow-sm border border-gray-100 group-hover:shadow-md group-hover:scale-105 transition-all duration-300">
                    <img src="/images/Online_quick.webp" alt="Online in Minutes" class="w-full h-auto object-contain">
                </div>
                <span class="text-sm font-semibold text-gray-900">Online in Minutes</span>
                <span class="text-xs text-gray-500 mt-1">No waiting rooms, no queues</span>
            </div>
            <!-- Licensed SA Doctors -->
            <div class="flex flex-col items-center text-center group">
                <div class="w-full rounded-xl overflow-hidden mb-3 shadow-sm border border-gray-100 group-hover:shadow-md group-hover:scale-105 transition-all duration-300">
                    <img src="/images/Licensed_SA_Doctors.webp" alt="Licensed SA Doctors" class="w-full h-auto object-contain">
                </div>
                <span class="text-sm font-semibold text-gray-900">Licensed Online SA Doctors</span>
                <span class="text-xs text-gray-500 mt-1">HPCSA registered practitioners</span>
            </div>
            <!-- Delivered to Your Door -->
            <div class="flex flex-col items-center text-center group">
                <div class="w-full rounded-xl overflow-hidden mb-3 shadow-sm border border-gray-100 group-hover:shadow-md group-hover:scale-105 transition-all duration-300">
                    <img src="/images/Door_delivery.webp" alt="Delivered to Your Door" class="w-full h-auto object-contain">
                </div>
                <span class="text-sm font-semibold text-gray-900">Medication Delivered to Your Door</span>
                <span class="text-xs text-gray-500 mt-1">Discreet packaging nationwide</span>
            </div>
            <!-- Discreet & Confidential -->
            <div class="flex flex-col items-center text-center group">
                <div class="w-full rounded-xl overflow-hidden mb-3 shadow-sm border border-gray-100 group-hover:shadow-md group-hover:scale-105 transition-all duration-300">
                    <img src="/images/Descreet_Door_Delivery.webp" alt="Discreet and Confidential" class="w-full h-auto object-contain">
                </div>
                <span class="text-sm font-semibold text-gray-900">Discreet & Confidential</span>
                <span class="text-xs text-gray-500 mt-1">End-to-end encrypted</span>
            </div>
            <!-- Regulated Pharmacy Partners -->
            <div class="flex flex-col items-center text-center group">
                <div class="w-full rounded-xl overflow-hidden mb-3 shadow-sm border border-gray-100 group-hover:shadow-md group-hover:scale-105 transition-all duration-300">
                    <img src="/images/Regulated_Pharmacy_partners.webp" alt="Regulated Pharmacy Partners" class="w-full h-auto object-contain">
                </div>
                <span class="text-sm font-semibold text-gray-900">Regulated Pharmacy Partners</span>
                <span class="text-xs text-gray-500 mt-1">SAPC licensed dispensaries</span>
            </div>
            <!-- POPIA Compliant -->
            <div class="flex flex-col items-center text-center group">
                <div class="w-full rounded-xl overflow-hidden mb-3 shadow-sm border border-gray-100 group-hover:shadow-md group-hover:scale-105 transition-all duration-300">
                    <img src="/images/Popi.webp" alt="POPIA Compliant" class="w-full h-auto object-contain">
                </div>
                <span class="text-sm font-semibold text-gray-900">POPIA Compliant</span>
                <span class="text-xs text-gray-500 mt-1">Your data stays protected</span>
            </div>
        </div>
    </div>
            <!-- AI Health Assistant — now a floating widget -->

            <!-- Inline AI Chat (Homepage only) -->
            <div class="max-w-2xl mx-auto" style="margin-top: 5px;" x-data="aiChatWidget()">
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="p-5 border-b border-gray-50">
                        <div class="flex items-center space-x-3">
                            <div class="w-9 h-9 bg-zapmed-100 rounded-lg flex items-center justify-center overflow-hidden">
                                <img src="/images/aiavatar.webp" alt="AI Doctor" class="w-9 h-9">
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">AI Health Assistant</h3>
                                <p class="text-xs text-gray-500">Ask me anything about our treatments</p>
                            </div>
                        </div>
                    </div>

                    <!-- Response area -->
                    <template x-if="messages.length > 0">
                        <div class="p-5 bg-gray-50 border-b border-gray-100 max-h-[300px] overflow-y-auto space-y-3">
                            <template x-for="(msg, i) in messages" :key="i">
                                <div>
                                    <template x-if="msg.role === 'user'">
                                        <div class="flex justify-end">
                                            <div class="bg-zapmed-600 text-white rounded-xl rounded-tr-sm px-3 py-2 max-w-[85%]">
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
                                                <div class="bg-white rounded-xl rounded-tl-sm px-3 py-2 border border-gray-200">
                                                    <p class="text-sm text-gray-700 whitespace-pre-line" x-text="msg.text"></p>
                                                </div>
                                                <template x-if="msg.treatmentUrl">
                                                    <a :href="msg.treatmentUrl" class="mt-2 inline-flex items-center px-3 py-1.5 bg-zapmed-600 hover:bg-zapmed-700 text-white text-xs font-medium rounded-lg transition-colors">
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
                            class="flex-1 rounded-xl border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500 placeholder-gray-400"
                            placeholder="e.g. I want to lose weight, I need help with acne..."
                            :disabled="loading" maxlength="500">
                        <button type="submit" :disabled="loading || !message.trim()"
                            class="flex-shrink-0 w-10 h-10 bg-zapmed-600 hover:bg-zapmed-700 disabled:bg-gray-300 text-white rounded-xl flex items-center justify-center transition-colors">
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
                <p class="text-center text-xs text-gray-400 mt-3" style="padding-bottom: 35px;">Powered by AI. Not a substitute for medical advice — always consult a doctor.</p>
            </div>
        </div>
    </section>


    <!-- Services -->
    <section id="services" class="py-20 bg-white px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Treatments</h2>
                <p class="mt-4 text-lg text-gray-500 max-w-2xl mx-auto">Doctor-led online treatment — personalised, discreet, and delivered.</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-8">
                @foreach(config('treatments') as $categorySlug => $category)
                <div class="group">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2 relative inline-block">
                        {{ $category['name'] }}
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-zapmed-600 group-hover:w-full transition-all duration-300"></span>
                    </h3>
                    <!-- Category Image -->
                    <div class="mb-3 rounded-lg overflow-hidden bg-gray-100">
                        <img src="{{ $category['image'] }}" alt="{{ $category['name'] }} treatments - Zapmed online telehealth South Africa" class="w-full h-auto object-contain transition-transform duration-300 group-hover:scale-105" loading="lazy">
                    </div>
                    <ul class="space-y-0">
                        @foreach($category['treatments'] as $treatmentSlug => $treatment)
                            <li class="border-b border-gray-100 last:border-b-0 relative">
                                <a href="{{ route('treatments.show', $treatmentSlug) }}" class="relative flex items-center gap-1.5 text-sm text-gray-600 hover:text-zapmed-600 hover:translate-x-1 transition-all duration-200 py-1.5 group/item">
                                    <span class="text-zapmed-600 text-xs">›</span>
                                    {{ $treatment['name'] }}
                                    <span class="hidden md:block absolute bottom-full left-0 mb-2 z-[9999] bg-white text-gray-700 text-xs rounded-lg px-3 py-2 w-48 opacity-0 invisible group-hover/item:opacity-100 group-hover/item:visible transition-all duration-200 pointer-events-none shadow-md border border-zapmed-600">
                                        {{ $treatment['tagline'] }}
                                        <span class="absolute top-full left-4 border-[6px] border-transparent border-t-zapmed-600"></span>
                                        <span class="absolute top-full left-4 mt-[-1px] border-[6px] border-transparent border-t-white"></span>
                                    </span>
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
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Get your personalised consultation</h2>
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
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Doctor consultation included</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Prescription fees included</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Free delivery nationwide</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Cancel anytime</li>
                    </ul>
                    <a href="{{ route('register') }}" class="mt-8 block w-full text-center bg-gray-100 hover:bg-gray-200 text-gray-800 py-3 rounded-lg text-sm font-semibold transition-colors">
                        Get Started
                    </a>
                </div>

                <!-- Weight Loss (featured) -->
                <div class="bg-white rounded-2xl p-8 shadow-lg border-2 border-zapmed-500 relative">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-zapmed-600 text-white text-xs font-semibold px-3 py-1 rounded-full">Most Popular</div>
                    <h3 class="text-lg font-semibold text-gray-900">Weight Loss Programme</h3>
                    <p class="text-sm text-gray-500 mt-1">Doctor + Health Coach</p>
                    <div class="mt-6">
                        <span class="text-4xl font-bold text-gray-900">R450</span>
                        <span class="text-gray-500">/month</span>
                    </div>
                    <ul class="mt-6 space-y-3 text-sm text-gray-600">
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Partner Doctor consultation</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Dedicated Health Coach</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>GLP-1 medication (if prescribed)</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Ongoing WhatsApp support</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Discreet delivery</li>
                    </ul>
                    <p class="mt-4 text-xs text-gray-400">Medication costs billed separately based on prescribed dosage.</p>
                    <a href="{{ route('register') }}" class="mt-6 block w-full text-center bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm">
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
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Virtual doctor consultation</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>E-prescription emailed</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Sick notes & medical certificates</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Optional delivery add-on</li>
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
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Doctor-Trusted Solutions</h2>
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
                                <a href="{{ route('register') }}" class="mt-4 inline-block bg-zapmed-600 hover:bg-zapmed-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors w-fit">Start Assessment</a>
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
                                <a href="{{ route('register') }}" class="mt-4 inline-block bg-zapmed-600 hover:bg-zapmed-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors w-fit">Start Assessment</a>
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
                                <a href="{{ route('register') }}" class="mt-4 inline-block bg-zapmed-600 hover:bg-zapmed-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors w-fit">Start Assessment</a>
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
                                <a href="{{ route('register') }}" class="mt-4 inline-block bg-zapmed-600 hover:bg-zapmed-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors w-fit">Start Assessment</a>
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
                                <a href="{{ route('register') }}" class="mt-4 inline-block bg-zapmed-600 hover:bg-zapmed-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors w-fit">Start Assessment</a>
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
                                <a href="{{ route('register') }}" class="mt-4 inline-block bg-zapmed-600 hover:bg-zapmed-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors w-fit">Start Assessment</a>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-center mt-10 space-x-4">
                    <button @click="prev()" class="w-10 h-10 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:text-zapmed-600 hover:border-zapmed-300 transition-colors shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <div class="flex space-x-2">
                        <template x-for="i in dots" :key="i">
                            <button @click="goTo(i - 1)" class="w-2.5 h-2.5 rounded-full transition-all duration-300" :class="current === (i - 1) ? 'bg-zapmed-500 w-6' : 'bg-gray-300'"></button>
                        </template>
                    </div>
                    <button @click="next()" class="w-10 h-10 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:text-zapmed-600 hover:border-zapmed-300 transition-colors shadow-sm">
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

    <!-- Doctors -->
    <section id="doctors" class="py-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Discreet Care for Everyday Health Needs</h2>
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

    <!-- CTA -->
    <section class="py-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto bg-gray-900 rounded-3xl p-12 text-center">
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Healthcare made simple for you.</h2>
            <p class="mt-4 text-lg text-gray-400">Start your assessment today — it only takes a few minutes.</p>
            <a href="{{ route('register') }}" class="mt-8 inline-block bg-zapmed-500 hover:bg-zapmed-600 text-white px-8 py-4 rounded-xl text-base font-semibold transition-all shadow-lg">
                Start Your Assessment
            </a>
            <!-- Mobile App Download Buttons -->
            <div class="mt-8">
                <p class="text-sm text-gray-500 mb-4">Coming soon on mobile</p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                    <a href="#" class="inline-flex items-center bg-white/10 hover:bg-white/20 border border-white/20 text-white px-5 py-3 rounded-xl transition-all group">
                        <svg class="w-7 h-7 mr-3" viewBox="0 0 24 24" fill="currentColor"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>
                        <div class="text-left">
                            <div class="text-[10px] text-gray-400 leading-none">Download on the</div>
                            <div class="text-sm font-semibold leading-tight">App Store</div>
                        </div>
                    </a>
                    <a href="#" class="inline-flex items-center bg-white/10 hover:bg-white/20 border border-white/20 text-white px-5 py-3 rounded-xl transition-all group">
                        <svg class="w-7 h-7 mr-3" viewBox="0 0 24 24" fill="currentColor"><path d="M3.609 1.814L13.792 12 3.61 22.186a.996.996 0 01-.61-.92V2.734a1 1 0 01.609-.92zm10.89 10.893l2.302 2.302-10.937 6.333 8.635-8.635zm3.199-3.198l2.807 1.626a1 1 0 010 1.73l-2.808 1.626L15.206 12l2.492-2.491zM5.864 2.658L16.8 8.99l-2.302 2.302-8.634-8.634z"/></svg>
                        <div class="text-left">
                            <div class="text-[10px] text-gray-400 leading-none">Get it on</div>
                            <div class="text-sm font-semibold leading-tight">Google Play</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    @include('partials.public-footer')

</body>
</html>
