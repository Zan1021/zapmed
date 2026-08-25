<div>
    <x-slot name="header">Patients</x-slot>

    <!-- Search & Filters -->
    <div class="flex items-center gap-4 mb-6">
        <div class="relative flex-1 max-w-sm">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or profile code..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-green-500 focus:border-green-500" />
            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <select wire:model.live="consentFilter" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-green-500 focus:border-green-500">
            <option value="all">All Patients</option>
            <option value="opted_in">Opted In</option>
            <option value="opted_out">Opted Out</option>
            <option value="pending">Pending Consent</option>
        </select>
    </div>

    <!-- Patients Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left p-3 font-medium text-gray-600">Patient</th>
                        <th class="text-left p-3 font-medium text-gray-600">Profile Code</th>
                        <th class="text-left p-3 font-medium text-gray-600">Medical Aid</th>
                        <th class="text-center p-3 font-medium text-gray-600">Consent</th>
                        <th class="text-center p-3 font-medium text-gray-600">Active Journey</th>
                        <th class="text-left p-3 font-medium text-gray-600">Since</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($patients as $patient)
                        <tr class="hover:bg-gray-50">
                            <td class="p-3">
                                <p class="font-medium text-gray-900">{{ $patient->display_name }}</p>
                                @if(!$patient->is_primary_member)
                                    <p class="text-xs text-gray-500">Dependent ({{ $patient->dependent_relation }})</p>
                                @endif
                            </td>
                            <td class="p-3 text-gray-600 font-mono text-xs">{{ $patient->profile_code }}</td>
                            <td class="p-3 text-gray-600">{{ $patient->medical_aid_name ?? '-' }}</td>
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    @if($patient->consent_status === 'opted_in') bg-green-100 text-green-800
                                    @elseif($patient->consent_status === 'opted_out') bg-red-100 text-red-800
                                    @else bg-gray-100 text-gray-600
                                    @endif">
                                    {{ ucfirst(str_replace('_', ' ', $patient->consent_status)) }}
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                @if($patient->journeys->isNotEmpty())
                                    @php $j = $patient->journeys->first(); @endphp
                                    <span class="text-xs text-green-600">{{ $j->dispenses_completed }}/{{ $j->total_dispenses }}</span>
                                @else
                                    <span class="text-xs text-gray-400">None</span>
                                @endif
                            </td>
                            <td class="p-3 text-gray-500 text-xs">{{ $patient->created_at->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-500">No patients found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">
            {{ $patients->links() }}
        </div>
    </div>
</div>
