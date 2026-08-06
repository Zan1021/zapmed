<div>
    <x-slot name="header">
        Write Prescription — {{ $consultation->patient->first_name }} {{ $consultation->patient->last_name }}
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Left: Patient Info -->
        <div class="lg:col-span-1 space-y-4">
            <!-- Patient Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <span class="text-sm font-bold text-blue-700">
                            {{ substr($consultation->patient->first_name, 0, 1) }}{{ substr($consultation->patient->last_name, 0, 1) }}
                        </span>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">{{ $consultation->patient->first_name }} {{ $consultation->patient->last_name }}</h3>
                        <p class="text-xs text-gray-500">
                            @if($consultation->patient->date_of_birth){{ $consultation->patient->date_of_birth->age }} yrs old &middot; @endif
                            {{ ucfirst($consultation->patient->gender ?? 'Unknown') }}
                        </p>
                    </div>
                </div>
                @if($consultation->diagnosis)
                <div class="pt-3 border-t border-gray-100">
                    <span class="text-xs text-gray-500">Diagnosis</span>
                    <p class="text-sm text-gray-800 mt-0.5">{{ $consultation->diagnosis }}</p>
                </div>
                @endif
            </div>

            <!-- Allergies -->
            @php $profile = $consultation->patient->patientProfile; @endphp
            @if($profile && $profile->allergies->count() > 0)
            <div class="bg-red-50 rounded-xl border border-red-200 p-4">
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

            <!-- Prescription Settings -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Prescription Settings</h4>

                <label class="flex items-center space-x-2 cursor-pointer mb-3">
                    <input wire:model.live="isChronic" type="checkbox"
                        class="rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                    <span class="text-sm text-gray-700">Chronic Prescription</span>
                </label>

                @if($isChronic)
                <div class="mt-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Repeats (1-6)</label>
                    <select wire:model="repeats"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                        @for($i = 1; $i <= 6; $i++)
                        <option value="{{ $i }}">{{ $i }} {{ $i === 1 ? 'repeat' : 'repeats' }}</option>
                        @endfor
                    </select>
                </div>
                @endif
            </div>
        </div>

        <!-- Right: Prescription Builder -->
        <div class="lg:col-span-3 space-y-4">
            <!-- Medication Search -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Search Medication</h3>

                <div class="relative">
                    <input wire:model.live.debounce.300ms="search" type="text"
                        placeholder="Search by medication name..."
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm pl-10">
                    <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>

                    <!-- Search Results Dropdown -->
                    @if($showResults && count($this->searchResults) > 0)
                    <div class="absolute z-10 w-full mt-1 bg-white rounded-lg border border-gray-200 shadow-lg max-h-60 overflow-y-auto">
                        @foreach($this->searchResults as $med)
                        <button wire:click="selectMedication({{ $med['id'] }})" type="button"
                            class="w-full text-left px-4 py-2.5 hover:bg-gray-50 border-b border-gray-50 last:border-0 transition-colors">
                            <span class="text-sm font-medium text-gray-900">{{ $med['name'] }}</span>
                            <span class="text-xs text-gray-500 ml-2">{{ $med['form'] }} &middot; {{ $med['strength'] }} &middot; {{ $med['schedule'] }}</span>
                        </button>
                        @endforeach
                    </div>
                    @elseif($showResults && count($this->searchResults) === 0)
                    <div class="absolute z-10 w-full mt-1 bg-white rounded-lg border border-gray-200 shadow-lg p-3">
                        <p class="text-sm text-gray-500">No medications found.</p>
                    </div>
                    @endif
                </div>

                <button wire:click="addCustomMedication" type="button"
                    class="mt-3 text-sm text-zapmed-600 hover:text-zapmed-700 font-medium">
                    + Add custom medication
                </button>
            </div>

            <!-- Add Item Form (shows when medication selected or custom mode) -->
            @if($medicationName || $isCustomMedication)
            <div class="bg-white rounded-xl shadow-sm border border-zapmed-100 p-5">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">
                    {{ $isCustomMedication ? 'Add Custom Medication' : 'Add: ' . $medicationName }}
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @if($isCustomMedication)
                    <!-- Custom medication fields -->
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Medication Name *</label>
                        <input wire:model="medicationName" type="text" placeholder="e.g. Amoxicillin"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Form *</label>
                        <select wire:model="medicationForm"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            <option value="tablet">Tablet</option>
                            <option value="capsule">Capsule</option>
                            <option value="injection">Injection</option>
                            <option value="topical">Topical</option>
                            <option value="syrup">Syrup</option>
                            <option value="cream">Cream</option>
                            <option value="drops">Drops</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Strength *</label>
                        <input wire:model="medicationStrength" type="text" placeholder="e.g. 500mg"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                    </div>
                    @endif

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Dosage *</label>
                        <input wire:model="dosage" type="text" placeholder="e.g. 1 tablet"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Frequency *</label>
                        <select wire:model="frequency"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            <option value="once daily">Once daily</option>
                            <option value="twice daily">Twice daily</option>
                            <option value="three times daily">Three times daily</option>
                            <option value="at bedtime">At bedtime</option>
                            <option value="as needed">As needed</option>
                            <option value="every 4 hours">Every 4 hours</option>
                            <option value="every 8 hours">Every 8 hours</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Route *</label>
                        <select wire:model="route"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            <option value="oral">Oral</option>
                            <option value="topical">Topical</option>
                            <option value="sublingual">Sublingual</option>
                            <option value="IM">IM</option>
                            <option value="IV">IV</option>
                            <option value="per rectum">Per rectum</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Duration (days)</label>
                        <input wire:model="durationDays" type="number" min="1" placeholder="e.g. 7"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Quantity *</label>
                        <input wire:model="quantity" type="number" min="1" placeholder="e.g. 30"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                    </div>
                    <div class="md:col-span-2 lg:col-span-3">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Special Instructions</label>
                        <input wire:model="instructions" type="text" placeholder="e.g. Take with food, avoid alcohol"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                    </div>
                </div>

                <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input wire:model="substitutionAllowed" type="checkbox"
                            class="rounded border-gray-300 text-zapmed-600 focus:ring-zapmed-500">
                        <span class="text-sm text-gray-700">Substitution allowed</span>
                    </label>

                    <button wire:click="addItem"
                        class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white rounded-lg text-sm font-medium transition-colors">
                        Add to Prescription
                    </button>
                </div>

                @error('medicationName') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                @error('dosage') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @error('quantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            @endif

            <!-- Prescription Items List -->
            @if(count($items) > 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Prescription Items ({{ count($items) }})</h3>

                <div class="space-y-3">
                    @foreach($items as $index => $item)
                    <div class="flex items-start justify-between p-3 rounded-lg bg-gray-50 border border-gray-100">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2">
                                <span class="text-sm font-medium text-gray-900">{{ $item['medication_name'] }}</span>
                                <span class="text-xs text-gray-500">{{ $item['form'] }} &middot; {{ $item['strength'] }}</span>
                            </div>
                            <p class="text-xs text-gray-600 mt-1">
                                {{ $item['dosage'] }} &middot; {{ $item['frequency'] }} &middot; {{ $item['route'] }}
                                @if($item['duration_days']) &middot; {{ $item['duration_days'] }} days @endif
                                &middot; Qty: {{ $item['quantity'] }}
                            </p>
                            @if($item['instructions'])
                            <p class="text-xs text-gray-500 mt-0.5 italic">{{ $item['instructions'] }}</p>
                            @endif
                            @if(!$item['substitution_allowed'])
                            <span class="inline-block mt-1 text-xs text-red-600 font-medium">No substitution</span>
                            @endif
                        </div>
                        <button wire:click="removeItem({{ $index }})" type="button"
                            class="ml-3 text-gray-400 hover:text-red-500 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Pharmacist Notes -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">Notes for Pharmacist</label>
                <textarea wire:model="pharmacistNotes" rows="2"
                    placeholder="Any special instructions for the pharmacist..."
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm"></textarea>
            </div>

            <!-- Actions -->
            @error('items') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex items-center justify-between pt-4">
                <button wire:click="cancel" type="button"
                    class="text-sm text-gray-500 hover:text-gray-700">
                    &larr; Back to Consultation
                </button>

                <button wire:click="signPrescription"
                    wire:confirm="Sign this prescription? This will finalise and cannot be undone."
                    @if(count($items) === 0) disabled @endif
                    class="px-6 py-2.5 bg-green-600 hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-lg text-sm font-semibold transition-colors shadow-sm">
                    Sign Prescription
                </button>
            </div>
        </div>
    </div>
</div>
