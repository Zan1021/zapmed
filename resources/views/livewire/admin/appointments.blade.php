<div>
    <x-slot name="header">Appointments</x-slot>

    <!-- Flash Messages -->
    @if(session('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-sm text-green-700">{{ session('message') }}</p>
        </div>
    @endif

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by reference or patient name..."
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
            </div>
            <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="no_show">No Show</option>
            </select>
            <select wire:model.live="doctorFilter" class="rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500">
                <option value="">All Doctors</option>
                @foreach($this->doctors as $doctor)
                    <option value="{{ $doctor->id }}">Dr. {{ $doctor->last_name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <input wire:model.live="dateFrom" type="date" class="w-full rounded-lg border-gray-300 text-sm focus:border-zapmed-500 focus:ring-zapmed-500" placeholder="From">
            </div>
        </div>
    </div>

    <!-- Appointments Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Doctor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700" wire:click="sortBy('appointment_date')">
                            <div class="flex items-center gap-1">
                                Date/Time
                                @if($sortBy === 'appointment_date')
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="{{ $sortDirection === 'asc' ? 'M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 4.414l-3.293 3.293a1 1 0 01-1.414 0z' : 'M14.707 12.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 15.586l3.293-3.293a1 1 0 011.414 0z' }}"/></svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($appointments as $appointment)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-mono text-gray-700">{{ $appointment->reference }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $appointment->patient->name ?? 'Unknown' }}</p>
                                <p class="text-xs text-gray-500">{{ $appointment->patient->email ?? '' }}</p>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <p class="text-sm text-gray-700">Dr. {{ $appointment->doctor->last_name ?? 'Unassigned' }}</p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <p class="text-sm text-gray-900">{{ $appointment->appointment_date->format('j M Y') }}</p>
                            <p class="text-xs text-gray-500">{{ substr($appointment->start_time, 0, 5) }} ({{ $appointment->duration_minutes }}min)</p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-600">{{ $appointment->type_label }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $appointment->status_color }}-100 text-{{ $appointment->status_color }}-700">
                                {{ ucfirst(str_replace('_', ' ', $appointment->status)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-700">{{ $appointment->formatted_fee }}</span>
                            @if($appointment->is_paid)
                                <span class="ml-1 text-xs text-green-600">Paid</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            @if($appointment->canBeCancelled())
                                <button wire:click="cancelAppointment({{ $appointment->id }})"
                                    wire:confirm="Cancel this appointment?"
                                    class="text-xs font-medium text-red-600 hover:text-red-800 transition-colors">
                                    Cancel
                                </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-400">
                            No appointments found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($appointments->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $appointments->links() }}
        </div>
        @endif
    </div>
</div>
