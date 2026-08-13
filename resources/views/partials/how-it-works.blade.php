@php
    $bookingUrl = $bookingUrl ?? '/';
    $title = $title ?? 'How It Works';
    $subtitle = $subtitle ?? "From sign-up to medicine at your door — here's exactly what happens.";
@endphp

<!-- How It Works — Interactive Step-by-Step -->
<section id="how-it-works" class="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50">
    <div class="max-w-5xl mx-auto" x-data="howItWorks()">
        <div class="text-center mb-10">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $title }}</h2>
            <p class="mt-3 text-gray-500">{{ $subtitle }}</p>
        </div>

        <!-- Step indicators -->
        <div class="flex items-center justify-between mb-10 max-w-3xl mx-auto">
            <template x-for="(step, index) in steps" :key="index">
                <div class="flex items-center" :class="index < steps.length - 1 ? 'flex-1' : ''">
                    <button @click="current = index"
                        class="relative w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300"
                        :class="current === index ? 'bg-zapmed-600 text-white shadow-lg scale-110' : (index < current ? 'bg-zapmed-100 text-zapmed-700' : 'bg-gray-100 text-gray-400')">
                        <span x-text="index + 1"></span>
                        <template x-if="index < current">
                            <svg class="absolute w-4 h-4 -top-1 -right-1 text-green-500 bg-white rounded-full" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </template>
                    </button>
                    <template x-if="index < steps.length - 1">
                        <div class="flex-1 h-0.5 mx-2" :class="index < current ? 'bg-zapmed-300' : 'bg-gray-200'"></div>
                    </template>
                </div>
            </template>
        </div>

        <!-- Step content -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="grid grid-cols-1 lg:grid-cols-2 min-h-[300px]">
                <!-- Left: Image -->
                <div class="relative overflow-hidden min-h-[280px]">
                    <img :src="steps[current].image" :alt="steps[current].title" class="absolute inset-0 w-full h-full object-cover">
                    <div class="absolute bottom-4 left-4 bg-black/50 backdrop-blur-sm rounded-lg px-3 py-1.5">
                        <p class="text-white/90 text-xs font-medium" x-text="'Step ' + (current + 1) + ' of ' + steps.length"></p>
                    </div>
                </div>

                <!-- Right: Text content -->
                <div class="p-10 flex flex-col justify-center">
                    <h3 class="text-xl font-bold text-gray-900 mb-3" x-text="steps[current].title"></h3>
                    <p class="text-gray-600 mb-5" x-text="steps[current].description"></p>
                    <ul class="space-y-2 mb-6">
                        <template x-for="detail in steps[current].details" :key="detail">
                            <li class="flex items-start gap-2 text-sm text-gray-600">
                                <svg class="w-4 h-4 text-zapmed-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <span x-text="detail"></span>
                            </li>
                        </template>
                    </ul>
                    <p class="text-xs text-gray-400 italic" x-text="steps[current].time"></p>
                </div>
            </div>
            <div class="px-10 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                <button @click="current = Math.max(0, current - 1)" :disabled="current === 0" class="text-sm text-gray-500 hover:text-gray-700 disabled:opacity-30 disabled:cursor-not-allowed font-medium">&larr; Previous</button>
                <div class="flex gap-1.5">
                    <template x-for="(step, i) in steps" :key="i">
                        <button @click="current = i" class="w-2 h-2 rounded-full transition-colors" :class="current === i ? 'bg-zapmed-600' : 'bg-gray-300'"></button>
                    </template>
                </div>
                <template x-if="current < steps.length - 1">
                    <button @click="current++" class="text-sm text-zapmed-600 hover:text-zapmed-700 font-medium">Next &rarr;</button>
                </template>
                <template x-if="current === steps.length - 1">
                    <a href="{{ $bookingUrl }}" class="text-sm bg-zapmed-600 hover:bg-zapmed-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">Get Started &rarr;</a>
                </template>
            </div>
        </div>
    </div>
</section>
