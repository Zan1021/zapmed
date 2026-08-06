<div>
    <x-slot name="header">Book a Consultation</x-slot>

    <!-- Progress indicator -->
    @if($step < 4)
    <div class="mb-8 flex items-center space-x-3 text-sm">
        <span class="{{ $step >= 1 ? 'text-zapmed-600 font-semibold' : 'text-gray-400' }}">1. Choose Doctor</span>
        <svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
        <span class="{{ $step >= 2 ? 'text-zapmed-600 font-semibold' : 'text-gray-400' }}">2. Select Time</span>
        <svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
        <span class="{{ $step >= 3 ? 'text-zapmed-600 font-semibold' : 'text-gray-400' }}">3. Confirm</span>
    </div>
    @endif


    <!-- Step 1: Choose Doctor -->
    @if($step === 1)
    <div>
        <!-- Search & Filter -->
        <div class="flex flex-col sm:flex-row gap-4 mb-6">
            <div class="flex-1">
                <input wire:model.live.debounce.300ms="searchQuery" type="text" placeholder="Search by doctor name..."
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
            </div>
            <select wire:model.live="specialityFilter"
                class="rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                <option value="">All Specialities</option>
                @foreach($this->specialities as $spec)
                    <option value="{{ $spec }}">{{ $spec }}</option>
                @endforeach
            </select>
        </div>

        <!-- Doctor Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($this->doctors as $profile)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:border-zapmed-200 hover:shadow-md transition-all cursor-pointer"
                     wire:click="selectDoctor({{ $profile->user_id }})">
                    <div class="flex items-start space-x-4">
                        <div class="w-14 h-14 bg-zapmed-100 rounded-xl flex items-center justify-center flex-shrink-0">
                            <span class="text-lg font-bold text-zapmed-700">
                                {{ substr($profile->user->first_name, 0, 1) }}{{ substr($profile->user->last_name, 0, 1) }}
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-base font-semibold text-gray-900">Dr. {{ $profile->user->last_name }}</h3>
                            <p class="text-sm text-zapmed-600 font-medium">{{ $profile->speciality }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $profile->qualification }}</p>
                            @if($profile->bio)
                                <p class="text-xs text-gray-400 mt-2 line-clamp-2">{{ $profile->bio }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-50">
                        <div class="flex items-center space-x-4 text-xs text-gray-500">
                            <span class="flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $profile->consultation_duration }} min
                            </span>
                            <span>{{ $profile->formatted_fee }}</span>
                        </div>
                        <span class="text-xs font-medium text-zapmed-600">Select →</span>
                    </div>
                </div>
            @empty
                <div class="col-span-2 text-center py-12 bg-white rounded-xl border border-gray-100">
                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <p class="text-gray-500">No doctors found matching your criteria.</p>
                </div>
            @endforelse
        </div>
    </div>
    @endif


    <!-- Step 2: Select Date & Time -->
    @if($step === 2 && $this->selectedDoctor)
    <div>
        <button wire:click="backToDoctors" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-6">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to doctors
        </button>

        <!-- Selected Doctor Summary -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 bg-zapmed-100 rounded-xl flex items-center justify-center">
                    <span class="text-sm font-bold text-zapmed-700">{{ substr($this->selectedDoctor->first_name, 0, 1) }}{{ substr($this->selectedDoctor->last_name, 0, 1) }}</span>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Dr. {{ $this->selectedDoctor->last_name }}</h3>
                    <p class="text-sm text-gray-500">{{ $this->selectedDoctor->doctorProfile->speciality }} &middot; {{ $this->selectedDoctor->doctorProfile->formatted_fee }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left: Date & Type selection -->
            <div class="lg:col-span-1 space-y-5">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Appointment Date</label>
                    <input wire:model.live="selectedDate" type="date" min="{{ now()->format('Y-m-d') }}"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Consultation Type</label>
                    <select wire:model.live="appointmentType"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                        <option value="general">General Consultation</option>
                        <option value="follow_up">Follow-up</option>
                        <option value="chronic_renewal">Chronic Med Renewal</option>
                        <option value="new_patient">New Patient</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-2">
                        Fee: <span class="font-medium text-gray-700">
                            {{ $appointmentType === 'follow_up' ? $this->selectedDoctor->doctorProfile->formatted_followup_fee : $this->selectedDoctor->doctorProfile->formatted_fee }}
                        </span>
                    </p>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Visit (optional)</label>
                    <textarea wire:model="reason" rows="3" placeholder="Brief description of your concern..."
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-zapmed-500 focus:ring-zapmed-500 text-sm"></textarea>
                </div>
            </div>

            <!-- Right: Time slots -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-medium text-gray-700 mb-4">Available Times for {{ \Carbon\Carbon::parse($selectedDate)->format('l, j M Y') }}</h3>

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
                            class="w-full bg-zapmed-600 hover:bg-zapmed-700 text-white py-3 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                            Continue to Confirmation →
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


    <!-- Step 3: Confirmation -->
    @if($step === 3 && $this->selectedDoctor)
    <div class="max-w-2xl mx-auto">
        <button wire:click="backToTimeSelection" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-6">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back
        </button>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Confirm Your Booking</h2>

            <div class="space-y-4">
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <span class="text-sm text-gray-500">Doctor</span>
                    <span class="text-sm font-medium text-gray-900">Dr. {{ $this->selectedDoctor->first_name }} {{ $this->selectedDoctor->last_name }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <span class="text-sm text-gray-500">Speciality</span>
                    <span class="text-sm font-medium text-gray-900">{{ $this->selectedDoctor->doctorProfile->speciality }}</span>
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
                    <span class="text-sm font-medium text-gray-900">{{ $this->selectedDoctor->doctorProfile->consultation_duration }} minutes</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <span class="text-sm text-gray-500">Type</span>
                    <span class="text-sm font-medium text-gray-900">
                        {{ match($appointmentType) { 'general' => 'General Consultation', 'follow_up' => 'Follow-up', 'chronic_renewal' => 'Chronic Med Renewal', 'new_patient' => 'New Patient', default => $appointmentType } }}
                    </span>
                </div>
                @if($reason)
                <div class="flex items-center justify-between py-3 border-b border-gray-50">
                    <span class="text-sm text-gray-500">Reason</span>
                    <span class="text-sm font-medium text-gray-900 text-right max-w-xs">{{ $reason }}</span>
                </div>
                @endif
                <div class="flex items-center justify-between py-3">
                    <span class="text-base font-semibold text-gray-900">Total</span>
                    <span class="text-lg font-bold text-zapmed-600">
                        R{{ number_format(($appointmentType === 'follow_up' ? $this->selectedDoctor->doctorProfile->followup_fee : $this->selectedDoctor->doctorProfile->consultation_fee) / 100, 2) }}
                    </span>
                </div>
            </div>

            <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                <p class="text-xs text-blue-700">
                    <strong>Note:</strong> Payment will be collected before your consultation. You'll receive a confirmation email with your video call link.
                </p>
            </div>

            <button wire:click="confirmBooking"
                class="w-full mt-6 bg-zapmed-600 hover:bg-zapmed-700 text-white py-3.5 rounded-lg text-sm font-semibold transition-colors shadow-sm">
                Confirm Booking
            </button>
        </div>
    </div>
    @endif


    <!-- Step 4: Success -->
    @if($step === 4 && $bookedAppointment)
    <div class="max-w-lg mx-auto text-center">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
            <div class="w-16 h-16 bg-zapmed-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-zapmed-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Booking Confirmed!</h2>
            <p class="text-gray-500 mt-2">Your appointment has been scheduled.</p>

            <div class="mt-6 p-4 bg-gray-50 rounded-lg text-left">
                <div class="flex items-center justify-between text-sm mb-2">
                    <span class="text-gray-500">Reference</span>
                    <span class="font-mono font-semibold text-gray-900">{{ $bookedAppointment->reference }}</span>
                </div>
                <div class="flex items-center justify-between text-sm mb-2">
                    <span class="text-gray-500">Date & Time</span>
                    <span class="font-medium text-gray-900">{{ $bookedAppointment->appointment_date->format('j M Y') }} at {{ $bookedAppointment->start_time }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Amount</span>
                    <span class="font-medium text-gray-900">{{ $bookedAppointment->formatted_fee }}</span>
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
