<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Blood tests</h1>
        <p class="text-sm text-gray-500 mt-1">Requested tests, results that came back and your review.</p>
    </div>

    <div class="mb-3 flex gap-2 text-sm">
        @foreach(['open' => 'Open', 'done' => 'Finished'] as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                class="px-3 py-1.5 rounded-lg border {{ $filter === $key ? 'bg-zapmed-600 text-white border-zapmed-600' : 'bg-white text-gray-700 border-gray-200' }}">{{ $label }}</button>
        @endforeach
    </div>

    @error('status') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror

    @forelse($requests as $r)
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-3" data-bloodtest="{{ $r->id }}">
            <div class="flex items-start justify-between">
                <div>
                    <p class="font-medium text-gray-900">{{ $r->patient?->name }} <span class="text-xs text-gray-400">{{ $r->patient?->member_number }}</span></p>
                    <p class="text-sm text-gray-700 mt-1">{{ implode(', ', $r->testNames()) ?: 'See note' }}</p>
                    @if($r->free_text)<p class="text-sm text-gray-600 mt-1 whitespace-pre-line">{{ $r->free_text }}</p>@endif
                    <p class="text-xs text-gray-400 mt-1">
                        Asked {{ $r->created_at->format('j M Y') }}
                        @if($r->lab_form) &middot; {{ config('doctor.blood_tests.lab_forms')[$r->lab_form] ?? $r->lab_form }} @endif
                        @if($r->lab_form_path) &middot; <a class="text-zapmed-700 hover:underline" href="{{ route('bloodtests.form', $r) }}">{{ $r->lab_form_name }}</a> @endif
                    </p>
                </div>
                <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700" data-status="{{ $r->status }}">{{ str_replace('_', ' ', ucfirst($r->status)) }}</span>
            </div>

            @if($r->results->isNotEmpty())
                <ul class="mt-3 text-sm space-y-1">
                    @foreach($r->results as $res)
                        <li><a class="text-zapmed-700 hover:underline" href="{{ route('bloodtests.result', [$r, $res]) }}">{{ $res->original_name }}</a>
                            <span class="text-xs text-gray-400">uploaded by {{ $res->uploader_role }}</span></li>
                    @endforeach
                </ul>
            @endif
            @if($r->review_note)<p class="text-sm text-gray-700 mt-2"><span class="text-xs text-gray-400">Your review:</span> {{ $r->review_note }}</p>@endif
            @if($r->cancel_reason)<p class="text-xs text-gray-400 mt-2">Cancelled: {{ $r->cancel_reason }}</p>@endif

            @if($r->isOpen())
                @if($acting === $r->id)
                    <div class="mt-3 border-t border-gray-100 pt-3">
                        @if($mode === 'upload')
                            <input type="file" wire:model="upload" class="text-sm">
                            <button type="button" wire:click="uploadResult" data-action="upload" class="ml-2 px-3 py-1.5 text-sm border border-gray-300 rounded-lg">Add result</button>
                            @error('upload') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            @error('file') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        @else
                            <textarea wire:model="text" rows="2" placeholder="{{ $mode === 'cancel' ? 'Why are you cancelling?' : 'Your review (optional)' }}" class="w-full text-sm border-gray-300 rounded-lg"></textarea>
                            @error('reason') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            <button type="button" wire:click="{{ $mode === 'cancel' ? 'cancel' : 'review' }}" data-action="confirm-{{ $mode }}" class="mt-2 px-3 py-1.5 bg-zapmed-600 text-white text-sm rounded-lg">
                                {{ $mode === 'cancel' ? 'Cancel the request' : 'Mark as reviewed' }}</button>
                        @endif
                        <button type="button" wire:click="close" class="ml-2 text-sm text-gray-500">Close</button>
                    </div>
                @else
                    <div class="mt-3 flex gap-4 text-xs">
                        <button type="button" wire:click="open({{ $r->id }}, 'upload')" class="text-zapmed-700">Upload a result</button>
                        @if($r->status === 'results_received')
                            <button type="button" wire:click="open({{ $r->id }}, 'review')" data-action="review" class="text-zapmed-700">Review results</button>
                        @endif
                        @if($r->requested_by === auth()->id())
                            <button type="button" wire:click="open({{ $r->id }}, 'cancel')" class="text-gray-500">Cancel</button>
                        @endif
                    </div>
                @endif
            @endif
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-sm text-gray-400" data-empty="bloodtests">Nothing here.</div>
    @endforelse
</div>
