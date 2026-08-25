<div>
    <x-slot name="header">SPAR Chronic Medication</x-slot>

    <!-- Header Banner -->
    <div class="bg-gradient-to-r from-green-700 to-green-900 rounded-xl p-6 mb-8 text-white" style="background: linear-gradient(to right, #15803d, #14532d);">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-white">SPAR Medication Management</h2>
                <p class="text-green-200 mt-1" style="color: #bbf7d0;">Chronic prescription journeys, reminders & pharmacy coordination.</p>
            </div>
            <div class="text-right hidden sm:block">
                <p class="text-sm text-green-200" style="color: #bbf7d0;">{{ now()->format('l, j M Y') }}</p>
                <p class="text-xs text-green-300 mt-1" style="color: #86efac;">Last import: {{ $this->stats['last_import'] }}</p>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Active Pharmacies</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->stats['total_pharmacies'] }}</p>
                </div>
                <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Active Patients</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($this->stats['total_patients']) }}</p>
                </div>
                <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">{{ $this->stats['consented_patients'] }} consented</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Active Journeys</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->stats['active_journeys'] }}</p>
                </div>
                <div class="w-10 h-10 bg-purple-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
            </div>
            <p class="text-xs text-amber-600 mt-2">{{ $this->stats['renewal_due'] }} renewals due</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pending Orders</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->stats['pending_orders'] }}</p>
                </div>
                <div class="w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
            </div>
            @if($this->stats['overdue_dispenses'] > 0)
                <p class="text-xs text-red-600 mt-2">{{ $this->stats['overdue_dispenses'] }} overdue dispenses</p>
            @endif
        </div>
    </div>

    <!-- Quick Links -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <a href="{{ route('admin.spar.pharmacies') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:border-green-300 transition flex items-center gap-3">
            <div class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
            </div>
            <span class="text-sm font-medium text-gray-700">Manage Pharmacies</span>
        </a>
        <a href="{{ route('admin.spar.imports') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:border-green-300 transition flex items-center gap-3">
            <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            </div>
            <span class="text-sm font-medium text-gray-700">Import Data</span>
        </a>
        <a href="{{ route('admin.spar.exceptions') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:border-green-300 transition flex items-center gap-3">
            <div class="w-8 h-8 bg-red-50 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <span class="text-sm font-medium text-gray-700">Exceptions</span>
        </a>
        <a href="{{ route('admin.spar.reporting') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:border-green-300 transition flex items-center gap-3">
            <div class="w-8 h-8 bg-purple-50 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <span class="text-sm font-medium text-gray-700">Reporting</span>
        </a>
    </div>

    <!-- Pharmacy Summary Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-8">
        <div class="p-5 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">Pharmacy Summary</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left p-3 font-medium text-gray-600">Pharmacy</th>
                        <th class="text-center p-3 font-medium text-gray-600">Active Patients</th>
                        <th class="text-center p-3 font-medium text-gray-600">Pending Orders</th>
                        <th class="text-center p-3 font-medium text-gray-600">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($this->pharmacySummary as $pharmacy)
                        <tr class="hover:bg-gray-50">
                            <td class="p-3">
                                <p class="font-medium text-gray-900">{{ $pharmacy->name }}</p>
                                <p class="text-xs text-gray-500">{{ $pharmacy->city ?? 'Location TBD' }}</p>
                            </td>
                            <td class="p-3 text-center">{{ $pharmacy->active_patients_count }}</td>
                            <td class="p-3 text-center">
                                @if($pharmacy->pending_orders_count > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">{{ $pharmacy->pending_orders_count }}</span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="p-3 text-center">
                                @if($pharmacy->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-8 text-center text-gray-500">
                                No pharmacies registered yet. <a href="{{ route('admin.spar.pharmacies') }}" class="text-green-600 hover:underline">Add one</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Orders & Renewals Due -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Orders -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Recent Orders</h3>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($this->recentOrders as $order)
                    <div class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $order->patient->display_name }}</p>
                            <p class="text-xs text-gray-500">{{ $order->pharmacy->name }} &middot; {{ ucfirst($order->type) }}</p>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                            @if($order->status === 'completed') bg-green-100 text-green-800
                            @elseif($order->status === 'ready') bg-blue-100 text-blue-800
                            @elseif($order->status === 'preparing') bg-amber-100 text-amber-800
                            @else bg-gray-100 text-gray-600
                            @endif">
                            {{ ucfirst($order->status) }}
                        </span>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-500 text-sm">No orders yet.</div>
                @endforelse
            </div>
        </div>

        <!-- Renewals Due -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-5 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Renewals Due</h3>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($this->renewalsDue as $journey)
                    <div class="p-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $journey->patient->display_name }}</p>
                            <p class="text-xs text-gray-500">{{ $journey->pharmacy->name }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-red-600">Due {{ $journey->renewal_due_date?->diffForHumans() }}</p>
                            <p class="text-xs text-gray-400">{{ $journey->dispenses_completed }}/{{ $journey->total_dispenses }} dispenses</p>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-gray-500 text-sm">No renewals due.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
