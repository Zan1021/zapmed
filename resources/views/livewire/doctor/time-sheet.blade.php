<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">My time</h1>
            <p class="text-sm text-gray-500 mt-1">Time on patient records and requests, ready for your monthly invoice.</p>
        </div>
        <div class="flex items-center gap-3">
            <input type="month" wire:model.live="month" class="text-sm border-gray-300 rounded-lg">
            <a href="{{ route('doctor.time.export', ['month' => $month]) }}" class="px-3 py-2 text-sm border border-gray-300 rounded-lg" data-action="export">Download CSV</a>
        </div>
    </div>

    @if($running)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4 flex items-center justify-between" data-section="running">
            <p class="text-sm text-amber-900">
                Timer running since {{ $running->started_at->setTimezone($tz)->format('H:i') }}
                on {{ $running->patient?->name ?? $running->request?->title ?? 'a record' }}.
            </p>
            <button type="button" wire:click="stop" data-action="stop" class="px-3 py-1.5 text-sm bg-white border border-amber-300 rounded-lg">Stop</button>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" data-section="totals">
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500 uppercase">Total</p>
            <p class="text-2xl font-bold text-gray-900">{{ $summary['total_hours'] }} h</p><p class="text-xs text-gray-400">{{ $summary['total_minutes'] }} minutes</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500 uppercase">Entries</p>
            <p class="text-2xl font-bold text-gray-900">{{ $summary['entries'] }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500 uppercase">Edited</p>
            <p class="text-2xl font-bold text-gray-900">{{ $summary['adjusted'] }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500 uppercase">Cut at the maximum</p>
            <p class="text-2xl font-bold text-gray-900">{{ $summary['capped'] }}</p><p class="text-xs text-gray-400">check these</p></div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase mb-3">Add time by hand</h2>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">On</label>
                <select wire:model="target" class="text-sm border-gray-300 rounded-lg">
                    <option value="">Choose...</option>
                    <optgroup label="Patients">
                        @foreach($patients as $p)<option value="patient:{{ $p->id }}">{{ $p->first_name }} {{ $p->last_name }}</option>@endforeach
                    </optgroup>
                    <optgroup label="Requests">
                        @foreach($openRequests as $r)<option value="request:{{ $r->id }}">{{ $r->title }}</option>@endforeach
                    </optgroup>
                </select>
            </div>
            <div><label class="block text-xs text-gray-500 mb-1">Day</label><input type="date" wire:model="date" class="text-sm border-gray-300 rounded-lg"></div>
            <div><label class="block text-xs text-gray-500 mb-1">Minutes</label><input type="number" wire:model="minutes" min="1" class="text-sm border-gray-300 rounded-lg w-24"></div>
            <div class="flex-1 min-w-[10rem]"><label class="block text-xs text-gray-500 mb-1">Note</label><input type="text" wire:model="note" maxlength="255" class="w-full text-sm border-gray-300 rounded-lg"></div>
            <button type="button" wire:click="addManual" data-action="add-manual" class="px-4 py-2 bg-zapmed-600 text-white text-sm rounded-lg">Add</button>
        </div>
        @foreach(['target', 'date', 'minutes'] as $f) @error($f) <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr><th class="px-5 py-3 text-left">Day</th><th class="px-5 py-3 text-left">On</th><th class="px-5 py-3 text-right">Minutes</th><th class="px-5 py-3 text-right"></th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($summary['rows'] as $e)
                    <tr data-entry="{{ $e->id }}">
                        <td class="px-5 py-3 text-gray-700">{{ $e->started_at->setTimezone($tz)->format('j M') }}</td>
                        <td class="px-5 py-3 text-gray-800">{{ $e->patient?->name ?? $e->request?->title ?? '-' }}
                            @if($e->note)<span class="text-xs text-gray-400">&middot; {{ $e->note }}</span>@endif
                            @if($e->capped)<span class="text-xs text-amber-700"> (cut at the maximum)</span>@endif
                            @foreach($e->adjustments as $a)
                                <p class="text-xs text-gray-400">Changed {{ $a->old_minutes }} to {{ $a->new_minutes }} min: {{ $a->reason }}</p>
                            @endforeach
                        </td>
                        <td class="px-5 py-3 text-right text-gray-900">
                            @if($editing === $e->id)
                                <input type="number" wire:model="newMinutes" min="1" class="text-sm border-gray-300 rounded-lg w-20">
                            @else {{ $e->minutes }} @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            @if($editing === $e->id)
                                <input type="text" wire:model="reason" placeholder="Why?" maxlength="255" class="text-sm border-gray-300 rounded-lg w-48">
                                <button type="button" wire:click="saveEdit" data-action="save-edit" class="text-xs text-zapmed-700 ml-2">Save</button>
                                <button type="button" wire:click="cancelEdit" class="text-xs text-gray-500 ml-1">Cancel</button>
                                @foreach(['reason', 'minutes', 'entry'] as $f) @error($f) <p class="text-xs text-red-600">{{ $message }}</p> @enderror @endforeach
                            @else
                                <button type="button" wire:click="edit({{ $e->id }})" class="text-xs text-zapmed-700">Change</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-400" data-empty="time">No time logged this month.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($summary['patients']->isNotEmpty() || $summary['requests']->isNotEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-5" data-section="breakdown">
            <h2 class="text-sm font-semibold text-gray-500 uppercase mb-3">Where the time went</h2>
            <ul class="text-sm text-gray-700 space-y-1">
                @foreach($summary['patients'] as $p)<li>{{ $p['name'] }}: {{ $p['minutes'] }} min</li>@endforeach
                @foreach($summary['requests'] as $r)<li>Request "{{ $r['title'] }}": {{ $r['minutes'] }} min</li>@endforeach
            </ul>
        </div>
    @endif
</div>
