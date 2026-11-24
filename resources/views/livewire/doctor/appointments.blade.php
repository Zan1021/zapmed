<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Appointments</h1>
        <p class="text-sm text-gray-500 mt-1">New and past appointments, with the dates and services you choose.</p>
    </div>

    <div class="mb-3 flex gap-2 text-sm">
        @foreach(['new' => 'New', 'past' => 'Past'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" data-tab="{{ $key }}"
                class="px-3 py-1.5 rounded-lg border {{ $tab === $key ? 'bg-zapmed-600 text-white border-zapmed-600' : 'bg-white text-gray-700 border-gray-200' }}">{{ $label }}</button>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4 flex flex-wrap items-end gap-3 text-sm">
        @if($tab === 'new')
            <div>
                <label class="block text-xs text-gray-500 mb-1">Show</label>
                <select wire:model.live="view" class="text-sm border-gray-300 rounded-lg">
                    <option value="list">Newest booked first</option>
                    <option value="day">A day</option>
                    <option value="week">A week</option>
                    <option value="month">A month</option>
                </select>
            </div>
        @endif
        @if($tab === 'new' && $view !== 'list')
            <div><label class="block text-xs text-gray-500 mb-1">Starting</label><input type="date" wire:model.live="anchor" class="text-sm border-gray-300 rounded-lg"></div>
        @else
            <div><label class="block text-xs text-gray-500 mb-1">From</label><input type="date" wire:model.live="from" class="text-sm border-gray-300 rounded-lg"></div>
            <div><label class="block text-xs text-gray-500 mb-1">To</label><input type="date" wire:model.live="to" class="text-sm border-gray-300 rounded-lg"></div>
        @endif
        <div>
            <label class="block text-xs text-gray-500 mb-1">Service</label>
            <select wire:model.live="type" class="text-sm border-gray-300 rounded-lg">
                <option value="">All</option>
                @foreach($types as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
        </div>
        @if($tab === 'new' && $view === 'list')
            <div>
                <label class="block text-xs text-gray-500 mb-1">Order by</label>
                <select wire:model.live="sort" class="text-sm border-gray-300 rounded-lg">
                    <option value="booked">When it was booked</option>
                    <option value="date">Appointment date</option>
                </select>
            </div>
        @endif
        <button type="button" wire:click="clear" class="text-xs text-gray-500 pb-2">Clear</button>
    </div>
    @foreach(['from', 'to', 'anchor', 'view'] as $f) @error($f) <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror @endforeach

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">When</th>
                    <th class="px-5 py-3 text-left">Patient</th>
                    <th class="px-5 py-3 text-left">Service</th>
                    @if($tab === 'past')
                        <th class="px-5 py-3 text-center">Prescriptions</th>
                        <th class="px-5 py-3 text-left">Next repeat</th>
                    @endif
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $a)
                    <tr data-appointment="{{ $a->id }}">
                        <td class="px-5 py-3 text-gray-800">{{ $a->appointment_date->format('j M Y') }}<p class="text-xs text-gray-400">{{ \Illuminate\Support\Str::of((string) $a->start_time)->limit(5, '') }}</p></td>
                        <td class="px-5 py-3 text-gray-900">{{ $a->patient?->name }}<p class="text-xs text-gray-400">{{ $a->patient?->member_number }}</p></td>
                        <td class="px-5 py-3 text-gray-700">{{ $a->type_label }}</td>
                        @if($tab === 'past')
                            <td class="px-5 py-3 text-center text-gray-700">{{ $a->submitted_prescriptions }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $a->next_repeat ? \Illuminate\Support\Carbon::parse($a->next_repeat)->format('j M Y') : '-' }}</td>
                        @endif
                        <td class="px-5 py-3 text-center"><span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">{{ str_replace('_', ' ', ucfirst($a->status)) }}</span></td>
                        <td class="px-5 py-3 text-right"><a href="{{ route('doctor.consultation', $a) }}" class="text-xs text-zapmed-700 hover:underline">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $tab === 'past' ? 7 : 5 }}" class="px-5 py-12 text-center text-sm text-gray-400" data-empty="appointments">Nothing here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
