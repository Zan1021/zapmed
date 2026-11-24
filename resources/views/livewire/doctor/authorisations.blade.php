<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Authorisations</h1>
        <p class="text-sm text-gray-500 mt-1">Each authorisation covers six months of repeats. Review it around month five: renew it, or ask for a follow-up first.</p>
    </div>

    <div class="mb-3 flex gap-2 text-sm">
        @foreach(['due' => 'Due for review', 'active' => 'All active', 'past' => 'Renewed or ended'] as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                class="px-3 py-1.5 rounded-lg border {{ $filter === $key ? 'bg-zapmed-600 text-white border-zapmed-600' : 'bg-white text-gray-700 border-gray-200' }}">{{ $label }}</button>
        @endforeach
    </div>

    @error('status') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror
    @error('order') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Patient</th>
                    <th class="px-5 py-3 text-left">Order</th>
                    <th class="px-5 py-3 text-left">Ends</th>
                    <th class="px-5 py-3 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $a)
                    <tr data-authorisation="{{ $a->id }}">
                        <td class="px-5 py-4 text-gray-900">{{ $a->patient?->name }}<p class="text-xs text-gray-400">{{ $a->patient?->member_number }}</p></td>
                        <td class="px-5 py-4 text-gray-700">{{ $a->order?->reference }}</td>
                        <td class="px-5 py-4 text-gray-700">
                            {{ $a->expires_on->format('j M Y') }}
                            @if($a->status === 'expired' || ($a->status === 'active' && $a->expires_on->format('Y-m-d') <= $today)) <span class="text-xs text-red-600">ended, repeats on hold</span>
                            @elseif($a->status === 'renewed') <span class="text-xs text-gray-400">renewed</span> @endif
                            @if($a->followUp && $a->followUp->isOpen()) <p class="text-xs text-amber-700">Follow-up asked for, due {{ $a->followUp->due_at->format('j M') }}</p> @endif
                        </td>
                        <td class="px-5 py-4 text-right">
                            @if(in_array($a->status, ['active', 'expired']))
                                @if($acting === $a->id)
                                    @if($mode === 'followup')
                                        <select wire:model="days" class="text-sm border-gray-300 rounded-lg">
                                            <option value="">Days...</option>
                                            @foreach($days_options as $d)<option value="{{ $d }}">{{ $d }} days</option>@endforeach
                                        </select>
                                    @endif
                                    <input type="text" wire:model="note" placeholder="Note (optional)" class="text-sm border-gray-300 rounded-lg w-44">
                                    <button type="button" wire:click="{{ $mode === 'renew' ? 'renew' : 'followUp' }}" data-action="confirm-{{ $mode }}" class="text-xs text-zapmed-700 ml-2">Confirm</button>
                                    <button type="button" wire:click="close" class="text-xs text-gray-500 ml-1">Close</button>
                                    @error('days') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                @else
                                    <button type="button" wire:click="open({{ $a->id }}, 'renew')" data-action="renew" class="text-xs text-zapmed-700 mr-3">Renew for six months</button>
                                    <button type="button" wire:click="open({{ $a->id }}, 'followup')" data-action="followup" class="text-xs text-gray-600">Ask for a follow-up</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-12 text-center text-sm text-gray-400" data-empty="authorisations">Nothing here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
