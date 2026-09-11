<div>
    <x-slot name="header">SPAR Chronic Medication</x-slot>

    <!-- Page header -->
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide" style="color: #15803d;">Workspace overview</p>
            <h1 class="mt-1 text-3xl font-bold text-gray-900">Medication dashboard</h1>
            <p class="mt-1 text-gray-500">Manage prescriptions, orders and pharmacy coordination.</p>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right">
                <p class="text-sm font-medium text-gray-700">{{ now()->format('l, j M Y') }}</p>
                <p class="text-xs text-gray-400">Last import {{ $this->stats['last_import'] }}</p>
            </div>
            <a href="{{ route('admin.spar.imports') }}"
               class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
               style="background-color: #15803d;">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 3v13m0-13l-4 4m4-4l4 4"/></svg>
                Import data
            </a>
        </div>
    </div>

    <!-- KPI cards -->
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Active pharmacies</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $this->stats['total_pharmacies'] }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Active patients</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ number_format($this->stats['total_patients']) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $this->stats['consented_patients'] }} consented</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Active journeys</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $this->stats['active_journeys'] }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Pending orders</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900">{{ $this->stats['pending_orders'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Needs attention -->
    @php
        $overdueDispenses = $this->stats['overdue_dispenses'];
        $renewalDue = $this->stats['renewal_due'];
    @endphp
    @if($overdueDispenses > 0 || $renewalDue > 0)
        <div class="mb-6 flex flex-col gap-3 rounded-xl border border-gray-100 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100">
                    <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </span>
                <span class="text-base font-semibold text-gray-900">Needs attention</span>
                @if($overdueDispenses > 0)
                    <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">{{ $overdueDispenses }} overdue {{ Str::plural('dispense', $overdueDispenses) }}</span>
                @endif
                @if($renewalDue > 0)
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">{{ $renewalDue }} {{ Str::plural('renewal', $renewalDue) }} due</span>
                @endif
            </div>
            <a href="{{ route('admin.spar.exceptions') }}" class="inline-flex items-center gap-1 text-sm font-semibold" style="color: #15803d;">
                View exceptions
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        </div>
    @endif

    <!-- Recent orders & Renewals due -->
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Recent orders -->
        <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 p-5">
                <h3 class="text-lg font-semibold text-gray-900">Recent orders</h3>
                <a href="{{ route('spar.patients') }}" class="inline-flex items-center gap-1 text-sm font-semibold" style="color: #15803d;">
                    View all
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($this->recentOrders->take(3) as $order)
                    <div class="flex items-center justify-between p-4">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $order->patient->display_name }}</p>
                            <p class="text-xs text-gray-500">{{ $order->pharmacy->name }} &middot; {{ ucfirst($order->type) }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                @if($order->status === 'completed') bg-green-100 text-green-800
                                @elseif($order->status === 'ready') bg-blue-100 text-blue-800
                                @elseif($order->status === 'preparing') bg-amber-100 text-amber-800
                                @elseif($order->status === 'overdue') bg-red-100 text-red-700
                                @else bg-gray-100 text-gray-600
                                @endif">
                                {{ ucfirst($order->status) }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-gray-500">No orders yet.</div>
                @endforelse
            </div>
        </div>

        <!-- Renewals due -->
        <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 p-5">
                <h3 class="text-lg font-semibold text-gray-900">Renewals due</h3>
                <a href="{{ route('admin.spar.exceptions') }}" class="inline-flex items-center gap-1 text-sm font-semibold" style="color: #15803d;">
                    View all
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($this->renewalsDue->take(3) as $journey)
                    <div class="flex items-center justify-between p-4">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $journey->patient->display_name }}</p>
                            <p class="text-xs text-gray-500">{{ $journey->pharmacy->name }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <p class="text-xs font-medium {{ $journey->renewal_due_date?->isPast() ? 'text-red-600' : 'text-amber-600' }}">
                                    {{ $journey->renewal_due_date?->diffForHumans() }}
                                </p>
                                <p class="text-xs text-gray-400">{{ $journey->dispenses_completed }}/{{ $journey->total_dispenses }} dispenses</p>
                            </div>
                            <a href="{{ route('admin.spar.exceptions') }}"
                               class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold transition hover:bg-green-50"
                               style="border-color: #15803d; color: #15803d;">Review renewal</a>
                        </div>
                    </div>
                @empty
                    <div class="flex items-start gap-2 p-5 text-sm text-gray-500">
                        <svg class="mt-0.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Review prescriptions that need renewal.</span>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Pharmacy summary -->
    <div class="rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 p-5">
            <h3 class="text-lg font-semibold text-gray-900">Pharmacy summary</h3>
            <a href="{{ route('admin.spar.pharmacies') }}"
               class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold transition hover:bg-green-50"
               style="border-color: #15803d; color: #15803d;">Manage pharmacies</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-500">
                        <th class="p-4 font-medium">Pharmacy</th>
                        <th class="p-4 font-medium">Active patients</th>
                        <th class="p-4 font-medium">Pending orders</th>
                        <th class="p-4 font-medium">Status</th>
                        <th class="p-4 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($this->pharmacySummary as $pharmacy)
                        <tr class="hover:bg-gray-50">
                            <td class="p-4">
                                <p class="font-semibold text-gray-900">{{ $pharmacy->name }}</p>
                                <p class="text-xs text-gray-500">{{ $pharmacy->city ?? 'Location TBD' }}</p>
                            </td>
                            <td class="p-4 text-gray-700">{{ $pharmacy->active_patients_count }}</td>
                            <td class="p-4">
                                @if($pharmacy->pending_orders_count > 0)
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-100 text-xs font-medium text-amber-800">{{ $pharmacy->pending_orders_count }}</span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="p-4">
                                @if($pharmacy->is_active)
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Active</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                                @endif
                            </td>
                            <td class="p-4 text-right">
                                <a href="{{ route('admin.spar.pharmacies') }}" class="inline-flex items-center gap-1 text-sm font-semibold" style="color: #15803d;">
                                    View
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-gray-500">
                                No pharmacies registered yet. <a href="{{ route('admin.spar.pharmacies') }}" class="hover:underline" style="color: #15803d;">Add one</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
