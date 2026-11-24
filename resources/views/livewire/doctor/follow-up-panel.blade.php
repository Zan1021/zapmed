<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5" data-panel="follow-up">
    <h3 class="text-sm font-semibold text-gray-900 mb-3">Follow-up</h3>

    @foreach($open as $f)
        <p class="text-xs text-gray-600 mb-2" data-open-followup="{{ $f->id }}">
            Follow-up due {{ $f->due_at->setTimezone($tz)->format('j M') }} for order {{ $f->order?->reference }} ({{ $f->status }}).
            The next repeat is on hold.
        </p>
    @endforeach

    @if($orders->isEmpty())
        <p class="text-xs text-gray-400">No order to attach a follow-up to.</p>
    @else
        <label class="block text-xs text-gray-500 mb-1">Order</label>
        <select wire:model="orderId" class="w-full text-sm border-gray-300 rounded-lg mb-2">
            <option value="">Choose...</option>
            @foreach($orders as $o)<option value="{{ $o->id }}">{{ $o->reference }} ({{ $o->status }})</option>@endforeach
        </select>

        <label class="block text-xs text-gray-500 mb-1">Follow-up in</label>
        <select wire:model="days" class="w-full text-sm border-gray-300 rounded-lg mb-2">
            <option value="">Choose...</option>
            @foreach($days_options as $d)<option value="{{ $d }}">{{ $d }} days</option>@endforeach
        </select>

        <label class="block text-xs text-gray-500 mb-1">Note (only you see it)</label>
        <textarea wire:model="note" rows="2" class="w-full text-sm border-gray-300 rounded-lg mb-2"></textarea>

        @foreach(['days', 'order'] as $f) @error($f) <p class="text-xs text-red-600 mb-1">{{ $message }}</p> @enderror @endforeach

        <button type="button" wire:click="request" data-action="request-followup" class="px-3 py-1.5 bg-zapmed-600 text-white text-xs font-medium rounded-lg">Request follow-up</button>
    @endif
</div>
