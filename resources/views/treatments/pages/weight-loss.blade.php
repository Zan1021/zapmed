<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Weight Loss - Online Treatment & Health Coach Support | Zapmed</title>
    <meta name="description" content="Doctor-led GLP-1 weight loss with personalised Health Coach support. Licensed SA doctors, discreet delivery, cancel anytime.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=noto-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white">

    @include('partials.public-nav')

    <!-- Hero Section -->
    <section class="pt-32 pb-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <span class="inline-flex items-center bg-zapmed-50 text-zapmed-700 px-3 py-1 rounded-full text-xs font-medium mb-4">
                        Weight Loss Programme
                    </span>
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-gray-900 tracking-tight leading-tight">
                        Lose weight with a doctor in your corner – all from home.
                    </h1>
                    <p class="mt-6 text-lg text-gray-600 leading-relaxed">
                        Zapmed's medical weight-loss programme is built around your health, your goals, and your real life. You'll work with a licensed Partner Doctor and a dedicated Health Coach to design a treatment plan that's clinically appropriate, personally supported, and quietly delivered to your door.
                    </p>
                    <p class="mt-4 text-base text-gray-500">
                        Where appropriate, treatment may include clinically approved GLP-1 medication (such as semaglutide), prescribed by your Partner Doctor after a private medical assessment.
                    </p>
                    <div class="mt-8">
                        <a href="{{ route('patient.book', ['category' => 'weight-loss', 'treatment' => 'weight-loss']) }}" class="inline-block bg-zapmed-600 hover:bg-zapmed-700 text-white px-8 py-4 rounded-xl text-base font-semibold transition-all shadow-lg">
                            Start Your Assessment
                        </a>
                    </div>
                </div>
                <div class="hidden lg:block">
                    <img src="/images/treatments/Weightloss/Weight_Loss_Treatments.png" alt="Weight loss treatment" class="w-full h-auto object-contain">
                </div>
            </div>
        </div>
    </section>

    @include('partials.how-it-works', ['bookingUrl' => route('patient.book', ['category' => 'weight-loss', 'treatment' => 'weight-loss'])])


    <!-- Pricing -->
    <section class="py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto text-center">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">One simple monthly subscription</h2>
            <div class="mt-8 bg-white rounded-2xl shadow-lg border border-gray-100 p-8 max-w-md mx-auto">
                <p class="text-sm font-semibold text-zapmed-600 uppercase tracking-wide">Subscription Service</p>
                <div class="mt-4">
                    <span class="text-4xl font-extrabold text-gray-900">R450</span>
                    <span class="text-gray-500">/pm</span>
                </div>
                <p class="mt-4 text-sm text-gray-500">Treatment costs billed separately based on your prescribed medication and dosage.</p>
                <p class="mt-3 text-sm text-gray-600">Includes a Doctor consult and dedicated Health Coach as part of your medically guided journey.</p>
                <ul class="mt-6 space-y-3 text-left">
                    <li class="flex items-center text-sm text-gray-600"><svg class="w-4 h-4 mr-2 text-zapmed-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Licensed Partner Doctor consultation</li>
                    <li class="flex items-center text-sm text-gray-600"><svg class="w-4 h-4 mr-2 text-zapmed-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Dedicated Health Coach (registered dietitian)</li>
                    <li class="flex items-center text-sm text-gray-600"><svg class="w-4 h-4 mr-2 text-zapmed-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Ongoing WhatsApp support</li>
                    <li class="flex items-center text-sm text-gray-600"><svg class="w-4 h-4 mr-2 text-zapmed-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Discreet delivery nationwide</li>
                    <li class="flex items-center text-sm text-gray-600"><svg class="w-4 h-4 mr-2 text-zapmed-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Prescription management</li>
                    <li class="flex items-center text-sm text-gray-600"><svg class="w-4 h-4 mr-2 text-zapmed-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Cancel anytime — no contracts</li>
                </ul>
                <p class="mt-6 text-xs text-gray-400">Cost of medication is excluded and depends on the dosage and type prescribed by your Partner Doctor.</p>
                <a href="{{ route('patient.book', ['category' => 'weight-loss', 'treatment' => 'weight-loss']) }}" class="mt-6 block w-full bg-zapmed-600 hover:bg-zapmed-700 text-white py-3.5 rounded-xl text-sm font-semibold transition-colors shadow-sm">
                    Start Your Assessment
                </a>
            </div>
        </div>
    </section>

    <!-- Health Coach Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50">
        <div class="max-w-5xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Your Health Coach is with you the whole way</h2>
                    <p class="mt-4 text-gray-600 leading-relaxed">
                        Your subscription doesn't just cover medical oversight. It gives you flexible, ongoing support from a dedicated registered dietitian – to help you navigate food choices, habits, side effects, motivation, and long-term consistency.
                    </p>
                    <ul class="mt-6 space-y-3">
                        <li class="flex items-start gap-3"><svg class="w-5 h-5 text-zapmed-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><span class="text-sm text-gray-600">Personalised nutrition guidance</span></li>
                        <li class="flex items-start gap-3"><svg class="w-5 h-5 text-zapmed-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><span class="text-sm text-gray-600">Side-effect management support</span></li>
                        <li class="flex items-start gap-3"><svg class="w-5 h-5 text-zapmed-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><span class="text-sm text-gray-600">Motivation and accountability</span></li>
                        <li class="flex items-start gap-3"><svg class="w-5 h-5 text-zapmed-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><span class="text-sm text-gray-600">Real-life lifestyle adjustments</span></li>
                    </ul>
                </div>
                <div>
                    <img src="/images/treatments/placeholder.svg" alt="Health Coach support" class="rounded-2xl shadow-lg w-full object-cover" style="max-height: 400px;">
                </div>
            </div>
        </div>
    </section>

    <!-- Delivery info -->
    <section class="py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-zapmed-50 rounded-2xl p-8 border border-zapmed-100">
                <p class="text-gray-700 leading-relaxed">
                    Our weight-loss treatment offering allows you to consult with one of our Partner Doctors virtually for R450 p.m. Have your prescribed medication discreetly delivered to your door, or ask your assigned Partner Doctor during your consultation to receive your prescription via email for free.
                </p>
            </div>
        </div>
    </section>


    <!-- Preparing for Consultation -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-6">Preparing for Your Weight Loss Consultation</h2>
            <div class="prose prose-gray max-w-none">
                <p class="text-gray-600 leading-relaxed">As this consultation is conducted via telemedicine, physical examination is limited. For this reason, baseline blood investigations are often recommended to assist our doctors in making a safe, appropriate, and effective treatment decision.</p>
                <p class="mt-4 text-gray-600 leading-relaxed">Some weight-loss medications require baseline assessment and ongoing monitoring. The need for blood tests depends on your medical history, existing conditions, and potential contraindications. Not having blood results available does not prevent your consultation or discussion of treatment options.</p>
                <p class="mt-4 text-gray-600 leading-relaxed">If you already have recent blood results, please email them in advance to <a href="mailto:doctors@zapmed.co.za" class="text-zapmed-600 hover:text-zapmed-700 font-medium">doctors@zapmed.co.za</a> in preparation for your consultation. If required, your consulting doctor may request some or all blood tests during or after your appointment.</p>
                <p class="mt-4 text-gray-600 leading-relaxed">If you would like to arrange blood tests beforehand, please email the same address and we will gladly assist.</p>
            </div>
            <div class="mt-8 p-5 bg-white rounded-xl border border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Blood tests that may be requested:</h3>
                <p class="text-sm text-gray-600">HbA1c or fasting glucose, urea, creatinine and eGFR, liver function tests (ALT, AST, ALP, GGT, bilirubin), thyroid function (TSH, Free T4), lipid profile, full blood count, and in selected cases amylase or lipase.</p>
            </div>
        </div>
    </section>

    <!-- The Support Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <img src="/images/treatments/placeholder.svg" alt="Weight loss support" class="rounded-2xl shadow-lg w-full object-cover" style="max-height: 400px;">
                </div>
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">The Support You've Been Looking For</h2>
                    <p class="mt-4 text-gray-600 leading-relaxed">
                        Weight loss medications like GLP-1's work by regulating appetite, helping you feel fuller for longer, and supporting healthier eating habits.
                    </p>
                    <p class="mt-4 text-gray-600 leading-relaxed">
                        These treatments are most effective when paired with balanced nutrition, regular movement, and lifestyle adjustments.
                    </p>
                    <p class="mt-4 text-gray-600 leading-relaxed">
                        Common side effects may include nausea, bloating, or mild stomach upset, but our Partner Doctors and Health Coaches will guide you on safe use and what to expect. Our Partner Doctors are here to help you find the right plan for your health goals.
                    </p>
                </div>
            </div>
        </div>
    </section>


    <!-- Calculators -->
    <section class="py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">Check Your Numbers</h2>
                <p class="mt-3 text-gray-500">Understand where you are — and where you could be.</p>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- BMI Calculator -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8" x-data="bmiCalculator()">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">BMI Calculator</h3>
                    <p class="text-sm text-gray-500 mb-6">Calculate your Body Mass Index to understand your weight category.</p>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Height (cm)</label>
                            <input type="number" x-model="height" @input="calculate()" placeholder="170" min="100" max="250" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Weight (kg)</label>
                            <input type="number" x-model="weight" @input="calculate()" placeholder="75" min="30" max="300" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                        </div>
                    </div>
                    <button @click="calculate()" class="w-full bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors">Calculate My BMI</button>
                    <div x-show="bmi > 0" x-transition class="mt-6">
                        <div class="text-center mb-4">
                            <span class="text-5xl font-bold" :class="categoryColor" x-text="bmi.toFixed(1)"></span>
                            <p class="text-sm font-medium mt-1" :class="categoryColor" x-text="category"></p>
                        </div>
                        <div class="relative h-4 rounded-full overflow-hidden bg-gray-100 mb-3">
                            <div class="absolute inset-0 flex"><div class="w-1/4 bg-blue-400"></div><div class="w-1/4 bg-green-400"></div><div class="w-1/4 bg-amber-400"></div><div class="w-1/4 bg-red-400"></div></div>
                            <div class="absolute top-0 bottom-0 w-1 bg-gray-900 rounded transition-all duration-500 shadow-lg" :style="'left: ' + gaugePosition + '%'"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-400 mb-4"><span>Underweight</span><span>Normal</span><span>Overweight</span><span>Obese</span></div>
                        <div class="p-4 rounded-lg" :class="insightBg"><p class="text-sm" :class="insightText" x-text="insight"></p></div>
                        <div class="mt-4 text-center"><p class="text-xs text-gray-500">Healthy weight for your height: <span class="font-semibold text-gray-700" x-text="healthyRange"></span></p></div>
                        <a href="{{ route('patient.book', ['category' => 'weight-loss', 'treatment' => 'weight-loss']) }}" class="mt-4 block w-full text-center bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                            Start Your Weight Loss Journey
                        </a>
                    </div>
                    <div x-show="bmi === 0" class="mt-6 text-center py-8">
                        <svg class="w-12 h-12 text-gray-200 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        <p class="text-sm text-gray-400">Enter your height and weight to see your BMI</p>
                    </div>
                </div>

                <!-- Weight Loss Projector -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8" x-data="weightLossCalculator()">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Weight Loss Projector</h3>
                    <p class="text-sm text-gray-500 mb-6">See how much you could lose with doctor-guided GLP-1 treatment.</p>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Current Weight (kg)</label>
                            <input type="number" x-model="currentWeight" @input="project()" placeholder="95" min="50" max="300" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Goal Weight (kg)</label>
                            <input type="number" x-model="goalWeight" @input="project()" placeholder="75" min="40" max="250" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                        </div>
                    </div>
                    <div x-show="showResults" x-transition class="mt-6">
                        <div class="grid grid-cols-3 gap-3 mb-6">
                            <div class="bg-zapmed-50 rounded-lg p-3 text-center"><p class="text-xs text-zapmed-600 font-medium">3 Months</p><p class="text-2xl font-bold text-zapmed-700" x-text="month3 + ' kg'"></p><p class="text-xs text-zapmed-500" x-text="'-' + loss3 + ' kg'"></p></div>
                            <div class="bg-zapmed-100 rounded-lg p-3 text-center"><p class="text-xs text-zapmed-600 font-medium">6 Months</p><p class="text-2xl font-bold text-zapmed-700" x-text="month6 + ' kg'"></p><p class="text-xs text-zapmed-500" x-text="'-' + loss6 + ' kg'"></p></div>
                            <div class="bg-zapmed-200 rounded-lg p-3 text-center"><p class="text-xs text-zapmed-700 font-medium">12 Months</p><p class="text-2xl font-bold text-zapmed-800" x-text="month12 + ' kg'"></p><p class="text-xs text-zapmed-600" x-text="'-' + loss12 + ' kg'"></p></div>
                        </div>
                        <div class="mb-4">
                            <div class="flex justify-between text-xs text-gray-500 mb-1"><span x-text="'Start: ' + currentWeight + ' kg'"></span><span x-text="'Goal: ' + goalWeight + ' kg'"></span></div>
                            <div class="h-5 bg-gray-100 rounded-full overflow-hidden"><div class="h-full bg-gradient-to-r from-zapmed-400 to-zapmed-600 rounded-full transition-all duration-1000" :style="'width: ' + progressPercent + '%'"></div></div>
                            <p class="text-xs text-gray-500 text-center mt-1" x-text="percentLoss + '% of excess weight projected in 12 months'"></p>
                        </div>
                        <div class="p-4 bg-zapmed-50 rounded-lg border border-zapmed-100"><p class="text-sm text-zapmed-800"><strong>Clinical data:</strong> Patients on GLP-1 medication typically lose 15-20% of body weight over 12 months with medical supervision and lifestyle changes.</p></div>
                        <a href="{{ route('patient.book', ['category' => 'weight-loss', 'treatment' => 'weight-loss']) }}" class="mt-4 block w-full text-center bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                            Start Your Weight Loss Journey
                        </a>
                    </div>
                    <div x-show="!showResults" class="mt-6 text-center py-8">
                        <svg class="w-12 h-12 text-gray-200 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        <p class="text-sm text-gray-400">Enter your current and goal weight to see projections</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50">
        <div class="max-w-3xl mx-auto">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 text-center mb-10">Weight-Loss Treatment FAQ's</h2>

            <div class="space-y-3" x-data="{ open: null }">
                @php
                $faqs = [
                    ['q' => 'How much does Zapmed\'s weight-loss service cost?', 'a' => 'Zapmed\'s weight-loss service is offered on a monthly subscription basis, providing ongoing medical support, prescription management, and personalised health coach support throughout your treatment journey. The monthly subscription fee is R450 per month, paid privately. Medication costs are separate and start from approximately R1,210 depending on your prescribed treatment and dosage. Medication costs may be claimable through your medical aid, however approval and cover are dependent on your individual medical aid plan and are not guaranteed. Some medication pens may last for 2 months or longer depending on your dosage and treatment plan. During this time, the R450 monthly subscription fee will still apply, as this covers your ongoing personalised health coach support and continuous care.'],
                    ['q' => 'Does Zapmed replace my primary doctor?', 'a' => 'No, our service is complementary to your existing healthcare provider.'],
                    ['q' => 'Can I claim my consultation and medication costs from my medical aid?', 'a' => 'Your consultation fee will be charged privately via Zapmed. After your consultation, you\'ll receive an invoice containing the relevant telehealth consultation ICD code, which you can use to submit a claim directly to your medical aid provider. Medication costs may be claimed on your behalf by our partner pharmacy. Please note that successful claims depend on your specific medical aid and plan benefits, and approval cannot be guaranteed. If a claim is unsuccessful, you\'ll be notified and will need to settle the medication cost in cash to proceed. Alternatively, you\'re welcome to pay cash for both your consultation and medication, and use the invoices provided to claim directly from your medical aid provider.'],
                    ['q' => 'When and how is my medication delivered?', 'a' => 'Once you have completed your consultation and paid for your medication, your order will be dispatched and delivered within 1-3 business days, in discreet packaging to your door. As most weight loss medications are fridge-line medications, these are labelled and delivered accordingly within 48 hours. Fridge-line medications such as GLP-1 agonists must remain within a controlled temperature range to stay effective. In most cases, they must be stored within your fridge. These instructions will be discussed with you during your consultation and within your medication instructions.'],
                    ['q' => 'What is medical weight loss?', 'a' => 'Medical weight loss uses prescription medications, such as GLP-1 receptor agonists, under the supervision of a licensed doctor, to help you safely reduce weight when lifestyle changes alone are not enough.'],
                    ['q' => 'What causes weight gain and difficulty losing weight?', 'a' => 'Factors include genetics, hormones, metabolism, lifestyle, certain medications, and underlying health conditions. Our Partner Doctors will explore these with you to recommend the right treatment plan.'],
                    ['q' => 'Who is eligible for treatment?', 'a' => 'Weight-loss medication may be recommended if you have a BMI of 30 or higher, or a BMI of 27 or higher with a weight-related health condition (such as type 2 diabetes, high blood pressure, or high cholesterol).'],
                    ['q' => 'Can I get weight-loss treatment online?', 'a' => 'Yes. After completing a medical assessment and virtual consultation, your doctor can prescribe treatment if appropriate.'],
                    ['q' => 'How effective are weight-loss medications?', 'a' => 'GLP-1 treatments can help patients lose an average of 10-22% of body weight when combined with healthy lifestyle changes.'],
                    ['q' => 'Can weight come back after stopping treatment?', 'a' => 'Yes, weight regain can occur if lifestyle changes are not maintained. Doctors may suggest a long-term plan to support sustainable results. We also have your online health coach to give you tips and guidance towards maintaining your lifestyle changes.'],
                    ['q' => 'Are there risks or side effects?', 'a' => 'Yes, side effects could include nausea, constipation, or stomach upset. Serious risks are rare but will be discussed during your consultation.'],
                    ['q' => 'How long is the treatment plan?', 'a' => 'Treatment plans vary. Some patients stay on medication for several months, while others may continue longer, depending on health goals and medical advice.'],
                ];
                @endphp

                @foreach($faqs as $index => $faq)
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm">
                    <button @click="open = open === {{ $index }} ? null : {{ $index }}"
                        class="w-full flex items-center justify-between p-5 text-left hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-medium text-gray-900 pr-4">{{ $faq['q'] }}</span>
                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0 transition-transform duration-200" :class="open === {{ $index }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open === {{ $index }}" x-transition x-cloak class="px-5 pb-5 border-t border-gray-50">
                        <p class="text-sm text-gray-600 leading-relaxed pt-3">{{ $faq['a'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <livewire:treatment-testimonials category="weight-loss" />

    <!-- CTA -->
    <section class="py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto bg-gray-900 rounded-3xl p-12 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold text-white">Ready to start your weight-loss journey?</h2>
            <p class="mt-4 text-lg text-gray-400">Complete your assessment in just a few minutes. A licensed doctor will review it and guide you to the right plan.</p>
            <a href="{{ route('patient.book', ['category' => 'weight-loss', 'treatment' => 'weight-loss']) }}" class="mt-8 inline-block bg-zapmed-500 hover:bg-zapmed-600 text-white px-8 py-4 rounded-xl text-base font-semibold transition-all shadow-lg">
                Start Your Assessment
            </a>
        </div>
    </section>

    @include('partials.public-footer')

<script>
function bmiCalculator() {
    return {
        height: '', weight: '', bmi: 0, category: '', categoryColor: '', gaugePosition: 0, insight: '', insightBg: '', insightText: '', healthyRange: '',
        calculate() {
            if (!this.height || !this.weight || this.height < 100 || this.weight < 30) { this.bmi = 0; return; }
            const h = this.height / 100;
            this.bmi = this.weight / (h * h);
            if (this.bmi < 18.5) { this.category = 'Underweight'; this.categoryColor = 'text-blue-600'; this.insight = 'Your BMI suggests you are underweight. Consider consulting a doctor about healthy weight gain strategies.'; this.insightBg = 'bg-blue-50'; this.insightText = 'text-blue-800'; }
            else if (this.bmi < 25) { this.category = 'Normal Weight'; this.categoryColor = 'text-green-600'; this.insight = 'Your BMI is in the healthy range. Maintain your current lifestyle with regular exercise and balanced nutrition.'; this.insightBg = 'bg-green-50'; this.insightText = 'text-green-800'; }
            else if (this.bmi < 30) { this.category = 'Overweight'; this.categoryColor = 'text-amber-600'; this.insight = 'Your BMI indicates you are overweight. A doctor-guided weight management programme could help you reach a healthier weight.'; this.insightBg = 'bg-amber-50'; this.insightText = 'text-amber-800'; }
            else { this.category = 'Obese'; this.categoryColor = 'text-red-600'; this.insight = 'Your BMI indicates obesity. Medical weight-loss treatment such as GLP-1 medication may be clinically appropriate. Speak to a doctor.'; this.insightBg = 'bg-red-50'; this.insightText = 'text-red-800'; }
            this.gaugePosition = Math.min(Math.max(((this.bmi - 15) / 25) * 100, 2), 98);
            const minW = (18.5 * h * h).toFixed(0); const maxW = (24.9 * h * h).toFixed(0);
            this.healthyRange = minW + ' - ' + maxW + ' kg';
        }
    }
}
function weightLossCalculator() {
    return {
        currentWeight: '', goalWeight: '', showResults: false, month3: 0, month6: 0, month12: 0, loss3: 0, loss6: 0, loss12: 0, progressPercent: 0, percentLoss: 0,
        project() {
            if (!this.currentWeight || !this.goalWeight || this.currentWeight <= this.goalWeight || this.currentWeight < 50) { this.showResults = false; return; }
            this.showResults = true;
            const cw = parseFloat(this.currentWeight); const gw = parseFloat(this.goalWeight);
            const loss3Raw = cw * 0.05; const loss6Raw = cw * 0.10; const loss12Raw = cw * 0.17;
            this.loss3 = Math.min(loss3Raw, cw - gw).toFixed(1); this.loss6 = Math.min(loss6Raw, cw - gw).toFixed(1); this.loss12 = Math.min(loss12Raw, cw - gw).toFixed(1);
            this.month3 = (cw - this.loss3).toFixed(1); this.month6 = (cw - this.loss6).toFixed(1); this.month12 = (cw - this.loss12).toFixed(1);
            const totalToLose = cw - gw;
            this.progressPercent = Math.min((this.loss12 / totalToLose) * 100, 100).toFixed(0);
            this.percentLoss = ((this.loss12 / cw) * 100).toFixed(0);
        }
    }
}
</script>
</body>
</html>
