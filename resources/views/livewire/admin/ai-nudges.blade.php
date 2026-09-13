<div>
    <x-slot name="header">AI Nudges</x-slot>

    @if(session('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('message') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ session('error') }}</div>
    @endif

    {{-- AI status banner --}}
    <div class="mb-6 p-3 rounded-lg text-sm border {{ $this->aiConfigured ? 'bg-indigo-50 border-indigo-200 text-indigo-700' : 'bg-amber-50 border-amber-200 text-amber-700' }}">
        @if($this->aiConfigured)
            OpenAI is configured — drafts are AI-written. Every draft still requires your approval before sending.
        @else
            No OpenAI key configured — drafts use the built-in templated fallback (fully functional). Approval still required before sending.
        @endif
    </div>

    {{-- Quick draft --}}
    <div class="mb-6 bg-white rounded-lg border border-gray-200 p-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Draft a nudge</h3>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Lead ID</label>
                <input type="number" wire:model="draftLeadId" placeholder="e.g. 42" class="text-sm border-gray-300 rounded-lg w-32" />
                @error('draftLeadId') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Kind</label>
                <select wire:model="draftKind" class="text-sm border-gray-300 rounded-lg">
                    <option value="reengagement">Re-engagement</option>
                    <option value="cross_sell">Cross-sell</option>
                    <option value="winback">Win-back</option>
                    <option value="checkin">Check-in</option>
                </select>
            </div>
            <button wire:click="draft" wire:loading.attr="disabled"
                    class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40">
                Generate draft
            </button>
            <span wire:loading wire:target="draft" class="text-xs text-gray-400">Drafting…</span>
        </div>
    </div>

    {{-- Filter --}}
    <div class="mb-4 flex items-center gap-2">
        @foreach(['pending' => 'Pending', 'draft' => 'Draft', 'approved' => 'Approved', 'sent' => 'Sent', 'dismissed' => 'Dismissed', 'all' => 'All'] as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')"
                    class="text-xs px-3 py-1.5 rounded-full border {{ $filter === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- List --}}
    <div class="space-y-3">
        @forelse($this->nudges as $nudge)
            <div class="bg-white rounded-lg border border-gray-200 p-4" wire:key="nudge-{{ $nudge->id }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-800">
                                {{ $nudge->patient?->first_name }} {{ $nudge->patient?->last_name ?: '—' }}
                            </span>
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ ucfirst(str_replace('_',' ',$nudge->kind)) }}</span>
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">{{ $nudge->channel }}</span>
                            <span class="text-[11px] px-2 py-0.5 rounded-full {{ $nudge->generated_by === 'ai' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">{{ $nudge->generated_by }}</span>
                        </div>
                        <p class="mt-2 text-sm text-gray-600 whitespace-pre-line">{{ $nudge->draft_body }}</p>
                    </div>
                    <div class="flex-shrink-0 text-right">
                        <span class="text-xs px-2 py-0.5 rounded-full
                            @class([
                                'bg-slate-100 text-slate-600' => $nudge->status->value === 'draft',
                                'bg-blue-100 text-blue-700' => $nudge->status->value === 'approved',
                                'bg-green-100 text-green-700' => $nudge->status->value === 'sent',
                                'bg-gray-100 text-gray-400' => $nudge->status->value === 'dismissed',
                            ])">
                            {{ $nudge->status->label() }}
                        </span>
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-3">
                    @if($nudge->status->value === 'draft')
                        <button wire:click="approve({{ $nudge->id }})" class="text-xs text-blue-600 hover:underline">Approve</button>
                        <button wire:click="openDismiss({{ $nudge->id }})" class="text-xs text-gray-500 hover:underline">Dismiss</button>
                    @elseif($nudge->status->value === 'approved')
                        <button wire:click="send({{ $nudge->id }})" class="text-xs text-green-600 hover:underline">Mark sent</button>
                        <button wire:click="openDismiss({{ $nudge->id }})" class="text-xs text-gray-500 hover:underline">Dismiss</button>
                    @else
                        <span class="text-xs text-gray-400">No actions.</span>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-400 text-center py-10">No nudges in this view.</p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $this->nudges->links() }}
    </div>

    {{-- Dismiss modal --}}
    @if($dismissId !== null)
        <div class="fixed inset-0 z-40 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/30" wire:click="cancelDismiss"></div>
            <div class="relative z-50 w-full max-w-md bg-white rounded-lg shadow-xl p-6">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Dismiss nudge</h4>
                <textarea wire:model="dismissReason" rows="2" placeholder="Reason (optional)"
                          class="w-full text-sm border-gray-300 rounded-lg"></textarea>
                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="cancelDismiss" class="px-3 py-2 text-sm text-gray-500 hover:underline">Cancel</button>
                    <button wire:click="confirmDismiss" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-gray-600 hover:bg-gray-700">Dismiss</button>
                </div>
            </div>
        </div>
    @endif
</div>
