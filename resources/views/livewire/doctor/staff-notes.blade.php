<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Notes for me</h1>
        <p class="text-sm text-gray-500 mt-1">Staff notes sent to you or to your role.</p>
    </div>

    <div class="mb-3 flex gap-2 text-sm">
        @foreach(['open' => 'Needs an action', 'all' => 'All'] as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                class="px-3 py-1.5 rounded-lg border {{ $filter === $key ? 'bg-zapmed-600 text-white border-zapmed-600' : 'bg-white text-gray-700 border-gray-200' }}">{{ $label }}</button>
        @endforeach
    </div>

    @error('action') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror

    @forelse($notes as $n)
        @php($mine = $n->audiences->firstWhere('user_id', auth()->id()))
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-3" data-note="{{ $n->id }}">
            <p class="text-xs text-gray-500">
                {{ $n->author?->name ?? 'Unknown' }} &middot; {{ $n->created_at->format('j M Y, H:i') }}
                &middot; about {{ $n->patient?->name }} <span class="text-gray-400">{{ $n->patient?->member_number }}</span>
                @if($n->order) &middot; order {{ $n->order->reference }} @endif
                @if($mine && ! $mine->read_at) <span class="text-amber-700">&middot; new</span> @endif
            </p>
            <p class="text-sm text-gray-800 whitespace-pre-line mt-2">{{ $n->body }}</p>
            <div class="mt-2 flex gap-4 text-xs">
                @if($n->action_status === 'open')
                    <button type="button" wire:click="done({{ $n->id }})" data-action="done" class="text-zapmed-700">Mark action as done</button>
                @elseif($n->action_status === 'done')
                    <span class="text-green-700">Action done {{ $n->action_done_at?->format('j M') }}</span>
                @endif
                @if($mine && ! $mine->read_at)
                    <button type="button" wire:click="read({{ $n->id }})" class="text-gray-500">Mark as read</button>
                @endif
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-sm text-gray-400" data-empty="notes">Nothing here.</div>
    @endforelse
</div>
