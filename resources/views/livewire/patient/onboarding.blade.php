<div>
    <x-slot name="header">Complete Your Profile</x-slot>

    <div class="max-w-3xl mx-auto">
        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                @foreach(['Personal Info', 'Medical History', 'Allergies & Conditions', 'Consent'] as $index => $label)
                    <div class="flex items-center {{ $index < 3 ? 'flex-1' : '' }}">
                        <button wire:click="goToStep({{ $index + 1 }})"
                            class="flex items-center justify-center w-10 h-10 rounded-full text-sm font-semibold transition-colors
                            {{ $currentStep > $index + 1 ? 'bg-zapmed-600 text-white' : ($currentStep === $index + 1 ? 'bg-zapmed-600 text-white ring-4 ring-zapmed-100' : 'bg-gray-200 text-gray-500') }}">
                            @if($currentStep > $index + 1)
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            @else
                                {{ $index + 1 }}
                            @endif
                        </button>
                        @if($index < 3)
                            <div class="flex-1 h-1 mx-3 rounded {{ $currentStep > $index + 1 ? 'bg-zapmed-500' : 'bg-gray-200' }}"></div>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between mt-2 px-1">
                @foreach(['Personal Info', 'Medical History', 'Allergies', 'Consent'] as $label)
                    <span class="text-xs text-gray-500 text-center w-20">{{ $label }}</span>
                @endforeach
            </div>
        </div>


        <!-- Step 1: Personal Information -->
        @if($currentStep === 1)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-1">Personal Information</h2>
            <p class="text-sm text-gray-500 mb-6">Let's start with your basic details.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                    <input wire:model="phone" type="tel" id="phone" placeholder="e.g. 072 123 4567"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth *</label>
                    <input wire:model="date_of_birth" type="date" id="date_of_birth"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    @error('date_of_birth') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender *</label>
                    <select wire:model="gender" id="gender"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                        <option value="">Select gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                    @error('gender') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="id_number" class="block text-sm font-medium text-gray-700 mb-1">SA ID Number</label>
                    <input wire:model="id_number" type="text" id="id_number" placeholder="13 digit ID number"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    <p class="mt-1 text-xs text-gray-400">Optional. Stored encrypted for your security.</p>
                </div>
            </div>

            <h3 class="text-lg font-medium text-gray-900 mt-8 mb-4">Address</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Street Address *</label>
                    <input wire:model="address" type="text" id="address" placeholder="123 Main Road"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700 mb-1">City *</label>
                    <input wire:model="city" type="text" id="city" placeholder="Cape Town"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    @error('city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="province" class="block text-sm font-medium text-gray-700 mb-1">Province *</label>
                    <select wire:model="province" id="province"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                        <option value="">Select province</option>
                        <option value="Eastern Cape">Eastern Cape</option>
                        <option value="Free State">Free State</option>
                        <option value="Gauteng">Gauteng</option>
                        <option value="KwaZulu-Natal">KwaZulu-Natal</option>
                        <option value="Limpopo">Limpopo</option>
                        <option value="Mpumalanga">Mpumalanga</option>
                        <option value="North West">North West</option>
                        <option value="Northern Cape">Northern Cape</option>
                        <option value="Western Cape">Western Cape</option>
                    </select>
                    @error('province') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-1">Postal Code *</label>
                    <input wire:model="postal_code" type="text" id="postal_code" placeholder="8001"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    @error('postal_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
        @endif


        <!-- Step 2: Medical History -->
        @if($currentStep === 2)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-1">Medical History</h2>
            <p class="text-sm text-gray-500 mb-6">This helps your doctor provide better care.</p>

            <h3 class="text-lg font-medium text-gray-900 mb-4">Emergency Contact *</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
                <div>
                    <label for="emergency_contact_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input wire:model="emergency_contact_name" type="text" id="emergency_contact_name"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    @error('emergency_contact_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="emergency_contact_phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input wire:model="emergency_contact_phone" type="tel" id="emergency_contact_phone"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                    @error('emergency_contact_phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="emergency_contact_relationship" class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                    <select wire:model="emergency_contact_relationship" id="emergency_contact_relationship"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                        <option value="">Select</option>
                        <option value="spouse">Spouse/Partner</option>
                        <option value="parent">Parent</option>
                        <option value="child">Child</option>
                        <option value="sibling">Sibling</option>
                        <option value="friend">Friend</option>
                        <option value="other">Other</option>
                    </select>
                    @error('emergency_contact_relationship') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <h3 class="text-lg font-medium text-gray-900 mb-4">Medical Aid (Optional)</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Medical Aid Name</label>
                    <input wire:model="medical_aid_name" type="text" placeholder="e.g. Discovery Health"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Member Number</label>
                    <input wire:model="medical_aid_number" type="text"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                    <input wire:model="medical_aid_plan" type="text" placeholder="e.g. KeyCare Plus"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                </div>
            </div>

            <h3 class="text-lg font-medium text-gray-900 mb-4">Health Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Blood Type</label>
                    <select wire:model="blood_type"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                        <option value="">Unknown</option>
                        <option value="A+">A+</option><option value="A-">A-</option>
                        <option value="B+">B+</option><option value="B-">B-</option>
                        <option value="AB+">AB+</option><option value="AB-">AB-</option>
                        <option value="O+">O+</option><option value="O-">O-</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Height (cm)</label>
                    <input wire:model="height_cm" type="number" step="0.1" placeholder="170"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Weight (kg)</label>
                    <input wire:model="weight_kg" type="number" step="0.1" placeholder="75"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                </div>
                <div class="flex items-end space-x-4 pb-2">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input wire:model="is_smoker" type="checkbox" class="rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                        <span class="text-sm text-gray-700">Smoker</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input wire:model="consumes_alcohol" type="checkbox" class="rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                        <span class="text-sm text-gray-700">Alcohol</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Previous Surgeries</label>
                    <textarea wire:model="surgical_history" rows="3" placeholder="List any previous surgeries..."
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Family Medical History</label>
                    <textarea wire:model="family_history" rows="3" placeholder="Any conditions that run in your family..."
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500"></textarea>
                </div>
            </div>
        </div>
        @endif


        <!-- Step 3: Allergies & Chronic Conditions -->
        @if($currentStep === 3)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-1">Allergies & Chronic Conditions</h2>
            <p class="text-sm text-gray-500 mb-6">This information is critical for safe prescribing. Skip if none apply.</p>

            <!-- Allergies Section -->
            <h3 class="text-lg font-medium text-gray-900 mb-4">Allergies</h3>

            @if(count($allergies) > 0)
            <div class="mb-4 space-y-2">
                @foreach($allergies as $index => $allergy)
                <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg border border-red-100">
                    <div>
                        <span class="font-medium text-gray-900">{{ $allergy['allergen'] }}</span>
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                            {{ $allergy['severity'] === 'severe' ? 'bg-red-100 text-red-800' : ($allergy['severity'] === 'moderate' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800') }}">
                            {{ ucfirst($allergy['severity']) }}
                        </span>
                        @if($allergy['reaction'])
                            <span class="ml-2 text-sm text-gray-500">- {{ $allergy['reaction'] }}</span>
                        @endif
                    </div>
                    <button wire:click="removeAllergy({{ $index }})" class="text-red-500 hover:text-red-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                @endforeach
            </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Allergen</label>
                    <input wire:model="new_allergen" type="text" placeholder="e.g. Penicillin"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                    @error('new_allergen') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Severity</label>
                    <select wire:model="new_allergy_severity"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                        <option value="mild">Mild</option>
                        <option value="moderate">Moderate</option>
                        <option value="severe">Severe</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reaction</label>
                    <input wire:model="new_allergy_reaction" type="text" placeholder="e.g. Rash, swelling"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                </div>
                <button wire:click="addAllergy" class="bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    + Add Allergy
                </button>
            </div>

            <!-- Chronic Conditions Section -->
            <h3 class="text-lg font-medium text-gray-900 mt-10 mb-4">Chronic Conditions</h3>

            @if(count($conditions) > 0)
            <div class="mb-4 space-y-2">
                @foreach($conditions as $index => $condition)
                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg border border-blue-100">
                    <div>
                        <span class="font-medium text-gray-900">{{ $condition['condition_name'] }}</span>
                        @if($condition['diagnosed_date'])
                            <span class="ml-2 text-sm text-gray-500">since {{ \Carbon\Carbon::parse($condition['diagnosed_date'])->format('M Y') }}</span>
                        @endif
                        @if($condition['notes'])
                            <span class="ml-2 text-sm text-gray-500">- {{ $condition['notes'] }}</span>
                        @endif
                    </div>
                    <button wire:click="removeCondition({{ $index }})" class="text-red-500 hover:text-red-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                @endforeach
            </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Condition</label>
                    <input wire:model="new_condition_name" type="text" placeholder="e.g. Hypertension"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                    @error('new_condition_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diagnosed Date</label>
                    <input wire:model="new_condition_date" type="date"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <input wire:model="new_condition_notes" type="text" placeholder="Optional details"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                </div>
                <button wire:click="addCondition" class="bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    + Add Condition
                </button>
            </div>

            @if(count($allergies) === 0 && count($conditions) === 0)
            <div class="mt-6 p-4 bg-gray-50 rounded-lg text-center">
                <p class="text-sm text-gray-500">No allergies or conditions? That's fine — just click "Next" to continue.</p>
            </div>
            @endif
        </div>
        @endif


        <!-- Step 4: Consent -->
        @if($currentStep === 4)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-1">Consent & Agreements</h2>
            <p class="text-sm text-gray-500 mb-6">Please review and accept the following to use Zapmed's services.</p>

            <div class="space-y-5">
                <div class="p-4 border border-gray-200 rounded-lg">
                    <label class="flex items-start space-x-3 cursor-pointer">
                        <input wire:model="consent_terms" type="checkbox" class="mt-1 rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Terms of Service *</p>
                            <p class="text-xs text-gray-500 mt-1">I agree to Zapmed's Terms of Service and understand the limitations of telehealth consultations.</p>
                        </div>
                    </label>
                    @error('consent_terms') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="p-4 border border-gray-200 rounded-lg">
                    <label class="flex items-start space-x-3 cursor-pointer">
                        <input wire:model="consent_privacy" type="checkbox" class="mt-1 rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Privacy Policy *</p>
                            <p class="text-xs text-gray-500 mt-1">I acknowledge that my personal information will be processed in accordance with the Protection of Personal Information Act (POPIA) and Zapmed's Privacy Policy.</p>
                        </div>
                    </label>
                    @error('consent_privacy') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="p-4 border border-gray-200 rounded-lg">
                    <label class="flex items-start space-x-3 cursor-pointer">
                        <input wire:model="consent_data_processing" type="checkbox" class="mt-1 rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Data Processing Consent *</p>
                            <p class="text-xs text-gray-500 mt-1">I consent to the processing of my health data for the purpose of receiving medical services through this platform. Data is stored securely in South Africa.</p>
                        </div>
                    </label>
                    @error('consent_data_processing') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="p-4 border border-gray-200 rounded-lg">
                    <label class="flex items-start space-x-3 cursor-pointer">
                        <input wire:model="consent_medical_records" type="checkbox" class="mt-1 rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                        <div>
                            <p class="text-sm font-medium text-gray-900">Medical Records Access *</p>
                            <p class="text-xs text-gray-500 mt-1">I consent to my treating doctor accessing my medical history, prescriptions, and consultation records on this platform.</p>
                        </div>
                    </label>
                    @error('consent_medical_records') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-6 p-4 bg-zapmed-50 rounded-lg border border-zapmed-100">
                <div class="flex items-start space-x-3">
                    <svg class="w-5 h-5 text-zapmed-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-zapmed-900">Your data is safe</p>
                        <p class="text-xs text-zapmed-700 mt-1">All data is encrypted and stored on servers in South Africa. You can request deletion of your data at any time under POPIA.</p>
                    </div>
                </div>
            </div>
        </div>
        @endif


        <!-- Navigation Buttons -->
        <div class="flex items-center justify-between mt-8">
            <div>
                @if($currentStep > 1)
                    <button wire:click="previousStep"
                        class="inline-flex items-center px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Previous
                    </button>
                @endif
            </div>

            <div>
                @if($currentStep < $totalSteps)
                    <button wire:click="nextStep"
                        class="inline-flex items-center px-6 py-2.5 bg-zapmed-600 hover:bg-zapmed-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm">
                        Next Step
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                @else
                    <button wire:click="complete"
                        class="inline-flex items-center px-6 py-2.5 bg-zapmed-600 hover:bg-zapmed-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Complete Registration
                    </button>
                @endif
            </div>
        </div>

        <!-- Step indicator text -->
        <p class="text-center text-xs text-gray-400 mt-4">Step {{ $currentStep }} of {{ $totalSteps }}</p>
    </div>
</div>
