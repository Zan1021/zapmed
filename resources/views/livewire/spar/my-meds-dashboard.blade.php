<div>
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-xl p-3 mb-4">
            <p class="text-sm text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    @if(!$this->sparPatient)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
            <p class="text-amber-800">No active prescription found for your account.</p>
            <p class="text-sm text-amber-600 mt-2">Contact your SPAR pharmacy for assistance.</p>
        </div>
    @else
        <!-- Greeting -->
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900">Hi, {{ auth()->user()->first_name }}!</h2>
            <p class="text-sm text-gray-500">Here's your medication status.</p>
        </div>

        <!-- Active Order Status -->
        @if($this->pendingOrder)
            <div class="bg-white rounded-2xl shadow-sm border border-green-200 p-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center
                        {{ $this->pendingOrder->status === 'ready' ? 'bg-green-100' : 'bg-amber-100' }}">
                        @if($this->pendingOrder->status === 'ready')
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        @else
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @endif
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900">
                            @if($this->pendingOrder->status === 'ready')
                                Ready for {{ $this->pendingOrder->isCollection() ? 'collection' : 'delivery' }}!
                            @elseif($this->pendingOrder->status === 'preparing')
                                Being prepared...
                            @else
                                Order received
                            @endif
                        </p>
                        <p class="text-xs text-gray-500">{{ $this->pendingOrder->reference }} &middot; {{ ucfirst($this->pendingOrder->type) }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Journey Timeline -->
        @if($this->activeJourney)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900">Prescription Journey</h3>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full
                        {{ $this->activeJourney->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $this->activeJourney->status === 'renewal_due' ? 'Renewal Due' : 'Active' }}
                    </span>
                </div>

                <!-- Visual Timeline -->
                <div class="flex items-center justify-between mb-4">
                    @for($i = 1; $i <= $this->activeJourney->total_dispenses; $i++)
                        @php
                            $record = $this->dispenseRecords->firstWhere('dispense_number', $i);
                            $isComplete = $record && in_array($record->status, ['collected', 'delivered']);
                            $isCurrent = $record && in_array($record->status, ['upcoming', 'reminded']);
                            $isMissed = $record && $record->status === 'missed';
                        @endphp
                        <div class="flex flex-col items-center flex-1">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                                @if($isComplete) bg-green-500 text-white
                                @elseif($isCurrent) bg-green-100 text-green-700 ring-2 ring-green-400 ring-offset-2
                                @elseif($isMissed) bg-red-100 text-red-600
                                @else bg-gray-100 text-gray-400
                                @endif">
                                @if($isComplete)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                @else
                                    {{ $i }}
                                @endif
                            </div>
                            <span class="text-[10px] text-gray-400 mt-1">Month {{ $i }}</span>
                        </div>
                        @if($i < $this->activeJourney->total_dispenses)
                            <div class="flex-1 h-0.5 mx-1 {{ $isComplete ? 'bg-green-400' : 'bg-gray-200' }}"></div>
                        @endif
                    @endfor
                </div>

                <div class="text-center">
                    <p class="text-sm text-gray-600">
                        <span class="font-bold text-green-600">{{ $this->activeJourney->dispenses_completed }}</span> of {{ $this->activeJourney->total_dispenses }} dispenses completed
                    </p>
                </div>
            </div>
        @endif

        <!-- Next Dispense / Action Card -->
        @if($this->nextDispense && !$this->pendingOrder)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">Next medication due</p>
                        <p class="text-sm {{ $this->nextDispense->due_date->isPast() ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                            {{ $this->nextDispense->due_date->format('d F Y') }}
                            ({{ $this->nextDispense->due_date->diffForHumans() }})
                        </p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="grid grid-cols-2 gap-3">
                    <button wire:click="requestCollection" wire:loading.attr="disabled"
                        class="flex flex-col items-center gap-2 p-4 bg-green-50 border border-green-200 rounded-xl hover:bg-green-100 transition">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                        <span class="text-sm font-medium text-green-700">Collect</span>
                        <span class="text-[10px] text-green-600">From pharmacy</span>
                    </button>

                    @if($this->sparPatient->pharmacy->supports_delivery)
                        <button wire:click="showDelivery"
                            class="flex flex-col items-center gap-2 p-4 bg-blue-50 border border-blue-200 rounded-xl hover:bg-blue-100 transition">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                            <span class="text-sm font-medium text-blue-700">Deliver</span>
                            <span class="text-[10px] text-blue-600">To my address</span>
                        </button>
                    @else
                        <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 border border-gray-200 rounded-xl opacity-50">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                            <span class="text-sm font-medium text-gray-400">Deliver</span>
                            <span class="text-[10px] text-gray-400">Not available</span>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Delivery Form Modal -->
        @if($showDeliveryForm)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4">
                <h3 class="font-semibold text-gray-900 mb-3">Delivery Address</h3>
                <form wire:submit="requestDelivery" class="space-y-3">
                    <input type="text" wire:model="deliveryAddress" placeholder="Street address" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-green-500 focus:border-green-500" />
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" wire:model="deliveryCity" placeholder="City" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-green-500 focus:border-green-500" />
                        <input type="text" wire:model="deliveryPostalCode" placeholder="Postal code" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-green-500 focus:border-green-500" />
                    </div>
                    <input type="tel" wire:model="deliveryPhone" placeholder="Phone number" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-green-500 focus:border-green-500" />

                    <div class="flex gap-3 pt-2">
                        <button type="button" wire:click="$set('showDeliveryForm', false)" class="flex-1 py-2.5 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="flex-1 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">Request Delivery</button>
                    </div>
                </form>
            </div>
        @endif

        <!-- Medications -->
        @if($this->activeJourney && $this->activeJourney->medications)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4">
                <h3 class="font-semibold text-gray-900 mb-3">Your Medications</h3>
                <div class="space-y-3">
                    @foreach($this->activeJourney->medications as $med)
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $med['name'] ?? 'Unknown' }}</p>
                                @if(!empty($med['nappi_code']))
                                    <p class="text-xs text-gray-400">NAPPI: {{ $med['nappi_code'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Pharmacy Info -->
        @if($this->sparPatient->pharmacy)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4">
                <h3 class="font-semibold text-gray-900 mb-3">Your Pharmacy</h3>
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">{{ $this->sparPatient->pharmacy->name }}</p>
                        @if($this->sparPatient->pharmacy->address)
                            <p class="text-sm text-gray-500">{{ $this->sparPatient->pharmacy->address }}</p>
                        @endif
                        <p class="text-sm text-gray-500">{{ $this->sparPatient->pharmacy->city }}, {{ $this->sparPatient->pharmacy->province }}</p>
                        @if($this->sparPatient->pharmacy->phone)
                            <a href="tel:{{ $this->sparPatient->pharmacy->phone }}" class="text-sm text-green-600 font-medium mt-1 inline-block">
                                {{ $this->sparPatient->pharmacy->phone }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Quick Links -->
        <div class="grid grid-cols-2 gap-3">
            <a href="{{ route('my-meds.history') }}" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-center hover:border-green-200 transition">
                <svg class="w-6 h-6 text-gray-400 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-xs font-medium text-gray-600">History</p>
            </a>
            <a href="{{ route('patient.book') }}" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-center hover:border-green-200 transition">
                <svg class="w-6 h-6 text-gray-400 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <p class="text-xs font-medium text-gray-600">See a Doctor</p>
            </a>
        </div>
    @endif
</div>
