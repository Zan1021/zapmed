<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Patient history</h1>
        <p class="text-sm text-gray-500 mt-1">Patients you have seen in the dates you choose, with the notes you can read.</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4 flex flex-wrap items-end gap-3 text-sm">
        <div><label class="block text-xs text-gray-500 mb-1">From</label><input type="date" wire:model.live="from" class="text-sm border-gray-300 rounded-lg"></div>
        <div><label class="block text-xs text-gray-500 mb-1">To</label><input type="date" wire:model.live="to" class="text-sm border-gray-300 rounded-lg"></div>
        <div class="flex-1 min-w-[12rem]"><label class="block text-xs text-gray-500 mb-1">Search</label><input type="text" wire:model.live.debounce.300ms="search" placeholder="Name, email or member number" class="w-full text-sm border-gray-300 rounded-lg"></div>
    </div>
    @foreach(['from', 'to'] as $f) @error($f) <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror @endforeach

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Patient</th>
                    <th class="px-5 py-3 text-center">Visits</th>
                    <th class="px-5 py-3 text-left">Last visit</th>
                    <th class="px-5 py-3 text-center">Notes</th>
                    <th class="px-5 py-3 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $r)
                    <tr data-patient="{{ $r['patient']->id }}">
                        <td class="px-5 py-3 text-gray-900">{{ $r['patient']->name }}<p class="text-xs text-gray-400">{{ $r['patient']->member_number }}</p></td>
                        <td class="px-5 py-3 text-center text-gray-700">{{ $r['visits'] }}</td>
                        <td class="px-5 py-3 text-gray-700">{{ \Illuminate\Support\Carbon::parse($r['last_visit'])->format('j M Y') }}</td>
                        <td class="px-5 py-3 text-center text-gray-700">{{ $r['notes'] }}</td>
                        <td class="px-5 py-3 text-right"><a href="{{ route('doctor.consultation', $r['latest_appointment_id']) }}" class="text-xs text-zapmed-700 hover:underline">Open record</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-gray-400" data-empty="history">No patients in these dates.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
