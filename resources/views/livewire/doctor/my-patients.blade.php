<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">My Patients</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $this->stats['total'] }} patients total, {{ $this->stats['this_month'] }} new this month</p>
        </div>
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search patients..."
            class="text-sm border-gray-300 rounded-lg focus:ring-zapmed-500 focus:border-zapmed-500 w-64">
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Patient</th>
                    <th class="px-5 py-3 text-left">Contact</th>
                    <th class="px-5 py-3 text-center">Last Visit</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($this->patients as $patient)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-4">
                            <p class="font-medium text-gray-900">{{ $patient->name }}</p>
                            <p class="text-xs text-gray-400">{{ $patient->member_number ?? 'No member #' }}</p>
                        </td>
                        <td class="px-5 py-4">
                            <p class="text-sm text-gray-700">{{ $patient->email }}</p>
                            <p class="text-xs text-gray-400">{{ $patient->phone }}</p>
                        </td>
                        <td class="px-5 py-4 text-center text-sm text-gray-500">
                            {{ $patient->appointments()->where('doctor_id', auth()->id())->latest()->first()?->appointment_date?->diffForHumans() ?? '—' }}
                        </td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('patient.book') }}" class="text-xs text-zapmed-600 hover:text-zapmed-700 font-medium">Book Follow-up</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-12 text-center text-sm text-gray-400">
                            No patients yet. They'll appear here after your first consultation.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->patients->links() }}</div>
</div>
