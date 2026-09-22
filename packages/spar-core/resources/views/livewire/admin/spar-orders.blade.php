<div>
    <x-slot name="header">SPAR Orders</x-slot>

    <x-spar::page-header eyebrow="SPAR Group" title="Orders"
        subtitle="Patient-placed collection & delivery orders. Process them through to ready and completed." />

    @if($actionNotice)
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700"
             role="status" wire:key="order-notice">
            {{ $actionNotice }}
        </div>
    @endif

    {{-- Filter tabs --}}
    <div class="flex flex-wrap gap-2 mb-6">
        <button wire:click="setFilter('active')"
                class="px-4 py-2 text-sm rounded-lg transition {{ $filter === 'active' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
            In progress <span class="ml-1 opacity-70">{{ $this->counts['active'] }}</span>
        </button>
        <button wire:click="setFilter('ready')"
                class="px-4 py-2 text-sm rounded-lg transition {{ $filter === 'ready' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
            Ready <span class="ml-1 opacity-70">{{ $this->counts['ready'] }}</span>
        </button>
        <button wire:click="setFilter('completed')"
                class="px-4 py-2 text-sm rounded-lg transition {{ $filter === 'completed' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
            Completed <span class="ml-1 opacity-70">{{ $this->counts['completed'] }}</span>
        </button>
        <button wire:click="setFilter('all')"
                class="px-4 py-2 text-sm rounded-lg transition {{ $filter === 'all' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
            All
        </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="p-4 font-semibold">Reference</th>
                        <th class="p-4 font-semibold">Patient</th>
                        <th class="p-4 font-semibold">Pharmacy</th>
                        <th class="p-4 font-semibold">Fulfilment</th>
                        <th class="p-4 font-semibold">Payment</th>
                        <th class="p-4 font-semibold">Status</th>
                        <th class="p-4 font-semibold">Placed</th>
                        <th class="p-4 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($this->orders as $order)
                        <tr wire:key="order-{{ $order->id }}" class="hover:bg-green-50/30">
                            <td class="p-4 font-mono text-xs text-gray-700">{{ $order->reference }}</td>
                            <td class="p-4">
                                <p class="font-semibold text-gray-900">{{ $order->patient?->display_name ?? '—' }}</p>
                            </td>
                            <td class="p-4 text-gray-600">{{ $order->pharmacy?->name ?? '—' }}</td>
                            <td class="p-4 text-gray-700">{{ $order->modeLabel() }}</td>
                            <td class="p-4">
                                @php
                                    $pay = $order->payment_status;
                                    $payClass = match($pay) {
                                        'paid' => 'bg-green-100 text-green-700',
                                        'pay_at_store' => 'bg-amber-100 text-amber-700',
                                        default => 'bg-gray-100 text-gray-600',
                                    };
                                    $payLabel = match($pay) {
                                        'paid' => 'Paid',
                                        'pay_at_store' => 'Pay at store',
                                        default => 'Unpaid',
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $payClass }}">{{ $payLabel }}</span>
                            </td>
                            <td class="p-4">
                                @php
                                    $statusClass = match($order->status) {
                                        'requested' => 'bg-blue-100 text-blue-700',
                                        'preparing' => 'bg-amber-100 text-amber-700',
                                        'ready' => 'bg-green-100 text-green-700',
                                        'completed' => 'bg-gray-100 text-gray-600',
                                        'cancelled' => 'bg-red-100 text-red-600',
                                        default => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize {{ $statusClass }}">{{ $order->status }}</span>
                            </td>
                            <td class="p-4 text-gray-500">{{ $order->created_at?->format('d M, H:i') }}</td>
                            <td class="p-4">
                                <div class="flex items-center justify-end gap-2">
                                    @if($order->status === 'requested')
                                        <button wire:click="startPreparing({{ $order->id }})"
                                                class="rounded-lg bg-amber-500 hover:bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white">Start preparing</button>
                                    @elseif($order->status === 'preparing')
                                        <button wire:click="markReady({{ $order->id }})"
                                                class="rounded-lg bg-green-600 hover:bg-green-700 px-3 py-1.5 text-xs font-semibold text-white">Mark ready</button>
                                    @elseif($order->status === 'ready')
                                        <button wire:click="completeOrder({{ $order->id }})"
                                                class="rounded-lg bg-gray-800 hover:bg-black px-3 py-1.5 text-xs font-semibold text-white">Complete</button>
                                    @endif
                                    @if(in_array($order->status, ['requested', 'preparing', 'ready'], true))
                                        <button wire:click="cancelOrder({{ $order->id }})"
                                                wire:confirm="Cancel this order?"
                                                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-500 hover:bg-gray-50">Cancel</button>
                                    @endif
                                    @if(in_array($order->status, ['completed', 'cancelled'], true))
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-gray-500">No orders in this view.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
