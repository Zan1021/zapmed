<div>
    <x-slot name="header">Doctor Dashboard</x-slot>

    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-zapmed-600 to-zapmed-700 rounded-xl p-6 mb-8 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, Dr. {{ auth()->user()->last_name }}</h2>
                <p class="text-zapmed-100 mt-1">
                    You have <span class="font-semibold text-white">{{ $this->stats['today_remaining'] }} appointment{{ $this->stats['today_remaining'] !== 1 ? 's' : '' }}</span> remaining today.
                </p>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Today's Queue</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->stats['today_total'] }}</p>
            <p class="text-xs text-green-600 mt-1">{{ $this->stats['today_completed'] }} completed</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">This Week</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->stats['week_total'] }}</p>
            <p class="text-xs text-gray-500 mt-1">consultations</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Total Patients</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->stats['total_patients'] }}</p>
            <p class="text-xs text-gray-500 mt-1">unique patients</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <p class="text-sm text-gray-500">Pending Actions</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $this->stats['pending_actions'] }}</p>
            <p class="text-xs text-amber-600 mt-1">{{ $this->stats['pending_actions'] > 0 ? 'Needs attention' : 'All clear' }}</p>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Today's Schedule -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Today's Schedule</h3>
                    <span class="text-sm text-gray-500">{{ now()->format('l, j M Y') }}</span>
                </div>
            </div>

            @if($this->todayAppointments->count() > 0)
            <div class="divide-y divide-gray-50">
                @foreach($this->todayAppointments as $appointment)
                <div class="p-4 {{ $appointment->status === 'in_progress' ? 'bg-zapmed-50 border-l-4 border-zapmed-500' : ($appointment->status === 'completed' ? 'opacity-60' : 'hover:bg-gray-50') }} transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="flex flex-col items-center min-w-[50px]">
                                <span class="text-xs text-gray-500">{{ substr($appointment->start_time, 0, 5) }}</span>
                                <span class="text-xs text-gray-400">{{ $appointment->duration_minutes }}min</span>
                            </div>
                            <div class="w-10 h-10 bg-{{ $appointment->status_color }}-100 rounded-full flex items-center justify-center">
                                <span class="text-sm font-medium text-{{ $appointment->status_color }}-700">
                                    {{ substr($appointment->patient->first_name, 0, 1) }}{{ substr($appointment->patient->last_name, 0, 1) }}
                                </span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900 {{ $appointment->status === 'completed' ? 'line-through' : '' }}">
                                    {{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}
                                </p>
                                <p class="text-xs text-gray-500">{{ $appointment->type_label }}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            @if($appointment->status === 'pending')
                                <button wire:click="confirmAppointment({{ $appointment->id }})"
                                    class="text-xs bg-blue-50 text-blue-700 hover:bg-blue-100 px-3 py-1.5 rounded-lg font-medium transition-colors">
                                    Confirm
                                </button>
                            @elseif($appointment->status === 'confirmed')
                                <button wire:click="startConsultation({{ $appointment->id }})"
                                    class="text-xs bg-zapmed-600 text-white hover:bg-zapmed-700 px-3 py-1.5 rounded-lg font-medium transition-colors">
                                    Start
                                </button>
                            @elseif($appointment->status === 'in_progress')
                                <button wire:click="completeConsultation({{ $appointment->id }})"
                                    class="text-xs bg-green-600 text-white hover:bg-green-700 px-3 py-1.5 rounded-lg font-medium transition-colors">
                                    Complete
                                </button>
                            @elseif($appointment->status === 'completed')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Done</span>
                            @elseif($appointment->status === 'cancelled')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Cancelled</span>
                            @endif

                            @if($appointment->canBeCancelled())
                                <button wire:click="cancelAppointment({{ $appointment->id }})"
                                    wire:confirm="Cancel this appointment?"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium">
                                    Cancel
                                </button>
                            @endif
                        </div>
                    </div>
                    @if($appointment->reason)
                        <p class="text-xs text-gray-400 mt-2 ml-[74px]">Reason: {{ $appointment->reason }}</p>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <div class="p-8 text-center">
                <svg class="w-12 h-12 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="text-sm text-gray-500">No appointments scheduled for today.</p>
            </div>
            @endif
        </div>

        <!-- Upcoming Appointments -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Upcoming</h3>
            </div>

            @if($this->upcomingAppointments->count() > 0)
            <div class="divide-y divide-gray-50">
                @foreach($this->upcomingAppointments as $appointment)
                <div class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}</p>
                            <p class="text-xs text-gray-500">{{ $appointment->type_label }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-medium text-gray-700">{{ $appointment->appointment_date->format('j M') }}</p>
                            <p class="text-xs text-gray-400">{{ substr($appointment->start_time, 0, 5) }}</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="p-6 text-center">
                <p class="text-sm text-gray-400">No upcoming appointments.</p>
            </div>
            @endif
        </div>
    </div>
</div>
