<div>
    <x-slot name="header">SPAR Exceptions</x-slot>

    <!-- Filter Tabs -->
    <div class="flex gap-2 mb-6">
        <button wire:click="$set('filter', 'all')" class="px-4 py-2 text-sm rounded-lg transition {{ $filter === 'all' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">All</button>
        <button wire:click="$set('filter', 'overdue')" class="px-4 py-2 text-sm rounded-lg transition {{ $filter === 'overdue' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">Overdue Dispenses</button>
        <button wire:click="$set('filter', 'renewals')" class="px-4 py-2 text-sm rounded-lg transition {{ $filter === 'renewals' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">Missing Renewals</button>
        <button wire:click="$set('filter', 'unresponsive')" class="px-4 py-2 text-sm rounded-lg transition {{ $filter === 'unresponsive' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">Unresponsive</button>
    </div>

    <!-- Overdue Dispenses -->
    @if($filter === 'all' || $filter === 'overdue')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Overdue Dispenses</h3>
                <span class="text-sm text-red-600">{{ $this->overdueDispenses->count() }} patients</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 font-medium text-gray-600">Patient</th>
                            <th class="text-left p-3 font-medium text-gray-600">Pharmacy</th>
                            <th class="text-left p-3 font-medium text-gray-600">Due Date</th>
                            <th class="text-left p-3 font-medium text-gray-600">Days Overdue</th>
                            <th class="text-left p-3 font-medium text-gray-600">Reminded</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($this->overdueDispenses as $dispense)
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-900">{{ $dispense->patient->display_name }}</td>
                                <td class="p-3 text-gray-600">{{ $dispense->journey->pharmacy->name ?? '-' }}</td>
                                <td class="p-3 text-gray-600">{{ $dispense->due_date->format('d M Y') }}</td>
                                <td class="p-3">
                                    @php $daysOverdue = (int) floor($dispense->due_date->diffInDays(now())); @endphp
                                    <span class="text-red-600 font-medium">{{ $daysOverdue }} {{ \Illuminate\Support\Str::plural('day', $daysOverdue) }}</span>
                                </td>
                                <td class="p-3">
                                    @if($dispense->reminded_at)
                                        <span class="text-green-600">{{ $dispense->reminded_at->format('d M') }}</span>
                                    @else
                                        <span class="text-gray-400">Not yet</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-6 text-center text-gray-500">No overdue dispenses.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Missing Renewals -->
    @if($filter === 'all' || $filter === 'renewals')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Missing Renewals (7+ days overdue)</h3>
                <span class="text-sm text-amber-600">{{ $this->missingRenewals->count() }} journeys</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 font-medium text-gray-600">Patient</th>
                            <th class="text-left p-3 font-medium text-gray-600">Pharmacy</th>
                            <th class="text-left p-3 font-medium text-gray-600">Renewal Due</th>
                            <th class="text-left p-3 font-medium text-gray-600">Doctor</th>
                            <th class="text-left p-3 font-medium text-gray-600">Dispenses</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($this->missingRenewals as $journey)
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-900">{{ $journey->patient->display_name }}</td>
                                <td class="p-3 text-gray-600">{{ $journey->pharmacy->name ?? '-' }}</td>
                                <td class="p-3 text-amber-600">{{ $journey->renewal_due_date?->format('d M Y') ?? '-' }}</td>
                                <td class="p-3 text-gray-600">{{ $journey->doctor_name ?? '-' }}</td>
                                <td class="p-3 text-gray-600">{{ $journey->dispenses_completed }}/{{ $journey->total_dispenses }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-6 text-center text-gray-500">No missing renewals.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Unresponsive Patients -->
    @if($filter === 'all' || $filter === 'unresponsive')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Unresponsive (10+ days after reminder)</h3>
                <span class="text-sm text-gray-500">{{ $this->unresponsivePatients->count() }} patients</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 font-medium text-gray-600">Patient</th>
                            <th class="text-left p-3 font-medium text-gray-600">Pharmacy</th>
                            <th class="text-left p-3 font-medium text-gray-600">Reminder Sent</th>
                            <th class="text-left p-3 font-medium text-gray-600">Due Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($this->unresponsivePatients as $dispense)
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-900">{{ $dispense->patient->display_name }}</td>
                                <td class="p-3 text-gray-600">{{ $dispense->journey->pharmacy->name ?? '-' }}</td>
                                <td class="p-3 text-gray-600">{{ $dispense->reminded_at?->format('d M Y') ?? '-' }}</td>
                                <td class="p-3 text-gray-600">{{ $dispense->due_date->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-6 text-center text-gray-500">No unresponsive patients.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
