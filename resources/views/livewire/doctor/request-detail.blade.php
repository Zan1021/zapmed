<div>
    <a href="{{ route('doctor.requests') }}" class="text-sm text-zapmed-700 hover:underline">&larr; All requests</a>

    <div class="mt-3 mb-6 flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $r->title }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                From {{ $r->requester?->name ?? 'Unknown' }} &middot; {{ $r->created_at->format('j M Y, H:i') }}
            </p>
        </div>
        <span class="text-xs px-3 py-1 rounded-full bg-gray-100 text-gray-700" data-status="{{ $r->status }}">{{ $r->label() }}</span>
    </div>

    @if(auth()->user()->isDoctor())
        <div class="mb-3"><livewire:doctor.timer-button :request-id="$r->id" :key="'t-'.$r->id" /></div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4">
        <h2 class="text-sm font-semibold text-gray-500 uppercase mb-2">What is needed</h2>
        <p class="text-sm text-gray-800 whitespace-pre-line">{{ $r->description ?: 'No description.' }}</p>

        <h2 class="text-sm font-semibold text-gray-500 uppercase mt-5 mb-2">People</h2>
        <p class="text-sm text-gray-800">
            To: {{ $r->people->where('pivot.role', 'assignee')->map->name->join(', ') }}
            @if($r->people->where('pivot.role', 'participant')->isNotEmpty())
                <br>In the loop: {{ $r->people->where('pivot.role', 'participant')->map->name->join(', ') }}
            @endif
        </p>

        @if($r->refs->isNotEmpty())
            <h2 class="text-sm font-semibold text-gray-500 uppercase mt-5 mb-2">Patients</h2>
            <ul class="text-sm text-gray-800 space-y-1">
                @foreach($r->refs as $ref)
                    <li data-ref="{{ $ref->patient_id }}">{{ $ref->patient?->name }} <span class="text-xs text-gray-400">{{ $ref->patient?->member_number }}</span>
                        @if($ref->order) &middot; order {{ $ref->order->reference }} @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if($r->response)
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4" data-section="response">
            <h2 class="text-sm font-semibold text-gray-500 uppercase mb-2">Response</h2>
            <p class="text-sm text-gray-800 whitespace-pre-line">{{ $r->response }}</p>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4">
        <h2 class="text-sm font-semibold text-gray-500 uppercase mb-2">Files</h2>
        @forelse($r->files as $f)
            <p class="text-sm"><a class="text-zapmed-700 hover:underline" href="{{ route('doctor.requests.file', [$r, $f]) }}">{{ $f->original_name }}</a>
                <span class="text-xs text-gray-400">{{ number_format($f->size_bytes / 1024, 0) }} KB &middot; {{ $f->uploader?->name }}</span></p>
        @empty
            <p class="text-sm text-gray-400">No files.</p>
        @endforelse

        @if($canAttach)
            <div class="mt-3 flex items-center gap-3">
                <input type="file" wire:model="upload" class="text-sm">
                <button type="button" wire:click="attach" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg">Add file</button>
            </div>
            @error('upload') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            @error('file') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        @endif
    </div>

    @error('status') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror

    @if($isAssignee && in_array($r->status, ['pending', 'in_review', 'in_progress']))
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4" data-section="actions">
            @if($r->status !== 'in_progress')
                <button type="button" wire:click="start" data-action="start" class="px-4 py-2 text-sm border border-gray-300 rounded-lg mb-4">Start work</button>
            @endif
            <label class="block text-sm font-medium text-gray-700 mb-1">Your response</label>
            <textarea wire:model="response" rows="4" class="w-full text-sm border-gray-300 rounded-lg"></textarea>
            @error('response') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            <button type="button" wire:click="submit" data-action="submit" class="mt-3 px-4 py-2 bg-zapmed-600 text-white text-sm font-medium rounded-lg">Send response</button>
        </div>
    @endif

    @if($canComplete)
        <button type="button" wire:click="complete" data-action="complete" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg mb-4">Mark as completed</button>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-500 uppercase mb-2">History</h2>
        <ul class="text-sm text-gray-700 space-y-1">
            @foreach($r->events as $e)
                <li>{{ $e->created_at?->format('j M, H:i') }} &middot; {{ $e->user?->name ?? 'System' }}
                    @if($e->event === 'status') moved it to {{ \App\Models\DoctorRequest::LABELS[$e->to_status] ?? $e->to_status }}
                    @elseif($e->event === 'created') sent the request
                    @elseif($e->event === 'file') added a file ({{ $e->note }})
                    @else {{ $e->event }} @endif
                </li>
            @endforeach
        </ul>
    </div>
</div>
