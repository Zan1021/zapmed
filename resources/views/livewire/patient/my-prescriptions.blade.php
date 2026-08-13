<div>
    <x-slot name="header">My Prescriptions</x-slot>

    @if($refillMessage)
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
        <p class="text-sm text-green-700">{{ $refillMessage }}</p>
    </div>
    @endif

    @if($refillError)
    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
        <p class="text-sm text-red-700">{{ $refillError }}</p>
    </div>
    @endif

    <!-- Active Chronic Prescriptions (Refillable) -->
    @if($chronic->isNotEmpty())
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Active Prescriptions</h2>
        <div class="space-y-4">
            @foreach($chronic as $prescription)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-sm font-semibold text-gray-900">{{ $prescription->reference }}</span>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">Chronic</span>
                        </div>
                        <p class="text-sm text-gray-600">{{ $prescription->diagnosis }}</p>
                        <p class="text-xs text-gray-400 mt-1">Dr. {{ $prescription->doctor->last_name }} &middot; {{ $prescription->signed_at?->format('j M Y') }}</p>

                        <!-- Medications -->
                        <div class="mt-3 space-y-1">
                            @foreach($prescription->items as $item)
                            <p class="text-xs text-gray-600">
                                <span class="font-medium">{{ $item->medication_name }}</span> — {{ $item->dosage }}, {{ $item->frequency }}
                            </p>
                            @endforeach
                        </div>
                    </div>

                    <div class="text-right flex-shrink-0 ml-4">
                        <!-- Refill progress -->
                        <p class="text-xs text-gray-500 mb-1">Refills used</p>
                        <p class="text-sm font-bold text-gray-900">{{ $prescription->repeats_used }} / {{ $prescription->repeats }}</p>
                        <div class="w-20 h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden">
                            <div class="h-full bg-zapmed-500 rounded-full" style="width: {{ ($prescription->repeats_used / $prescription->repeats) * 100 }}%"></div>
                        </div>

                        @if($prescription->valid_until)
                        <p class="text-xs text-gray-400 mt-2">Expires: {{ $prescription->valid_until->format('j M Y') }}</p>
                        @endif
                    </div>
                </div>

                <!-- Refill Button -->
                <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
                    <p class="text-xs text-gray-500">{{ $prescription->refills_remaining }} refill{{ $prescription->refills_remaining !== 1 ? 's' : '' }} remaining</p>
                    <button wire:click="requestRefill({{ $prescription->id }})"
                        wire:confirm="Request a refill? You'll be taken to payment."
                        class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg transition-colors">
                        Request Refill — {{ $prescription->formatted_total }}
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Past Prescriptions -->
    <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Prescription History</h2>
        @if($past->isEmpty() && $chronic->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
            <p class="text-sm text-gray-500">No prescriptions yet.</p>
        </div>
        @else
        <div class="space-y-3">
            @foreach($past as $prescription)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-900">{{ $prescription->reference }}</span>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $prescription->status === 'dispensed' ? 'bg-green-100 text-green-700' : ($prescription->status === 'signed' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') }}">
                                {{ ucfirst($prescription->status) }}
                            </span>
                            @if($prescription->is_chronic && !$prescription->hasRefillsRemaining())
                            <span class="text-xs text-gray-400">(all refills used)</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $prescription->diagnosis }} &middot; {{ $prescription->signed_at?->format('j M Y') }}</p>
                    </div>
                    <span class="text-sm font-medium text-gray-700">{{ $prescription->formatted_total }}</span>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <!-- Need a new prescription? -->
    <div class="mt-8 p-6 bg-gray-50 rounded-xl border border-gray-100 text-center">
        <p class="text-sm text-gray-600">Need a new prescription or ran out of refills?</p>
        <a href="{{ route('patient.book') }}" class="inline-block mt-3 px-5 py-2.5 bg-zapmed-600 hover:bg-zapmed-700 text-white rounded-lg text-sm font-semibold transition-colors">
            Book a Consultation
        </a>
    </div>
</div>
