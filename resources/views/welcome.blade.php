<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Zapmed - Online Doctor-Guided Medical Treatments</title>
    <meta name="description" content="Doctor-led online treatment for weight loss, sexual health, skincare, and chronic care. Prescribed by licensed SA doctors, delivered to your door.">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white">

    @include('partials.public-nav')

    <!-- Hero -->
    <section class="mt-16 pb-16 lg:pb-20 bg-cover bg-center bg-no-repeat" style="background-image: url('/images/hero-bg.jpg');">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 lg:pt-20">
            <div class="text-center max-w-3xl mx-auto">
                <div class="inline-flex items-center bg-zapmed-50 text-zapmed-700 px-4 py-1.5 rounded-full text-sm font-medium mb-8">
                    <span class="w-2 h-2 bg-zapmed-500 rounded-full mr-2 animate-pulse"></span>
                    Medically-Backed, Tech-Powered
                </div>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-gray-900 tracking-tight leading-tight">
                    Online Doctor-Guided
                    <span class="text-zapmed-600">Medical Treatments</span>
                </h1>
                <p class="mt-6 text-lg text-gray-500 max-w-xl mx-auto leading-relaxed">
                    Doctor-led GLP-1 weight loss with personalised coaching — plus discreet sexual, mental, skin and chronic care, all in one seamless platform.
                </p>
                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="{{ route('register') }}" class="w-full sm:w-auto bg-zapmed-600 hover:bg-zapmed-700 text-white px-8 py-4 rounded-xl text-base font-semibold transition-all shadow-lg">
                        Start Your Assessment
                    </a>
                    <a href="#how-it-works" class="w-full sm:w-auto flex items-center justify-center text-gray-700 hover:text-gray-900 px-8 py-4 rounded-xl text-base font-semibold transition-colors">
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
    <div class="py-8 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-center gap-x-8 gap-y-4 text-sm text-gray-600">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <span>Licensed SA Doctors</span>
            </div>
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/><path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z"/></svg>
                <span>Delivered to Your Door</span>
            </div>
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                <span>Discreet & Confidential</span>
            </div>
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-zapmed-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                <span>Regulated Pharmacy Partners</span>
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
                                <img src="/images/ai-doctor-avatar.svg" alt="AI Doctor" class="w-9 h-9">
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
                                                <img src="/images/ai-doctor-avatar.svg" alt="AI" class="w-7 h-7">
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
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Our Services</h2>
                <p class="mt-4 text-lg text-gray-500 max-w-2xl mx-auto">Doctor-led online treatment — personalised, discreet, and delivered.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-12">
                @foreach(config('treatments') as $categorySlug => $category)
                <div class="text-center">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">{{ $category['name'] }}</h3>
                    <div class="w-48 h-48 mx-auto mb-5 rounded-2xl overflow-hidden">
                        <img src="{{ $category['image'] }}" alt="{{ $category['name'] }} - Zapmed telehealth South Africa" class="w-full h-full object-cover">
                    </div>
                    <ul class="space-y-2">
                        @foreach($category['treatments'] as $treatmentSlug => $treatment)
                        <li>
                            <a href="{{ route('treatments.show', $treatmentSlug) }}" class="text-sm text-gray-600 hover:text-zapmed-600 transition-colors">
                                {{ $treatment['name'] }}
                            </a>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </div>
        </div>
    </section>


    <!-- How It Works -->
    <section id="how-it-works" class="py-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Your Process</h2>
                <p class="mt-4 text-lg text-gray-500">From assessment to treatment — simple, supported, and safe.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                <div class="text-center">
                    <div class="w-14 h-14 bg-zapmed-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <span class="text-xl font-bold text-zapmed-600">1</span>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Begin Your Journey</h3>
                    <p class="text-xs text-gray-500">Complete an online assessment — no waiting rooms, no judgement.</p>
                </div>

                <div class="text-center">
                    <div class="w-14 h-14 bg-zapmed-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <span class="text-xl font-bold text-zapmed-600">2</span>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Connect With A Doctor</h3>
                    <p class="text-xs text-gray-500">Work with an expert dedicated to making your treatment effective.</p>
                </div>

                <div class="text-center">
                    <div class="w-14 h-14 bg-zapmed-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <span class="text-xl font-bold text-zapmed-600">3</span>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Virtual Support</h3>
                    <p class="text-xs text-gray-500">Meet online in a flexible, convenient setting whenever it suits you.</p>
                </div>

                <div class="text-center">
                    <div class="w-14 h-14 bg-zapmed-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <span class="text-xl font-bold text-zapmed-600">4</span>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Start Treatment</h3>
                    <p class="text-xs text-gray-500">Follow a safe programme designed around your lifestyle and habits.</p>
                </div>

                <div class="text-center">
                    <div class="w-14 h-14 bg-zapmed-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <span class="text-xl font-bold text-zapmed-600">5</span>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Keep Moving Forward</h3>
                    <p class="text-xs text-gray-500">Track results, manage prescriptions, and adjust treatment as needed.</p>
                </div>
            </div>
        </div>
    </section>

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
                    <div class="w-full sm:w-1/2 lg:w-1/2 flex-shrink-0 px-3">
                        <div class="h-full rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow group relative" style="min-height: 380px;">
                                <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('/images/trust-slides/professional-female-doctor-banner.webp');"></div>

                                <div class="relative h-full p-8 flex flex-col justify-end text-white">
                                    <div class="w-12 h-12 bg-zapmed-500 rounded-xl flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    </div>
                                    <h3 class="text-lg font-bold mb-2">Prescribed by Licensed Providers</h3>
                                    <p class="text-sm text-white/80 leading-relaxed">Every medication prescribed by HPCSA-registered doctors and dispensed by regulated pharmacy partners.</p>
                                </div>
                            </div>
                        </div>
                        <!-- Card 2 -->
                        <div class="w-full sm:w-1/2 lg:w-1/3 flex-shrink-0 px-3">
                            <div class="h-full rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow group relative" style="min-height: 380px;">
                                <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('/images/trust-slides/Sliders_Images65.jpg');"></div>

                                <div class="relative h-full p-8 flex flex-col justify-end text-white">
                                    <div class="w-12 h-12 bg-zapmed-500 rounded-xl flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    </div>
                                    <h3 class="text-lg font-bold mb-2">Qualified SA Doctors</h3>
                                    <p class="text-sm text-white/80 leading-relaxed">Fully registered with the Health Professions Council with experience in telehealth and chronic care.</p>
                                </div>
                            </div>
                        </div>
                        <!-- Card 3 -->
                        <div class="w-full sm:w-1/2 lg:w-1/3 flex-shrink-0 px-3">
                            <div class="h-full rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow group relative" style="min-height: 380px;">
                                <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('/images/trust-slides/Sliders_Images909.jpg');"></div>

                                <div class="relative h-full p-8 flex flex-col justify-end text-white">
                                    <div class="w-12 h-12 bg-zapmed-500 rounded-xl flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    </div>
                                    <h3 class="text-lg font-bold mb-2">Private & Confidential</h3>
                                    <p class="text-sm text-white/80 leading-relaxed">POPIA compliant. Encrypted records. Discreet, unbranded packaging. Your health stays your business.</p>
                                </div>
                            </div>
                        </div>
                        <!-- Card 4 -->
                        <div class="w-full sm:w-1/2 lg:w-1/3 flex-shrink-0 px-3">
                            <div class="h-full rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow group relative" style="min-height: 380px;">
                                <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('/images/trust-slides/healthy-nutrition-meal-prep-containers.webp');"></div>

                                <div class="relative h-full p-8 flex flex-col justify-end text-white">
                                    <div class="w-12 h-12 bg-zapmed-500 rounded-xl flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    </div>
                                    <h3 class="text-lg font-bold mb-2">Fast Delivery Nationwide</h3>
                                    <p class="text-sm text-white/80 leading-relaxed">Medication delivered to your door in 1-3 business days. All 9 provinces. Plain packaging, no questions asked.</p>
                                </div>
                            </div>
                        </div>
                        <!-- Card 5 -->
                        <div class="w-full sm:w-1/2 lg:w-1/3 flex-shrink-0 px-3">
                            <div class="h-full rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow group relative" style="min-height: 380px;">
                                <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('/images/trust-slides/professional-female-doctor-banner.webp');"></div>

                                <div class="relative h-full p-8 flex flex-col justify-end text-white">
                                    <div class="w-12 h-12 bg-zapmed-500 rounded-xl flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                    </div>
                                    <h3 class="text-lg font-bold mb-2">Ongoing Support & Care</h3>
                                    <p class="text-sm text-white/80 leading-relaxed">Continuous check-ins, dose adjustments, and a dedicated health coach to guide you every step of the way.</p>
                                </div>
                            </div>
                        </div>
                        <!-- Card 6 -->
                        <div class="w-full sm:w-1/2 lg:w-1/3 flex-shrink-0 px-3">
                            <div class="h-full rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-shadow group relative" style="min-height: 380px;">
                                <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('/images/trust-slides/Sliders_Images65.jpg');"></div>

                                <div class="relative h-full p-8 flex flex-col justify-end text-white">
                                    <div class="w-12 h-12 bg-zapmed-500 rounded-xl flex items-center justify-center mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <h3 class="text-lg font-bold mb-2">Affordable & Transparent</h3>
                                    <p class="text-sm text-white/80 leading-relaxed">No hidden fees. Clear pricing from R220/month. Cancel anytime. Medical aid invoices provided.</p>
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
                <!-- Doctor 1 -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="w-16 h-16 bg-zapmed-100 rounded-full flex items-center justify-center mb-4">
                        <span class="text-lg font-bold text-zapmed-700">Dr</span>
                    </div>
                    <h3 class="text-base font-semibold text-gray-900">Partner Doctor</h3>
                    <p class="text-sm text-zapmed-600 font-medium">MBChB (Stell) | DipObs (CMSA)</p>
                    <p class="text-xs text-gray-500 mt-3 leading-relaxed">Compassionate medical doctor and advocate for patient-centred, inclusive healthcare. Special interest in women's health, mental wellness, sexual health, and preventative care.</p>
                </div>

                <!-- Dietitian -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                        <span class="text-lg font-bold text-green-700">RD</span>
                    </div>
                    <h3 class="text-base font-semibold text-gray-900">Amy — Wellness Dietitian</h3>
                    <p class="text-sm text-zapmed-600 font-medium">Registered Dietitian</p>
                    <p class="text-xs text-gray-500 mt-3 leading-relaxed">Dedicated to weight management and digital health. Combines evidence-based nutrition support with a compassionate, realistic approach to healthy eating and lasting change.</p>
                </div>

                <!-- Doctor 2 -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                        <span class="text-lg font-bold text-blue-700">Dr</span>
                    </div>
                    <h3 class="text-base font-semibold text-gray-900">Partner Doctor</h3>
                    <p class="text-sm text-zapmed-600 font-medium">MBChB (UP) | DipHIVMan | DipPEC</p>
                    <p class="text-xs text-gray-500 mt-3 leading-relaxed">Committed to delivering comprehensive, patient-centred care with empathy and insight. Interests in women's health, chronic disease management, and primary emergency care.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="py-20 bg-gray-50 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Our customers love Zapmed</h2>
                <p class="mt-4 text-lg text-gray-500">Safe, secure, and trusted.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center mb-3">
                        <div class="flex text-amber-400">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 mb-2">"Excellent from start to finish!"</p>
                    <p class="text-xs text-gray-500 leading-relaxed">The entire process was quick, easy to use and hassle free! The Doctor was friendly, knowledgeable and gave excellent advice.</p>
                </div>

                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center mb-3">
                        <div class="flex text-amber-400">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 mb-2">"This is the most genius thing ever!"</p>
                    <p class="text-xs text-gray-500 leading-relaxed">Quick consultation with expert advice. Got my delivery a day after — to my door. Extremely affordable and convenient.</p>
                </div>

                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center mb-3">
                        <div class="flex text-amber-400">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 mb-2">"Efficient and Discreet"</p>
                    <p class="text-xs text-gray-500 leading-relaxed">Very quick, easy, efficient and discreet. Great for people who don't have the time or nerve to go to a doctor in person. Recommend 100%.</p>
                </div>
            </div>
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
        </div>
    </section>

    @include('partials.public-footer')

</body>
</html>
