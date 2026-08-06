<div>
    <x-slot name="header">
        Consultation — {{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Left: Patient Summary Panel -->
        <div class="lg:col-span-1 space-y-4">
            <!-- Patient Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <span class="text-sm font-bold text-blue-700">
                            {{ substr($appointment->patient->first_name, 0, 1) }}{{ substr($appointment->patient->last_name, 0, 1) }}
                        </span>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">{{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}</h3>
                        <p class="text-xs text-gray-500">
                            @if($this->patientAge){{ $this->patientAge }} yrs old &middot; @endif
                            {{ ucfirst($appointment->patient->gender ?? 'Unknown') }}
                        </p>
                    </div>
                </div>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Appointment</span>
                        <span class="font-mono text-gray-700">{{ $appointment->reference }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Type</span>
                        <span class="text-gray-700">{{ $appointment->type_label }}</span>
                    </div>
                    @if($appointment->reason)
                    <div class="pt-2 border-t border-gray-50">
                        <span class="text-gray-500">Reason for visit:</span>
                        <p class="text-gray-700 mt-1">{{ $appointment->reason }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Allergies -->
            @php $profile = $appointment->patient->patientProfile; @endphp
            @if($profile && $profile->allergies->count() > 0)
            <div class="bg-red-50 rounded-xl border border-red-100 p-4">
                <h4 class="text-xs font-semibold text-red-800 uppercase tracking-wide mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    Allergies
                </h4>
                <ul class="space-y-1">
                    @foreach($profile->allergies as $allergy)
                    <li class="text-xs text-red-700">
                        <span class="font-medium">{{ $allergy->allergen }}</span>
                        <span class="text-red-500">({{ $allergy->severity }})</span>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Chronic Conditions -->
            @if($profile && $profile->chronicConditions->count() > 0)
            <div class="bg-blue-50 rounded-xl border border-blue-100 p-4">
                <h4 class="text-xs font-semibold text-blue-800 uppercase tracking-wide mb-2">Chronic Conditions</h4>
                <ul class="space-y-1">
                    @foreach($profile->chronicConditions as $condition)
                    <li class="text-xs text-blue-700">{{ $condition->condition_name }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Patient Metrics -->
            @if($profile)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Health Data</h4>
                <div class="space-y-1 text-xs">
                    @if($profile->blood_type)<div class="flex justify-between"><span class="text-gray-500">Blood Type</span><span class="text-gray-700">{{ $profile->blood_type }}</span></div>@endif
                    @if($profile->height_cm)<div class="flex justify-between"><span class="text-gray-500">Height</span><span class="text-gray-700">{{ $profile->height_cm }} cm</span></div>@endif
                    @if($profile->weight_kg)<div class="flex justify-between"><span class="text-gray-500">Weight</span><span class="text-gray-700">{{ $profile->weight_kg }} kg</span></div>@endif
                    @if($profile->bmi)<div class="flex justify-between"><span class="text-gray-500">BMI</span><span class="text-gray-700">{{ $profile->bmi }}</span></div>@endif
                    <div class="flex justify-between"><span class="text-gray-500">Smoker</span><span class="text-gray-700">{{ $profile->is_smoker ? 'Yes' : 'No' }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Alcohol</span><span class="text-gray-700">{{ $profile->consumes_alcohol ? 'Yes' : 'No' }}</span></div>
                </div>
            </div>
            @endif

            <!-- Assessment Answers -->
            @if($assessment && $assessment->answers)
            <div class="bg-purple-50 rounded-xl border border-purple-100 p-4">
                <h4 class="text-xs font-semibold text-purple-800 uppercase tracking-wide mb-3 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Assessment: {{ $assessment->treatment_name }}
                </h4>
                <div class="space-y-3">
                    @foreach($assessment->answers as $qa)
                        <div>
                            <p class="text-xs text-gray-500">{{ $qa['question'] }}</p>
                            <p class="text-xs font-semibold text-gray-900 mt-0.5">
                                @if(is_array($qa['answer']))
                                    {{ implode(', ', $qa['answer']) }}
                                @else
                                    {{ $qa['answer'] ?: '—' }}
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <!-- Right: Clinical Notes -->
        <div class="lg:col-span-3 space-y-4">
            <!-- Auto-save indicator -->
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Clinical Notes</h2>
                <span x-data="{ saved: false }"
                      x-on:notes-saved.window="saved = true; setTimeout(() => saved = false, 2000)"
                      x-show="saved" x-transition
                      class="text-xs text-green-600 font-medium">
                    Saved
                </span>
            </div>


            <!-- Presenting Complaint -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">Presenting Complaint *</label>
                <textarea wire:model.blur="presenting_complaint" wire:change="saveNotes" rows="3"
                    placeholder="What is the patient's main concern today?"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm"></textarea>
                @error('presenting_complaint') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- History -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">History of Presenting Illness</label>
                <textarea wire:model.blur="history_of_presenting_illness" wire:change="saveNotes" rows="3"
                    placeholder="Duration, onset, aggravating/relieving factors, associated symptoms..."
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm"></textarea>
            </div>

            <!-- Examination Findings -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">Examination Findings</label>
                <textarea wire:model.blur="examination_findings" wire:change="saveNotes" rows="3"
                    placeholder="Relevant findings from telehealth assessment..."
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm"></textarea>
            </div>

            <!-- Diagnosis & ICD-10 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Diagnosis *</label>
                        <input type="text" wire:model.blur="diagnosis" wire:change="saveNotes"
                            placeholder="e.g. Essential hypertension, uncontrolled"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                        @error('diagnosis') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">ICD-10 Code</label>
                        <input type="text" wire:model.blur="icd10_code" wire:change="saveNotes"
                            placeholder="e.g. I10"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm font-mono">
                    </div>
                </div>
            </div>

            <!-- Treatment Plan -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">Treatment Plan *</label>
                <textarea wire:model.blur="treatment_plan" wire:change="saveNotes" rows="4"
                    placeholder="Medications prescribed, lifestyle advice, referrals, investigations..."
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm"></textarea>
                @error('treatment_plan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <!-- Private Doctor Notes -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Private Notes
                    <span class="text-xs text-gray-400 font-normal">(not shared with patient)</span>
                </label>
                <textarea wire:model.blur="doctor_notes" wire:change="saveNotes" rows="2"
                    placeholder="Internal notes, reminders..."
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm bg-gray-50"></textarea>
            </div>


            <!-- Follow-up -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <label class="flex items-center space-x-2 cursor-pointer mb-3">
                    <input wire:model.live="follow_up_required" wire:change="saveNotes" type="checkbox"
                        class="rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                    <span class="text-sm font-medium text-gray-700">Follow-up Required</span>
                </label>

                @if($follow_up_required)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3" x-transition>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Follow-up Date</label>
                        <input type="date" wire:model.blur="follow_up_date" wire:change="saveNotes"
                            min="{{ now()->addDay()->format('Y-m-d') }}"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Follow-up Notes</label>
                        <input type="text" wire:model.blur="follow_up_notes" wire:change="saveNotes"
                            placeholder="e.g. Review blood results"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                    </div>
                </div>
                @endif
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-between pt-4">
                <a href="{{ route('doctor.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">
                    ← Back to Dashboard
                </a>

                <div class="flex items-center space-x-3">
                    <a href="{{ route('doctor.prescription', $consultation) }}"
                        class="px-5 py-2.5 border border-zapmed-300 rounded-lg text-sm font-medium text-zapmed-700 hover:bg-zapmed-50 transition-colors">
                        Write Prescription
                    </a>
                    <button wire:click="saveNotes"
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Save Draft
                    </button>
                    <button wire:click="completeConsultation"
                        wire:confirm="Complete this consultation? This will finalise the clinical record."
                        class="px-5 py-2.5 bg-zapmed-600 hover:bg-zapmed-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm">
                        Complete Consultation
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
