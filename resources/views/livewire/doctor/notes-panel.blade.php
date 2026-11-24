<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5" data-panel="notes">
    <h3 class="text-sm font-semibold text-gray-900 mb-3">Notes</h3>

    @forelse($notes as $n)
        <div class="border-b border-gray-100 pb-2 mb-2" data-note="{{ $n->id }}" data-type="{{ $n->type }}">
            <p class="text-xs text-gray-500">
                {{ $labels[$n->type] }} &middot; {{ $n->author?->name ?? 'Unknown' }} &middot; {{ $n->created_at->format('j M Y, H:i') }}
                @if($n->order) &middot; order {{ $n->order->reference }} @endif
            </p>
            @if($n->type === 'operations')
                <p class="text-xs text-gray-400">For: {{ $n->audiences->map(fn ($a) => $a->user_id ? ($staff->firstWhere('id', $a->user_id)?->name ?? 'a colleague') : str_replace('_', ' ', $a->role))->join(', ') }}</p>
            @endif
            <p class="text-sm text-gray-800 whitespace-pre-line mt-1">{{ $n->body }}</p>
            @if($n->action_status === 'open')
                <button type="button" wire:click="done({{ $n->id }})" data-action="done" class="text-xs text-zapmed-700 mt-1">Action needed: mark as done</button>
            @elseif($n->action_status === 'done')
                <p class="text-xs text-green-700 mt-1">Action done {{ $n->action_done_at?->format('j M') }}</p>
            @endif
        </div>
    @empty
        <p class="text-xs text-gray-400 mb-2">No notes yet.</p>
    @endforelse

    @if($types)
        <select wire:model.live="type" class="w-full text-sm border-gray-300 rounded-lg mb-2">
            @foreach($types as $t)<option value="{{ $t }}">{{ $labels[$t] }}</option>@endforeach
        </select>
        @if($type === 'clinical')<p class="text-xs text-gray-400 mb-1">Only doctors who treat this patient can read it.</p>@endif
        @if($type === 'patient_summary')<p class="text-xs text-amber-700 mb-1">The patient can read this one.</p>@endif

        <textarea wire:model="body" rows="3" class="w-full text-sm border-gray-300 rounded-lg mb-2"></textarea>
        @error('body') <p class="text-xs text-red-600 mb-1">{{ $message }}</p> @enderror

        @if($type === 'operations')
            <p class="text-xs text-gray-500 mb-1">Who is it for?</p>
            <div class="flex flex-wrap gap-3 mb-1">
                @foreach(['admin' => 'Operations', 'doctor' => 'Doctors', 'health_coach' => 'Health coach'] as $role => $label)
                    <label class="inline-flex items-center text-xs text-gray-700"><input type="checkbox" wire:model="roles" value="{{ $role }}" class="rounded border-gray-300 mr-1">{{ $label }}</label>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-3 mb-2">
                @foreach($staff as $s)
                    <label class="inline-flex items-center text-xs text-gray-700"><input type="checkbox" wire:model="people" value="{{ $s->id }}" class="rounded border-gray-300 mr-1">{{ $s->name }}</label>
                @endforeach
            </div>
            <label class="inline-flex items-center text-xs text-gray-700 mb-2"><input type="checkbox" wire:model="actionRequired" class="rounded border-gray-300 mr-1">Someone needs to act on this</label>
            @error('audience') <p class="text-xs text-red-600 mb-1">{{ $message }}</p> @enderror
        @endif

        <select wire:model="orderId" class="w-full text-sm border-gray-300 rounded-lg mb-2">
            <option value="">No specific order</option>
            @foreach($orders as $o)<option value="{{ $o->id }}">{{ $o->reference }}</option>@endforeach
        </select>
        @error('order') <p class="text-xs text-red-600 mb-1">{{ $message }}</p> @enderror
        @error('action') <p class="text-xs text-red-600 mb-1">{{ $message }}</p> @enderror

        <button type="button" wire:click="save" data-action="save-note" class="px-3 py-1.5 bg-zapmed-600 text-white text-xs font-medium rounded-lg">Add note</button>
    @endif
</div>
