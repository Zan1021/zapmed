<div>
    <x-slot name="header">Book a Consultation</x-slot>

    <!-- Progress indicator -->
    @if($step < 7)
    <div class="mb-8 flex items-center space-x-2 text-sm flex-wrap gap-y-2">
        <span class="{{ $step >= 1 ? 'text-zapmed-600 font-semibold' : 'text-gray-400' }}">1. Treatment</span>
        <svg class="w-3 h-3 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
        <span class="{{ $step >= 2 ? 'text-zapmed-600 font-semibold' : 'text-gray-400' }}">2. Assessment</span>
        <svg class="w-3 h-3 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
        <span class="{{ $step >= 3 ? 'text-zapmed-600 font-semibold' : 'text-gray-400' }}">3. Preference</span>
        <svg class="w-3 h-3 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
        <span class="{{ $step >= 4 ? 'text-zapmed-600 font-semibold' : 'text-gray-400' }}">4. Payment</span>
        <svg class="w-3 h-3 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
        <span class="{{ $step >= 5 ? 'text-zapmed-600 font-semibold' : 'text-gray-400' }}">5. Schedule</span>
        <svg class="w-3 h-3 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
        <span class="{{ $step >= 6 ? 'text-zapmed-600 font-semibold' : 'text-gray-400' }}">6. Confirm</span>
    </div>
    @endif


    <!-- Step 1: Choose Treatment Type -->
    @if($step === 1)
    <div class="max-w-2xl">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-1">What do you need help with?</h2>
            <p class="text-sm text-gray-500 mb-6">Select a category, then choose your specific treatment.</p>

            <!-- Categories -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">
                @foreach(config('treatments') as $slug => $category)
                    <button wire:click="$set('appointmentType', '{{ $slug }}')"
                        class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all text-center
                        {{ $appointmentType === $slug ? 'border-zapmed-500 bg-zapmed-50' : 'border-gray-100 hover:border-gray-200 hover:bg-gray-50' }}">
                        <div class="w-10 h-10 rounded-lg overflow-hidden bg-gray-100">
                            <img src="{{ $category['image'] }}" alt="{{ $category['name'] }}" class="w-full h-full object-cover">
                        </div>
                        <p class="text-xs font-semibold {{ $appointmentType === $slug ? 'text-zapmed-900' : 'text-gray-700' }}">{{ $category['name'] }}</p>
                    </button>
                @endforeach
            </div>

            <!-- Treatment dropdown (appears when category selected) -->
            @if($appointmentType && isset(config('treatments')[$appointmentType]))
            <div class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
                <label class="block text-sm font-medium text-gray-700 mb-2">Select Treatment</label>
                <select wire:model.live="selectedTreatment"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                    <option value="">— Choose a treatment —</option>
                    @foreach(config('treatments')[$appointmentType]['treatments'] as $treatmentSlug => $treatment)
                        <option value="{{ $treatmentSlug }}">{{ $treatment['name'] }} — {{ $treatment['price'] }}</option>
                    @endforeach
                </select>

                @if($selectedTreatment && isset(config('treatments')[$appointmentType]['treatments'][$selectedTreatment]))
                    <p class="mt-3 text-xs text-gray-600">
                        {{ config('treatments')[$appointmentType]['treatments'][$selectedTreatment]['tagline'] }}
                    </p>
                @endif
            </div>
            @endif

            @error('appointmentType') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror
            @error('selectedTreatment') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Additional notes (optional)</label>
                <textarea wire:model="reason" rows="2" placeholder="Anything else you'd like the doctor to know..."
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm"></textarea>
            </div>

            <button wire:click="proceedToAssessment"
                class="w-full bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm {{ !$selectedTreatment ? 'opacity-50 cursor-not-allowed' : '' }}"
                {{ !$selectedTreatment ? 'disabled' : '' }}>
                Continue — Assessment Questions →
            </button>
        </div>

        <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-100">
            <p class="text-xs text-blue-700">
                <strong>How it works:</strong> You pick a time that suits you, and we'll match you with the best available doctor for your treatment. If you've seen a doctor before, we'll try to book you with them again.
            </p>
        </div>
    </div>
    @endif


    <!-- Step 2: Assessment Questions -->
    @if($step === 2)
    <div class="max-w-2xl">
        <button wire:click="backToType" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-6">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back
        </button>

        @php
            $questions = config("assessment-questions.{$selectedTreatment}", []);
            $treatmentData = config("treatments.{$appointmentType}.treatments.{$selectedTreatment}");
        @endphp

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6"
             x-data="assessmentForm(@js($questions))">
            <h2 class="text-xl font-semibold text-gray-900 mb-1">Tell us about your condition</h2>
            <p class="text-sm text-gray-500 mb-6">Your answers help the doctor prepare for your <strong>{{ $treatmentData['name'] ?? '' }}</strong> consultation.</p>

            @if($errors->any())
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm font-medium text-red-800">Please complete the required fields:</p>
                    <ul class="mt-2 text-sm text-red-600 list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="space-y-6">
                @foreach($questions as $question)
                    <div x-show="isVisible('{{ $question['id'] }}')" x-transition x-cloak>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            {{ $question['label'] }}
                            @if($question['required']) <span class="text-red-500">*</span> @endif
                        </label>

                        @if($question['type'] === 'text')
                            <input type="text" wire:model="assessmentAnswers.{{ $question['id'] }}"
                                x-on:input="updateAnswer('{{ $question['id'] }}', $event.target.value)"
                                placeholder="{{ $question['placeholder'] ?? '' }}"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">

                        @elseif($question['type'] === 'number')
                            <input type="number" wire:model="assessmentAnswers.{{ $question['id'] }}"
                                x-on:input="updateAnswer('{{ $question['id'] }}', $event.target.value)"
                                placeholder="{{ $question['placeholder'] ?? '' }}"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">

                        @elseif($question['type'] === 'textarea')
                            <textarea wire:model="assessmentAnswers.{{ $question['id'] }}" rows="3"
                                x-on:input="updateAnswer('{{ $question['id'] }}', $event.target.value)"
                                placeholder="{{ $question['placeholder'] ?? '' }}"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm"></textarea>

                        @elseif($question['type'] === 'select')
                            <select wire:model="assessmentAnswers.{{ $question['id'] }}"
                                x-on:change="updateAnswer('{{ $question['id'] }}', $event.target.value)"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                                <option value="">— Select —</option>
                                @foreach($question['options'] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>

                        @elseif($question['type'] === 'radio')
                            <div class="flex flex-wrap gap-3 mt-1">
                                @foreach($question['options'] as $option)
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" wire:model="assessmentAnswers.{{ $question['id'] }}" value="{{ $option }}"
                                            x-on:change="updateAnswer('{{ $question['id'] }}', '{{ $option }}')"
                                            class="text-zapmed-600 focus:ring-zapmed-500 border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>

                        @elseif($question['type'] === 'checkbox')
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-1">
                                @foreach($question['options'] as $optionIndex => $option)
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model="assessmentAnswers.{{ $question['id'] }}.{{ $optionIndex }}" value="{{ $option }}"
                                            x-on:change="toggleCheckbox('{{ $question['id'] }}', '{{ $option }}', $event.target.checked)"
                                            class="rounded text-zapmed-600 focus:ring-zapmed-500 border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">{{ $option }}</span>
                                    </label>
                                @endforeach
                            </div>

                        @elseif($question['type'] === 'file')
                            <input type="file" wire:model="assessmentAnswers.{{ $question['id'] }}"
                                accept="{{ $question['accept'] ?? '*' }}"
                                {{ ($question['multiple'] ?? false) ? 'multiple' : '' }}
                                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-zapmed-50 file:text-zapmed-700 hover:file:bg-zapmed-100">
                        @endif

                        @error("assessmentAnswers.{$question['id']}")
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>

            <button wire:click="proceedToPayment"
                class="w-full mt-8 bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                Continue →
            </button>
        </div>

        <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-100">
            <p class="text-xs text-blue-700">
                <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <strong>Your answers are confidential</strong> and only shared with your assigned doctor.
            </p>
        </div>
    </div>
    @endif


    <!-- Step 3: Communication Preference -->
    @if($step === 3)
    <div class="max-w-2xl">
        <button wire:click="backToAssessment" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-6">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back
        </button>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-1">How would you like to consult?</h2>
            <p class="text-sm text-gray-500 mb-6">Choose the communication method you're most comfortable with. Your doctor will respect your preference.</p>

            <div class="space-y-3">
                <!-- Video option -->
                <label class="flex items-start p-4 rounded-xl border-2 cursor-pointer transition-all {{ $communicationPreference === 'video' ? 'border-zapmed-500 bg-zapmed-50' : 'border-gray-100 hover:border-gray-200 hover:bg-gray-50' }}">
                    <input type="radio" wire:model.live="communicationPreference" value="video" class="mt-0.5 text-zapmed-600 focus:ring-zapmed-500">
                    <div class="ml-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-zapmed-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-sm font-semibold text-gray-900">Video Call</span>
                            <span class="text-xs bg-zapmed-100 text-zapmed-700 px-2 py-0.5 rounded-full font-medium">Recommended</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Face-to-face video consultation with your doctor. Best for visual assessments.</p>
                    </div>
                </label>

                <!-- Audio option -->
                <label class="flex items-start p-4 rounded-xl border-2 cursor-pointer transition-all {{ $communicationPreference === 'audio' ? 'border-zapmed-500 bg-zapmed-50' : 'border-gray-100 hover:border-gray-200 hover:bg-gray-50' }}">
                    <input type="radio" wire:model.live="communicationPreference" value="audio" class="mt-0.5 text-zapmed-600 focus:ring-zapmed-500">
                    <div class="ml-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <span class="text-sm font-semibold text-gray-900">Audio Only</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Voice call without camera. Your doctor won't see you — perfect for sensitive consultations.</p>
                    </div>
                </label>

                <!-- Text option -->
                <label class="flex items-start p-4 rounded-xl border-2 cursor-pointer transition-all {{ $communicationPreference === 'text' ? 'border-zapmed-500 bg-zapmed-50' : 'border-gray-100 hover:border-gray-200 hover:bg-gray-50' }}">
                    <input type="radio" wire:model.live="communicationPreference" value="text" class="mt-0.5 text-zapmed-600 focus:ring-zapmed-500">
                    <div class="ml-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            <span class="text-sm font-semibold text-gray-900">Text Chat Only</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Written consultation via secure chat. No voice or video — maximum privacy.</p>
                    </div>
                </label>
            </div>

            @error('communicationPreference') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror

            <button wire:click="proceedFromCommunicationPreference"
                class="w-full mt-6 bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                Continue to Payment →
            </button>
        </div>

        <div class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-100">
            <p class="text-xs text-blue-700">
                <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <strong>100% confidential.</strong> Your doctor cannot override this preference. You're in control.
            </p>
        </div>
    </div>
    @endif


    <!-- Step 4: Payment -->
    @if($step === 4)
    <div class="max-w-2xl">
        <button wire:click="backToCommunicationPreference" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-6">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back
        </button>

        @php
            $treatmentData = config("treatments.{$appointmentType}.treatments.{$selectedTreatment}");
            $consultationFee = $treatmentData['price_once_off'] ?? $treatmentData['price_monthly'] ?? 45000;
            $duration = $treatmentData['duration'] ?? 15;
        @endphp

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-1">Pay for Your Consultation</h2>
            <p class="text-sm text-gray-500 mb-6">Pay once to unlock the booking calendar and secure your appointment.</p>

            <!-- Consultation summary card -->
            <div class="p-5 bg-gray-50 rounded-xl border border-gray-200 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-base font-semibold text-gray-900">{{ $treatmentData['name'] ?? 'Consultation' }}</p>
                        <p class="text-sm text-gray-500">{{ config("treatments.{$appointmentType}.name") }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-gray-900">R{{ number_format($consultationFee / 100, 0) }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-4 border-t border-gray-200">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-zapmed-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $duration }} min consultation
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-zapmed-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        Video call with doctor
                    </div>
                </div>
            </div>

            <!-- What's included -->
            <div class="mb-6">
                <p class="text-xs font-semibold text-gray-700 mb-2">Included:</p>
                <ul class="space-y-1.5 text-xs text-gray-600">
                    <li class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-zapmed-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Licensed SA doctor consultation
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-zapmed-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        E-prescription emailed to you
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-zapmed-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Personalised treatment plan
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 text-zapmed-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        100% confidential & secure
                    </li>
                </ul>
            </div>

            <button wire:click="proceedToDateTime"
                class="w-full bg-zapmed-600 hover:bg-zapmed-700 text-white py-3.5 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                Pay R{{ number_format($consultationFee / 100, 0) }} & Book Appointment
            </button>

            <p class="text-center text-xs text-gray-400 mt-3">
                <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Secured by PayFast. 256-bit encryption.
            </p>
        </div>
    </div>
    @endif


    <!-- Step 5: Select Date & Time -->
    @if($step === 5)
    <div>
        <button wire:click="backToPayment" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-6">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back
        </button>

        <!-- Payment confirmed badge -->
        <div class="mb-6 flex items-center gap-2 px-4 py-2.5 bg-green-50 border border-green-200 rounded-lg w-fit">
            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span class="text-sm font-medium text-green-800">Payment confirmed — choose your time slot</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Date picker -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Date</label>
                    <input wire:model.live="selectedDate" type="date" min="{{ now()->format('Y-m-d') }}" max="{{ now()->addDays(30)->format('Y-m-d') }}"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    <p class="text-xs text-gray-400 mt-2">Showing all available slots across our doctors.</p>
                </div>
            </div>

            <!-- Right: Time slots -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-medium text-gray-700 mb-4">
                    Available Times — {{ \Carbon\Carbon::parse($selectedDate)->format('l, j M Y') }}
                </h3>

                @error('selectedTime')
                    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    </div>
                @enderror

                @if(count($this->availableSlots) > 0)
                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2">
                        @foreach($this->availableSlots as $slot)
                            <button wire:click="selectTime('{{ $slot }}')"
                                class="px-3 py-2.5 rounded-lg text-sm font-medium transition-all
                                {{ $selectedTime === $slot ? 'bg-zapmed-600 text-white shadow-sm' : 'bg-gray-50 text-gray-700 hover:bg-zapmed-50 hover:text-zapmed-700 border border-gray-200' }}">
                                {{ $slot }}
                            </button>
                        @endforeach
                    </div>

                    @if($selectedTime)
                    <div class="mt-6 pt-4 border-t border-gray-100">
                        <button wire:click="proceedToConfirmation"
                            wire:loading.attr="disabled"
                            class="w-full bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm disabled:opacity-50">
                            <span wire:loading.remove wire:target="proceedToConfirmation">Find My Doctor & Confirm →</span>
                            <span wire:loading wire:target="proceedToConfirmation">Matching you with a doctor...</span>
                        </button>
                    </div>
                    @endif
                @else
                    <div class="text-center py-8">
                        <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="text-sm text-gray-500">No available slots on this date.</p>
                        <p class="text-xs text-gray-400 mt-1">Try selecting a different date.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif


    <!-- Step 6: Confirmation -->
    @if($step === 6 && $this->assignedDoctor)
    <div class="max-w-2xl mx-auto">
        <button wire:click="backToTimeSelection" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-6">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back
        </button>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">We found you a doctor!</h2>
                    <p class="text-sm text-gray-500">Review your booking details below.</p>
                </div>
            </div>

            <div class="space-y-4">
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <span class="text-sm text-gray-500">Doctor</span>
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-zapmed-100 rounded-full flex items-center justify-center">
                            <span class="text-xs font-bold text-zapmed-700">{{ substr($this->assignedDoctor->first_name, 0, 1) }}{{ substr($this->assignedDoctor->last_name, 0, 1) }}</span>
                        </div>
                        <span class="text-sm font-medium text-gray-900">Dr. {{ $this->assignedDoctor->first_name }} {{ $this->assignedDoctor->last_name }}</span>
                    </div>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <span class="text-sm text-gray-500">Speciality</span>
                    <span class="text-sm font-medium text-gray-900">{{ $this->assignedDoctor->doctorProfile->speciality }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <span class="text-sm text-gray-500">Date</span>
                    <span class="text-sm font-medium text-gray-900">{{ \Carbon\Carbon::parse($selectedDate)->format('l, j F Y') }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <span class="text-sm text-gray-500">Time</span>
                    <span class="text-sm font-medium text-gray-900">{{ $selectedTime }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <span class="text-sm text-gray-500">Duration</span>
                    <span class="text-sm font-medium text-gray-900">{{ $this->assignedDoctor->doctorProfile->consultation_duration }} minutes</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <span class="text-sm text-gray-500">Consultation Type</span>
                    <span class="text-sm font-medium text-gray-900">
                        {{ config('treatments')[$appointmentType]['name'] ?? $appointmentType }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <span class="text-sm text-gray-500">Treatment</span>
                    <span class="text-sm font-medium text-gray-900">
                        {{ config('treatments')[$appointmentType]['treatments'][$selectedTreatment]['name'] ?? $selectedTreatment }}
                    </span>
                </div>
                @if($reason)
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <span class="text-sm text-gray-500">Reason</span>
                    <span class="text-sm font-medium text-gray-900 text-right max-w-xs">{{ $reason }}</span>
                </div>
                @endif
                <div class="flex items-center justify-between py-3">
                    <span class="text-base font-semibold text-gray-900">Consultation Fee</span>
                    <span class="text-lg font-bold text-zapmed-600">
                        R{{ number_format(($appointmentType === 'follow_up' ? $this->assignedDoctor->doctorProfile->followup_fee : $this->assignedDoctor->doctorProfile->consultation_fee) / 100, 2) }}
                    </span>
                </div>
            </div>

            <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                <p class="text-xs text-blue-700">
                    <strong>Note:</strong> You'll receive a confirmation email with your video call link. Payment will be collected before the consultation.
                </p>
            </div>

            <button wire:click="confirmBooking"
                wire:loading.attr="disabled"
                class="w-full mt-6 bg-zapmed-600 hover:bg-zapmed-700 text-white py-3.5 rounded-lg text-sm font-semibold transition-colors shadow-sm disabled:opacity-50">
                <span wire:loading.remove wire:target="confirmBooking">Confirm Booking</span>
                <span wire:loading wire:target="confirmBooking">Booking...</span>
            </button>
        </div>
    </div>
    @endif


    <!-- Step 7: Success -->
    @if($step === 7 && $bookedAppointment)
    <div class="max-w-lg mx-auto text-center">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
            <div class="w-16 h-16 bg-zapmed-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-zapmed-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Booking Confirmed!</h2>
            <p class="text-gray-500 mt-2">Your appointment has been scheduled.</p>

            <div class="mt-6 p-4 bg-gray-50 rounded-lg text-left space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Reference</span>
                    <span class="font-mono font-semibold text-gray-900">{{ $bookedAppointment->reference }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Date & Time</span>
                    <span class="font-medium text-gray-900">{{ \Carbon\Carbon::parse($bookedAppointment->appointment_date)->format('j M Y') }} at {{ substr($bookedAppointment->start_time, 0, 5) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Doctor</span>
                    <span class="font-medium text-gray-900">Dr. {{ $this->assignedDoctor->last_name }}</span>
                </div>
            </div>

            <div class="mt-6 flex flex-col sm:flex-row gap-3">
                <a href="{{ route('patient.appointments') }}" class="flex-1 bg-zapmed-600 hover:bg-zapmed-700 text-white py-2.5 rounded-lg text-sm font-semibold transition-colors text-center">
                    View My Appointments
                </a>
                <a href="{{ route('dashboard') }}" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 py-2.5 rounded-lg text-sm font-medium transition-colors text-center">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    @endif
</div>
