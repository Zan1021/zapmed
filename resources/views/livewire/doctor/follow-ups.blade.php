<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Follow-ups</h1>
        <p class="text-sm text-gray-500 mt-1">While a follow-up is open, that order's next repeat is on hold.</p>
    </div>

    <div class="mb-3 flex gap-2 text-sm">
        @foreach(['open' => 'Open', 'done' => 'Finished'] as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                class="px-3 py-1.5 rounded-lg border {{ $filter === $key ? 'bg-zapmed-600 text-white border-zapmed-600' : 'bg-white text-gray-700 border-gray-200' }}">{{ $label }}</button>
        @endforeach
    </div>

    @error('status') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Patient</th>
                    <th class="px-5 py-3 text-left">Order</th>
                    <th class="px-5 py-3 text-left">Due</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($followUps as $f)
                    <tr data-followup="{{ $f->id }}">
                        <td class="px-5 py-4 text-gray-900">{{ $f->patient?->name }}<p class="text-xs text-gray-400">{{ $f->patient?->member_number }}</p></td>
                        <td class="px-5 py-4 text-gray-700">{{ $f->order?->reference }}</td>
                        <td class="px-5 py-4 text-gray-700">
                            {{ $f->due_at->setTimezone($tz)->format('j M Y') }}
                            @if($f->isOpen() && $f->due_at->isPast()) <span class="text-xs text-red-600">overdue</span> @endif
                            <p class="text-xs text-gray-400">in {{ $f->days }} days &middot; {{ $f->is_free ? 'free' : 'paid' }}</p>
                        </td>
                        <td class="px-5 py-4 text-center"><span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">{{ ucfirst($f->status) }}</span></td>
                        <td class="px-5 py-4 text-right">
                            @if($f->isOpen())
                                @if($cancelling === $f->id)
                                    <input type="text" wire:model="reason" placeholder="Reason" maxlength="255" class="text-sm border-gray-300 rounded-lg w-44">
                                    <button type="button" wire:click="cancel" data-action="confirm-cancel" class="text-xs text-red-600 ml-2">Cancel it</button>
                                    @error('reason') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                @else
                                    <button type="button" wire:click="complete({{ $f->id }})" data-action="complete" class="text-xs text-zapmed-700 mr-3">Reviewed and submitted</button>
                                    @if($f->requested_by === auth()->id())
                                        <button type="button" wire:click="askCancel({{ $f->id }})" class="text-xs text-gray-500">Cancel</button>
                                    @endif
                                @endif
                            @elseif($f->cancel_reason)
                                <span class="text-xs text-gray-400">{{ $f->cancel_reason }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-gray-400" data-empty="followups">Nothing here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
