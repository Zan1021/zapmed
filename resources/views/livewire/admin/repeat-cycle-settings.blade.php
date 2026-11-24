<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Repeat cycles</h1>
        <p class="mt-1 text-sm text-gray-500">How long a pack lasts and when its first repeat falls. A 28 day pack first repeats on day 23, which leaves a 5 day delivery buffer. Changes only affect repeats that are scheduled from now on.</p>
    </div>

    <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th class="px-4 py-3 text-left">Name</th><th class="px-4 py-3 text-right">Pack days</th><th class="px-4 py-3 text-right">First repeat on day</th>
                <th class="px-4 py-3 text-center">Same day</th><th class="px-4 py-3 text-center">On</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($cycles as $c)
                    <tr data-cycle="{{ $c->id }}">
                        <td class="px-4 py-2 text-gray-900">{{ $c->description }}</td>
                        <td class="px-4 py-2 text-right">{{ $c->days }}</td>
                        <td class="px-4 py-2 text-right">{{ $c->first_repeat_days }}</td>
                        <td class="px-4 py-2 text-center">{{ $c->same_day ? 'Yes' : '' }}</td>
                        <td class="px-4 py-2 text-center">{{ $c->is_enabled ? 'On' : 'Off' }}</td>
                        <td class="px-4 py-2 text-right">
                            <button type="button" wire:click="edit({{ $c->id }})" data-action="edit" class="mr-3 text-indigo-600">Edit</button>
                            <button type="button" wire:click="toggle({{ $c->id }})" data-action="toggle" class="text-gray-600">{{ $c->is_enabled ? 'Switch off' : 'Switch on' }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @foreach(['description', 'days', 'first_repeat_days'] as $f) @error($f) <p class="mb-2 text-sm text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror @endforeach

    <div class="rounded-xl border border-gray-200 bg-white p-4" data-form="cycle">
        <h2 class="mb-3 text-sm font-semibold uppercase text-gray-500">{{ $editing ? 'Edit cycle' : 'Add a cycle' }}</h2>
        <div class="flex flex-wrap items-end gap-3 text-sm">
            <label>Name <input type="text" wire:model="description" class="mt-1 block w-56 rounded-lg border-gray-300 text-sm"></label>
            <label>Pack days <input type="number" wire:model="days" min="1" max="365" class="mt-1 block w-24 rounded-lg border-gray-300 text-sm"></label>
            <label>First repeat on day <input type="number" wire:model="firstRepeatDays" min="1" class="mt-1 block w-24 rounded-lg border-gray-300 text-sm"></label>
            <label class="flex items-center"><input type="checkbox" wire:model="sameDay" class="mr-2 rounded border-gray-300"> Same day</label>
            <label class="flex items-center"><input type="checkbox" wire:model="enabled" class="mr-2 rounded border-gray-300"> On</label>
            <button type="button" wire:click="save" data-action="save" class="rounded-lg bg-indigo-600 px-4 py-2 text-white">Save</button>
            @if($editing)<button type="button" wire:click="cancel" class="text-gray-500">Cancel</button>@endif
        </div>
    </div>
</div>
