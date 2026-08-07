<div>
    <x-slot name="header">My Prescriptions</x-slot>

    <!-- Payment Form Modal -->
    @if($showPaymentForm && $payingPrescriptionId)
        @php $payingRx = \App\Models\Prescription::find($payingPrescriptionId); @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">Confirm Delivery Address</h3>
                    <p class="text-sm text-gray-500 mt-1">Where should your medication be delivered?</p>
                </div>
                <form wire:submit="confirmAndPay" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Street Address *</label>
                        <textarea wire:model="deliveryAddress" rows="2" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="123 Main Road, Apartment 4B"></textarea>
                        @error('deliveryAddress') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">City *</label>
                            <input wire:model="deliveryCity" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                            @error('deliveryCity') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Province *</label>
                            <select wire:model="deliveryProvince" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                                <option value="">Select...</option>
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
                            @error('deliveryProvince') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code *</label>
                            <input wire:model="deliveryPostalCode" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                            @error('deliveryPostalCode') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                            <input wire:model="deliveryPhone" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                            @error('deliveryPhone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Instructions</label>
                        <input wire:model="deliveryInstructions" type="text" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="e.g. Ring buzzer, gate code 1234">
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                        <p class="text-lg font-bold text-gray-900">Total: {{ $payingRx?->formatted_total }}</p>
                        <div class="flex space-x-3">
                            <button type="button" wire:click="cancelPayment" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">Cancel</button>
                            <button type="submit" class="px-5 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-medium rounded-lg transition-colors">Pay & Order</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Viewing Prescription Detail -->
    @if($viewingPrescription)
        <div class="mb-6">
            <button wire:click="closeView" class="text-sm text-gray-500 hover:text-gray-700 mb-4">&larr; Back to list</button>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $viewingPrescription->reference }}</h3>
                        <p class="text-sm text-gray-500">Prescribed by Dr. {{ $viewingPrescription->doctor->last_name }} &middot; {{ $viewingPrescription->signed_at?->format('j M Y') }}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $viewingPrescription->payment_status === 'paid' ? 'green' : ($viewingPrescription->payment_status === 'unpaid' ? 'amber' : 'gray') }}-100 text-{{ $viewingPrescription->payment_status === 'paid' ? 'green' : ($viewingPrescription->payment_status === 'unpaid' ? 'amber' : 'gray') }}-700">
                            {{ ucfirst($viewingPrescription->payment_status) }}
                        </span>
                        @if($viewingPrescription->pharmacy_status === 'dispatched')
                            <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Sent to Pharmacy</span>
                        @endif
                    </div>
                </div>

                <!-- Medications -->
                <div class="divide-y divide-gray-50">
                    @foreach($viewingPrescription->items as $item)
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $item->medication_name }} {{ $item->strength }}</p>
                                <p class="text-xs text-gray-500">{{ $item->form }} &middot; {{ $item->dosage }} &middot; {{ $item->frequency }}</p>
                                @if($item->instructions)
                                    <p class="text-xs text-gray-400 mt-1">{{ $item->instructions }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-700">{{ $item->formatted_line_total }}</p>
                                <p class="text-xs text-gray-400">x{{ $item->quantity }}</p>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Total -->
                <div class="p-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Total Medication Cost</span>
                    <span class="text-lg font-bold text-gray-900">{{ $viewingPrescription->formatted_total }}</span>
                </div>

                <!-- Pay button if unpaid -->
                @if($viewingPrescription->payment_status === 'unpaid')
                <div class="p-4 border-t border-gray-100">
                    <button wire:click="startPayment({{ $viewingPrescription->id }})"
                        class="w-full py-2.5 bg-zapmed-600 hover:bg-zapmed-700 text-white text-sm font-semibold rounded-lg transition-colors">
                        Pay for Medication
                    </button>
                </div>
                @endif

                <!-- Repeat button for chronic prescriptions -->
                @if($viewingPrescription->is_chronic && $viewingPrescription->payment_status === 'paid')
                <div class="p-4 border-t border-gray-100">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Chronic Prescription</p>
                            @if($viewingPrescription->repeats > 0)
                                <p class="text-xs text-gray-500">{{ $viewingPrescription->repeats - $viewingPrescription->repeats_used }} repeats remaining</p>
                            @else
                                <p class="text-xs text-gray-500">Unlimited repeats (review required periodically)</p>
                            @endif
                        </div>
                        @php
                            $canRepeat = $viewingPrescription->repeats === 0 || $viewingPrescription->repeats_used < $viewingPrescription->repeats;
                        @endphp
                        @if($canRepeat)
                            <button wire:click="requestRepeat({{ $viewingPrescription->id }})"
                                wire:confirm="Request a repeat of this prescription? You'll be asked to confirm delivery and pay for medication."
                                class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors">
                                Reorder Medication
                            </button>
                        @else
                            <span class="text-xs text-amber-600 font-medium">No repeats left — book a consultation</span>
                        @endif
                    </div>

                    <!-- Estimated next refill -->
                    @php
                        $maxDuration = $viewingPrescription->items->max('duration_days');
                        $refillDate = $viewingPrescription->paid_at?->addDays($maxDuration ?? 30);
                    @endphp
                    @if($refillDate)
                    <div class="p-3 bg-blue-50 rounded-lg border border-blue-100">
                        <p class="text-xs text-blue-700">
                            <span class="font-medium">Estimated refill date:</span> {{ $refillDate->format('j M Y') }}
                            @if($refillDate->isPast())
                                — <span class="text-red-600 font-medium">Overdue</span>
                            @elseif($refillDate->diffInDays(now()) <= 7)
                                — <span class="text-amber-600 font-medium">Due soon</span>
                            @endif
                        </p>
                    </div>
                    @endif
                </div>
                @endif

                <!-- Delivery info if dispatched -->
                @if($viewingPrescription->isDispatched())
                <div class="p-4 border-t border-gray-100">
                    <p class="text-xs font-medium text-gray-500 uppercase mb-2">Delivery Details</p>
                    <p class="text-sm text-gray-700">{{ $viewingPrescription->full_delivery_address }}</p>
                    @if($viewingPrescription->pharmacy_reference)
                        <p class="text-xs text-gray-400 mt-1">Pharmacy ref: {{ $viewingPrescription->pharmacy_reference }}</p>
                    @endif
                </div>
                @endif
            </div>
        </div>
    @else
        <!-- Prescription List -->
        @if($this->prescriptions->count() > 0)
        <div class="space-y-4">
            @foreach($this->prescriptions as $prescription)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow cursor-pointer" wire:click="viewPrescription({{ $prescription->id }})">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center space-x-2">
                            <h4 class="text-sm font-semibold text-gray-900">{{ $prescription->reference }}</h4>
                            @if($prescription->is_chronic)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">Chronic</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Dr. {{ $prescription->doctor->last_name }} &middot;
                            {{ $prescription->items->count() }} medication{{ $prescription->items->count() !== 1 ? 's' : '' }} &middot;
                            {{ $prescription->signed_at?->format('j M Y') }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900">{{ $prescription->formatted_total }}</p>
                        @if($prescription->payment_status === 'unpaid')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Payment Required</span>
                        @elseif($prescription->payment_status === 'paid' && $prescription->pharmacy_status === 'dispatched')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Sent to Pharmacy</span>
                        @elseif($prescription->payment_status === 'paid')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Paid</span>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-12 bg-white rounded-xl border border-gray-100">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
            <p class="text-gray-500">No prescriptions yet.</p>
            <p class="text-sm text-gray-400 mt-1">After a consultation, your doctor's prescriptions will appear here.</p>
        </div>
        @endif
    @endif
</div>
