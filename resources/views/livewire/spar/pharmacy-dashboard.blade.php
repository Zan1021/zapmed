<div>
    <x-slot name="header">Pharmacy Dashboard</x-slot>

    @if(!$this->pharmacy)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-6 text-center">
            <p class="text-amber-800">Your account is not linked to a SPAR pharmacy. Please contact your administrator.</p>
        </div>
    @else
        <!-- Header -->
        <div class="bg-white rounded-xl p-6 mb-8 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">{{ $this->pharmacy->name }}</h2>
                    <p class="text-gray-500 mt-1">{{ $this->pharmacy->city ?? '' }} {{ $this->pharmacy->province ?? '' }}</p>
                </div>
                <div class="text-right hidden sm:block">
                    <p class="text-sm text-gray-500">{{ now()->format('l, j M Y') }}</p>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm text-gray-500">Active Patients</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->stats['active_patients'] ?? 0 }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm text-gray-500">Pending Orders</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">{{ $this->stats['pending_orders'] ?? 0 }}</p>
                @if(($this->stats['ready_orders'] ?? 0) > 0)
                    <p class="text-xs text-green-600 mt-1">{{ $this->stats['ready_orders'] }} ready for collection</p>
                @endif
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm text-gray-500">Renewals Due</p>
                <p class="text-2xl font-bold text-purple-600 mt-1">{{ $this->stats['renewals_due'] ?? 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $this->stats['upcoming_dispenses'] ?? 0 }} dispenses this week</p>
            </div>
        </div>

        <!-- Orders Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Pending Orders -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">Pending Orders</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($this->pendingOrders as $order)
                        <div class="p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $order->patient->display_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $order->reference }} &middot; {{ ucfirst($order->type) }}</p>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $order->status === 'preparing' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($order->status) }}
                                </span>
                            </div>
                            <div class="flex gap-2">
                                @if($order->status === 'requested')
                                    <button wire:click="startPreparing({{ $order->id }})" class="px-3 py-1 text-xs bg-amber-50 text-amber-700 rounded hover:bg-amber-100 transition">Start Preparing</button>
                                @elseif($order->status === 'preparing')
                                    <button wire:click="markReady({{ $order->id }})" class="px-3 py-1 text-xs bg-green-50 text-green-700 rounded hover:bg-green-100 transition">Mark Ready</button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-gray-500 text-sm">No pending orders.</div>
                    @endforelse
                </div>
            </div>

            <!-- Ready for Collection -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-5 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">Ready for Collection/Delivery</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($this->readyOrders as $order)
                        <div class="p-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $order->patient->display_name }}</p>
                                <p class="text-xs text-gray-500">{{ $order->reference }} &middot; Ready {{ $order->ready_at?->diffForHumans() }}</p>
                            </div>
                            <button wire:click="markCompleted({{ $order->id }})" class="px-3 py-1.5 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                {{ $order->isCollection() ? 'Collected' : 'Delivered' }}
                            </button>
                        </div>
                    @empty
                        <div class="p-8 text-center text-gray-500 text-sm">No orders ready.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Upcoming Dispenses -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Upcoming Dispenses (Next 14 Days)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 font-medium text-gray-600">Patient</th>
                            <th class="text-left p-3 font-medium text-gray-600">Due Date</th>
                            <th class="text-left p-3 font-medium text-gray-600">Dispense #</th>
                            <th class="text-left p-3 font-medium text-gray-600">Medications</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($this->upcomingDispenses as $dispense)
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-900">{{ $dispense->patient->display_name }}</td>
                                <td class="p-3">
                                    <span class="{{ $dispense->due_date->isPast() ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                                        {{ $dispense->due_date->format('d M Y') }}
                                    </span>
                                </td>
                                <td class="p-3 text-gray-600">{{ $dispense->dispense_number }}/{{ $dispense->journey->total_dispenses }}</td>
                                <td class="p-3 text-gray-500 text-xs">
                                    {{ collect($dispense->journey->medications ?? [])->pluck('name')->take(3)->implode(', ') }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-6 text-center text-gray-500">No upcoming dispenses.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
