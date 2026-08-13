<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Pharmacy Orders</h1>
        <p class="text-sm text-gray-500 mt-1">Track prescription dispatch and delivery status.</p>
    </div>

    @if(session('message'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('message') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
        <div class="bg-yellow-50 border border-yellow-100 rounded-lg p-3 text-center">
            <p class="text-lg font-bold text-yellow-800">{{ $this->stats['pending'] }}</p>
            <p class="text-xs text-yellow-600">Pending</p>
        </div>
        <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 text-center">
            <p class="text-lg font-bold text-blue-800">{{ $this->stats['dispatched'] }}</p>
            <p class="text-xs text-blue-600">Dispatched</p>
        </div>
        <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-3 text-center">
            <p class="text-lg font-bold text-indigo-800">{{ $this->stats['in_transit'] }}</p>
            <p class="text-xs text-indigo-600">In Transit</p>
        </div>
        <div class="bg-green-50 border border-green-100 rounded-lg p-3 text-center">
            <p class="text-lg font-bold text-green-800">{{ $this->stats['delivered'] }}</p>
            <p class="text-xs text-green-600">Delivered</p>
        </div>
        <div class="bg-red-50 border border-red-100 rounded-lg p-3 text-center">
            <p class="text-lg font-bold text-red-800">{{ $this->stats['failed'] }}</p>
            <p class="text-xs text-red-600">Failed</p>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="flex items-center gap-1 mb-6 bg-gray-100 rounded-lg p-1 w-fit">
        @foreach(['all' => 'All', 'pending' => 'Pending', 'dispatched' => 'Dispatched', 'in_transit' => 'In Transit', 'delivered' => 'Delivered', 'failed' => 'Failed'] as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')"
                class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors {{ $filter === $key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Reference</th>
                    <th class="px-4 py-3 text-left">Patient</th>
                    <th class="px-4 py-3 text-left">Doctor</th>
                    <th class="px-4 py-3 text-center">Items</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Payment</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($this->orders as $order)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-mono text-xs font-medium text-gray-900">{{ $order->reference }}</p>
                            <p class="text-xs text-gray-400">{{ $order->signed_at?->format('d M Y H:i') }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-sm text-gray-900">{{ $order->patient->name }}</p>
                            <p class="text-xs text-gray-400">{{ $order->delivery_city }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">Dr. {{ $order->doctor->last_name }}</td>
                        <td class="px-4 py-3 text-center text-sm">{{ $order->items_count ?? $order->items()->count() }}</td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'dispatched' => 'bg-blue-100 text-blue-800',
                                    'in_transit' => 'bg-indigo-100 text-indigo-800',
                                    'delivered' => 'bg-green-100 text-green-800',
                                    'failed' => 'bg-red-100 text-red-800',
                                    'out_of_stock' => 'bg-orange-100 text-orange-800',
                                    'returned' => 'bg-gray-100 text-gray-800',
                                ];
                            @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$order->pharmacy_status] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ ucfirst(str_replace('_', ' ', $order->pharmacy_status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs {{ $order->payment_status === 'paid' ? 'text-green-600' : 'text-gray-400' }}">
                                {{ ucfirst($order->payment_status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($order->pharmacy_status === 'failed')
                                    <button wire:click="redispatch({{ $order->id }})" class="text-xs bg-blue-50 text-blue-700 hover:bg-blue-100 px-2 py-1 rounded font-medium">
                                        Retry
                                    </button>
                                @endif
                                @if(in_array($order->pharmacy_status, ['dispatched', 'in_transit']))
                                    <button wire:click="markDelivered({{ $order->id }})" wire:confirm="Mark as delivered?" class="text-xs bg-green-50 text-green-700 hover:bg-green-100 px-2 py-1 rounded font-medium">
                                        Delivered
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">No orders found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->orders->links() }}
    </div>
</div>
