<div>
    <x-slot name="header">Administration</x-slot>

    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-slate-800 to-slate-900 rounded-xl p-6 mb-8 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold">Admin Dashboard</h2>
                <p class="text-slate-300 mt-1">Platform overview and management.</p>
            </div>
            <div class="text-right hidden sm:block">
                <p class="text-sm text-slate-400">{{ now()->format('l, j M Y') }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ now()->format('H:i') }}</p>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Total Users -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Users</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($this->stats['total_users']) }}</p>
                </div>
                <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-2">
                <span class="text-xs text-gray-500">{{ $this->stats['total_patients'] }} patients</span>
                <span class="text-xs text-gray-400">|</span>
                <span class="text-xs text-gray-500">{{ $this->stats['total_doctors'] }} doctors</span>
            </div>
        </div>

        <!-- Appointments Today -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Today's Appointments</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->stats['appointments_today'] }}</p>
                </div>
                <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-2">
                <span class="text-xs text-gray-500">{{ $this->stats['appointments_this_week'] }} this week</span>
                <span class="text-xs text-amber-600">{{ $this->stats['pending_appointments'] }} pending</span>
            </div>
        </div>

        <!-- Revenue This Month -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Revenue (This Month)</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">R{{ number_format($this->stats['revenue_this_month'] / 100, 2) }}</p>
                </div>
                <div class="w-10 h-10 bg-zapmed-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-zapmed-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">R{{ number_format($this->stats['revenue_today'] / 100, 2) }} today</p>
        </div>

        <!-- Total Revenue -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Revenue</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">R{{ number_format($this->stats['total_revenue'] / 100, 2) }}</p>
                </div>
                <div class="w-10 h-10 bg-purple-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
            </div>
            <p class="text-xs text-amber-600 mt-2">{{ $this->stats['pending_payments'] }} pending</p>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Appointments -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Recent Appointments</h3>
                <a href="{{ route('admin.appointments') }}" class="text-sm text-zapmed-700 hover:text-zapmed-800 font-medium">View all &rarr;</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($this->recentAppointments as $appointment)
                <div class="p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-9 h-9 bg-gray-100 rounded-full flex items-center justify-center">
                                <span class="text-xs font-medium text-gray-600">
                                    {{ substr($appointment->patient->first_name ?? '?', 0, 1) }}{{ substr($appointment->patient->last_name ?? '?', 0, 1) }}
                                </span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $appointment->patient->name ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-500">{{ $appointment->reference }} &middot; {{ $appointment->type_label }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $appointment->status_color }}-100 text-{{ $appointment->status_color }}-700">
                                {{ ucfirst($appointment->status) }}
                            </span>
                            <p class="text-xs text-gray-400 mt-1">{{ $appointment->appointment_date->format('j M') }}</p>
                        </div>
                    </div>
                </div>
                @empty
                <div class="p-8 text-center">
                    <p class="text-sm text-gray-400">No appointments yet.</p>
                </div>
                @endforelse
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            <!-- Recent Payments -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Recent Payments</h3>
                    <a href="{{ route('admin.payments') }}" class="text-xs text-zapmed-700 hover:text-zapmed-800 font-medium">View all</a>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($this->recentPayments as $payment)
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $payment->patient->name ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-500">{{ $payment->reference }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-gray-900">{{ $payment->formatted_amount }}</p>
                                <span class="text-xs {{ $payment->status === 'completed' ? 'text-green-600' : 'text-amber-600' }}">{{ ucfirst($payment->status) }}</span>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="p-6 text-center">
                        <p class="text-sm text-gray-400">No payments yet.</p>
                    </div>
                    @endforelse
                </div>
            </div>

            <!-- New Users -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">New Users</h3>
                    <a href="{{ route('admin.users') }}" class="text-xs text-zapmed-700 hover:text-zapmed-800 font-medium">Manage</a>
                </div>
                <div class="divide-y divide-gray-50">
                    @forelse($this->recentUsers as $user)
                    <div class="p-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-zapmed-100 rounded-full flex items-center justify-center">
                                <span class="text-xs font-medium text-zapmed-700">{{ substr($user->first_name, 0, 1) }}{{ substr($user->last_name, 0, 1) }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $user->name }}</p>
                                <p class="text-xs text-gray-500">{{ $user->role->label() }} &middot; {{ $user->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="p-6 text-center">
                        <p class="text-sm text-gray-400">No users yet.</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
