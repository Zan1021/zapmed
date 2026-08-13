<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">My Prescriptions</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $this->stats['total'] }} total, {{ $this->stats['this_month'] }} this month, {{ $this->stats['chronic'] }} chronic</p>
    </div>

    <!-- Filter -->
    <div class="flex items-center gap-1 mb-6 bg-gray-100 rounded-lg p-1 w-fit">
        @foreach(['all' => 'All', 'pending' => 'Pending', 'dispatched' => 'Dispatched', 'delivered' => 'Delivered'] as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')"
                class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors {{ $filter === $key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Reference</th>
                    <th class="px-5 py-3 text-left">Patient</th>
                    <th class="px-5 py-3 text-left">Diagnosis</th>
                    <th class="px-5 py-3 text-center">Items</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-right">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($this->prescriptions as $rx)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-4">
                            <p class="font-mono text-xs font-medium text-gray-900">{{ $rx->reference }}</p>
                            @if($rx->is_chronic)
                                <span class="text-xs text-purple-600 font-medium">Chronic ({{ $rx->repeats }} repeats)</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-sm text-gray-700">{{ $rx->patient->name }}</td>
                        <td class="px-5 py-4 text-sm text-gray-600 max-w-48 truncate">{{ $rx->diagnosis }}</td>
                        <td class="px-5 py-4 text-center text-sm">{{ $rx->items->count() }}</td>
                        <td class="px-5 py-4 text-center">
                            @php
                                $colors = ['pending' => 'bg-yellow-100 text-yellow-800', 'dispatched' => 'bg-blue-100 text-blue-800', 'delivered' => 'bg-green-100 text-green-800', 'failed' => 'bg-red-100 text-red-800'];
                            @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $colors[$rx->pharmacy_status] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ ucfirst($rx->pharmacy_status) }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right text-xs text-gray-400">{{ $rx->signed_at?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-400">No prescriptions written yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->prescriptions->links() }}</div>
</div>
