<div>
    <x-slot name="header">My Appointments</x-slot>

    <!-- Filter tabs -->
    <div class="flex space-x-1 bg-gray-100 rounded-lg p-1 w-fit mb-6">
        <button wire:click="$set('filter', 'upcoming')"
            class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $filter === 'upcoming' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
            Upcoming
        </button>
        <button wire:click="$set('filter', 'past')"
            class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $filter === 'past' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
            Past
        </button>
        <button wire:click="$set('filter', 'cancelled')"
            class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $filter === 'cancelled' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
            Cancelled
        </button>
    </div>

    <!-- Appointments list -->
    @if($this->appointments->count() > 0)
    <div class="space-y-4">
        @foreach($this->appointments as $appointment)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-zapmed-100 rounded-xl flex items-center justify-center">
                        <span class="text-sm font-bold text-zapmed-700">
                            {{ substr($appointment->doctor->first_name, 0, 1) }}{{ substr($appointment->doctor->last_name, 0, 1) }}
                        </span>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Dr. {{ $appointment->doctor->last_name }}</h3>
                        <p class="text-xs text-gray-500">{{ $appointment->type_label }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        bg-{{ $appointment->status_color }}-100 text-{{ $appointment->status_color }}-800">
                        {{ ucfirst($appointment->status) }}
                    </span>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-50 flex items-center justify-between">
                <div class="flex items-center space-x-6 text-sm text-gray-500">
                    <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $appointment->appointment_date->format('j M Y') }}
                    </span>
                    <span class="flex items-center">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ substr($appointment->start_time, 0, 5) }}
                    </span>
                    <span class="font-mono text-xs text-gray-400">{{ $appointment->reference }}</span>
                </div>

                @if($appointment->canBeCancelled())
                <button wire:click="cancelAppointment({{ $appointment->id }})"
                    wire:confirm="Are you sure you want to cancel this appointment?"
                    class="text-xs text-red-600 hover:text-red-800 font-medium">
                    Cancel
                </button>
                @endif

                @if(in_array($appointment->status, ['confirmed', 'in_progress']) && $appointment->activeVideoSession)
                <a href="{{ route('patient.video', $appointment) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg transition-colors shadow-sm">
                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    Join Video Call
                </a>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-12 bg-white rounded-xl border border-gray-100">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <p class="text-gray-500">No {{ $filter }} appointments.</p>
        @if($filter === 'upcoming')
            <a href="{{ route('patient.book') }}" class="mt-3 inline-block text-sm text-zapmed-600 hover:text-zapmed-700 font-medium">Book a consultation →</a>
        @endif
    </div>
    @endif
</div>
