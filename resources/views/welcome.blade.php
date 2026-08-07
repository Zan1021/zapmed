<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zapmed - Online Doctor-Guided Medical Treatments</title>
    <meta name="description" content="Doctor-led online treatment for weight loss, sexual health, skincare, and chronic care. Prescribed by licensed SA doctors, delivered to your door.">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white">

    @include('partials.public-nav')

    <!-- Hero -->
    <section class="pt-28 pb-16 lg:pt-32 lg:pb-20 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <!-- Text -->
                <div class="text-center lg:text-left">
                    <div class="inline-flex items-center bg-zapmed-50 text-zapmed-700 px-4 py-1.5 rounded-full text-sm font-medium mb-8">
                        <span class="w-2 h-2 bg-zapmed-500 rounded-full mr-2 animate-pulse"></span>
                        Medically-Backed, Tech-Powered
                    </div>
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-gray-900 tracking-tight leading-tight">
                        Online Doctor-Guided
                        <span class="text-zapmed-600">Medical Treatments</span>
                    </h1>
                    <p class="mt-6 text-lg text-gray-500 max-w-xl mx-auto lg:mx-0 leading-relaxed">
                        Doctor-led GLP-1 weight loss with personalised coaching — plus discreet sexual, mental, skin and chronic care, all in one seamless platform.
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row items-center lg:items-start justify-center lg:justify-start gap-4">
                        <a href="{{ route('register') }}" class="w-full sm:w-auto bg-zapmed-600 hover:bg-zapmed-700 text-white px-8 py-4 rounded-xl text-base font-semibold transition-all">
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

                <!-- Hero Image -->
                <div class="relative">
                    <img src="/images/hero.png" alt="Zapmed telehealth consultation" class="w-full h-auto rounded-2xl shadow-xl">
                </div>
            </div>

            <!-- Trust badges -->
            <div class="mt-16 flex flex-wrap items-center justify-center gap-x-8 gap-y-4 text-sm text-gray-400">
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

            <!-- AI Health Assistant -->
            <div class="mt-12 max-w-2xl mx-auto" x-data="aiAssistant()">
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="p-5 border-b border-gray-50">
                        <div class="flex items-center space-x-3">
                            <div class="w-9 h-9 bg-zapmed-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-zapmed-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">AI Health Assistant</h3>
                                <p class="text-xs text-gray-500">Ask me anything about our treatments</p>
                            </div>
                        </div>
                    </div>

                    <!-- Response area -->
                    <div x-show="response" x-transition class="p-5 bg-gray-50 border-b border-gray-100">
                        <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line" x-text="response"></p>
                        <template x-if="treatmentUrl">
                            <a :href="treatmentUrl" class="mt-3 inline-flex items-center px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg transition-colors">
                                <span x-text="'View ' + treatmentName + ' →'"></span>
                            </a>
                        </template>
                    </div>

                    <!-- Input -->
                    <form @submit.prevent="askQuestion" class="p-4 flex items-center space-x-3">
                        <input x-model="message" type="text"
                            class="flex-1 rounded-xl border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500 placeholder-gray-400"
                            placeholder="e.g. I want to lose weight, I need help with acne, I'm feeling stressed..."
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
                <p class="text-center text-xs text-gray-400 mt-3">Powered by AI. Not a substitute for medical advice — always consult a doctor.</p>
            </div>
        </div>
    </section>


    <!-- Services -->
    <section id="services" class="py-20 bg-gray-50 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900">Our Services</h2>
                <p class="mt-4 text-lg text-gray-500 max-w-2xl mx-auto">Doctor-led online treatment — personalised, discreet, and delivered.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Weight Loss -->
                <a href="#" class="group bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-lg hover:border-zapmed-200 transition-all">
                    <div class="w-14 h-14 bg-gradient-to-br from-green-100 to-emerald-50 rounded-xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Weight Loss</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">GLP-1 medically guided weight loss with a dedicated Health Coach. Includes semaglutide where clinically appropriate.</p>
                    <p class="mt-3 text-sm font-semibold text-zapmed-600">From R450/month →</p>
                </a>

                <!-- Skincare -->
                <a href="#" class="group bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-lg hover:border-zapmed-200 transition-all">
                    <div class="w-14 h-14 bg-gradient-to-br from-pink-100 to-rose-50 rounded-xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Skincare</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Prescription treatments for acne, anti-ageing, hyperpigmentation, and more. Personalised to your skin.</p>
                    <p class="mt-3 text-sm font-semibold text-zapmed-600">From R220/month →</p>
                </a>

                <!-- Women's Health -->
                <a href="#" class="group bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-lg hover:border-zapmed-200 transition-all">
                    <div class="w-14 h-14 bg-gradient-to-br from-purple-100 to-violet-50 rounded-xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Women's Health</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Contraception, UTI treatment, period management, and hormone support. Private, judgement-free care.</p>
                    <p class="mt-3 text-sm font-semibold text-zapmed-600">From R220/month →</p>
                </a>

                <!-- Men's Health -->
                <a href="#" class="group bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-lg hover:border-zapmed-200 transition-all">
                    <div class="w-14 h-14 bg-gradient-to-br from-blue-100 to-indigo-50 rounded-xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Men's Health</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Hair loss treatment, testosterone support, and men's wellness. Discreet delivery in unbranded packaging.</p>
                    <p class="mt-3 text-sm font-semibold text-zapmed-600">From R220/month →</p>
                </a>

                <!-- Sexual Health -->
                <a href="#" class="group bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-lg hover:border-zapmed-200 transition-all">
                    <div class="w-14 h-14 bg-gradient-to-br from-amber-100 to-orange-50 rounded-xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Sexual Health</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Erectile dysfunction, STI treatment, and genital herpes care. Completely private and confidential consultations.</p>
                    <p class="mt-3 text-sm font-semibold text-zapmed-600">From R300 once-off →</p>
                </a>

                <!-- General Health -->
                <a href="#" class="group bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-lg hover:border-zapmed-200 transition-all">
                    <div class="w-14 h-14 bg-gradient-to-br from-teal-100 to-cyan-50 rounded-xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">General Health</h3>
                    <p class="text-sm text-gray-500 leading-relaxed">Virtual GP consultations for everyday health concerns. Get your e-prescription emailed to you or medication delivered.</p>
                    <p class="mt-3 text-sm font-semibold text-zapmed-600">R450 once-off →</p>
                </a>
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

    <script>
    function aiAssistant() {
        return {
            message: '',
            response: '',
            treatmentUrl: null,
            treatmentName: '',
            loading: false,

            async askQuestion() {
                if (!this.message.trim() || this.loading) return;

                this.loading = true;
                this.response = '';
                this.treatmentUrl = null;

                try {
                    const res = await fetch('/api/ai-assistant', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ message: this.message }),
                    });

                    const data = await res.json();
                    this.response = data.response;
                    this.treatmentUrl = data.treatment_url || null;
                    this.treatmentName = data.treatment_name || '';
                    this.message = '';
                } catch (err) {
                    this.response = 'Sorry, something went wrong. Please try again or browse our treatments below.';
                } finally {
                    this.loading = false;
                }
            }
        };
    }
    </script>

</body>
</html>
