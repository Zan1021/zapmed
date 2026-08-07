<div>
    <x-slot name="header">Payments & Revenue</x-slot>

    <!-- Revenue Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Total Revenue</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">R{{ number_format($this->revenueStats['total'] / 100, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">This Month</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">R{{ number_format($this->revenueStats['this_month'] / 100, 2) }}</p>
            @if($this->revenueStats['last_month'] > 0)
                @php
                    $change = (($this->revenueStats['this_month'] - $this->revenueStats['last_month']) / $this->revenueStats['last_month']) * 100;
                @endphp
                <p class="text-xs {{ $change >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">
                    {{ $change >= 0 ? '+' : '' }}{{ number_format($change, 1) }}% vs last month
                </p>
            @endif
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Today</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">R{{ number_format($this->revenueStats['today'] / 100, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Pending</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $this->revenueStats['pending_count'] }}</p>
            <p class="text-xs text-gray-500 mt-1">R{{ number_format($this->revenueStats['pending_amount'] / 100, 2) }} value</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by reference or patient..."
                class="rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
            <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="completed">Completed</option>
                <option value="failed">Failed</option>
                <option value="refunded">Refunded</option>
            </select>
            <input wire:model.live="dateFrom" type="date" class="rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="From">
            <input wire:model.live="dateTo" type="date" class="rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="To">
        </div>
    </div>

    <!-- Payments Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700" wire:click="sortBy('amount')">
                            <div class="flex items-center gap-1">
                                Amount
                                @if($sortBy === 'amount')
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="{{ $sortDirection === 'asc' ? 'M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z' : 'M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 15.586l3.293-3.293a1 1 0 011.414 0z' }}"/></svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700" wire:click="sortBy('created_at')">
                            <div class="flex items-center gap-1">
                                Date
                                @if($sortBy === 'created_at')
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="{{ $sortDirection === 'asc' ? 'M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z' : 'M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 15.586l3.293-3.293a1 1 0 011.414 0z' }}"/></svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Appointment</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($payments as $payment)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-mono text-gray-700">{{ $payment->reference }}</span>
                            @if($payment->provider_reference)
                                <p class="text-xs text-gray-400">{{ $payment->provider_reference }}</p>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <p class="text-sm font-medium text-gray-900">{{ $payment->patient->name ?? 'Unknown' }}</p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-semibold text-gray-900">{{ $payment->formatted_amount }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $statusColors = [
                                    'completed' => 'green',
                                    'pending' => 'amber',
                                    'failed' => 'red',
                                    'refunded' => 'purple',
                                ];
                                $color = $statusColors[$payment->status] ?? 'gray';
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
                                {{ ucfirst($payment->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ ucfirst($payment->payment_method ?? 'N/A') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $payment->paid_at?->format('j M Y H:i') ?? $payment->created_at->format('j M Y H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($payment->appointment)
                                <span class="text-xs font-mono text-gray-500">{{ $payment->appointment->reference }}</span>
                            @else
                                <span class="text-xs text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-400">
                            No payments found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($payments->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $payments->links() }}
        </div>
        @endif
    </div>
</div>
