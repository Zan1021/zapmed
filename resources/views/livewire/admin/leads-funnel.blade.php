<div>
    <x-slot name="header">Leads &amp; Funnel</x-slot>

    @if(session('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('message') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ session('error') }}</div>
    @endif

    {{-- Filters --}}
    <div class="mb-4 grid grid-cols-1 md:grid-cols-4 gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search patient / member no."
               class="md:col-span-2 text-sm border-gray-300 rounded-lg" />
        <select wire:model.live="serviceLine" class="text-sm border-gray-300 rounded-lg">
            <option value="">All service lines</option>
            @foreach($this->serviceLines as $line)
                <option value="{{ $line }}">{{ $line }}</option>
            @endforeach
        </select>
        <select wire:model.live="assignee" class="text-sm border-gray-300 rounded-lg">
            <option value="">All assignees</option>
            @foreach($this->assignableStaff as $staff)
                <option value="{{ $staff->id }}">{{ $staff->first_name }} {{ $staff->last_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-4 flex items-center gap-3">
        <button wire:click="resetFilters" class="text-xs text-gray-500 hover:underline">Reset filters</button>
        <span wire:loading class="text-xs text-gray-400">Loading…</span>
    </div>

    {{-- Funnel board --}}
    <div class="overflow-x-auto pb-4">
        <div class="flex gap-3 min-w-max">
            @foreach($stages as $stage)
                <div class="w-64 flex-shrink-0">
                    <div class="rounded-t-lg border border-gray-200 bg-gray-100 px-3 py-2 flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700">{{ $stage->label() }}</span>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-white text-gray-600">
                            {{ $this->columnCounts[$stage->value] ?? 0 }}
                        </span>
                    </div>
                    <div class="border border-t-0 border-gray-200 rounded-b-lg bg-gray-50 p-2 space-y-2 min-h-[8rem]">
                        @forelse($this->columns[$stage->value] as $lead)
                            <button type="button" wire:click="selectLead({{ $lead->id }})" wire:key="lead-{{ $lead->id }}"
                                    class="w-full text-left bg-white rounded-lg border border-gray-200 p-3 hover:border-indigo-300 hover:shadow-sm transition
                                           {{ $this->selectedLeadId === $lead->id ? 'ring-2 ring-indigo-400' : '' }}">
                                <div class="text-sm font-medium text-gray-800">
                                    {{ $lead->patient?->first_name }} {{ $lead->patient?->last_name ?: '—' }}
                                </div>
                                <div class="mt-1 flex items-center justify-between text-xs text-gray-500">
                                    <span>{{ $lead->service_line ?? '—' }}</span>
                                    @if($lead->riskScore)
                                        <span class="px-1.5 py-0.5 rounded-full text-[10px]
                                            {{ $lead->riskScore->band->value === 'critical' ? 'bg-red-100 text-red-700' :
                                               ($lead->riskScore->band->value === 'high' ? 'bg-orange-100 text-orange-700' :
                                               ($lead->riskScore->band->value === 'medium' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700')) }}">
                                            {{ $lead->riskScore->score }}
                                        </span>
                                    @endif
                                </div>
                                @if($lead->active_flags_count > 0)
                                    <div class="mt-1 text-[11px] text-red-500">{{ $lead->active_flags_count }} flag(s)</div>
                                @endif
                            </button>
                        @empty
                            <p class="text-xs text-gray-400 text-center py-6">—</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Detail drawer --}}
    @if($this->selectedLead)
        @php $lead = $this->selectedLead; @endphp
        <div class="fixed inset-0 z-40 flex justify-end" wire:key="lead-drawer-{{ $lead->id }}">
            <div class="absolute inset-0 bg-black/30" wire:click="closeDrawer"></div>
            <div class="relative z-50 w-full max-w-xl bg-white h-full shadow-xl overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                    <div>
                        <div class="text-lg font-semibold text-gray-800">
                            {{ $lead->patient?->first_name }} {{ $lead->patient?->last_name }}
                        </div>
                        <div class="text-xs text-gray-500 font-mono">{{ $lead->patient?->member_number }}</div>
                    </div>
                    <button wire:click="closeDrawer" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>

                <div class="px-6 py-4 space-y-6">
                    {{-- Summary --}}
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-400">Stage</span><div class="font-medium">{{ $lead->current_stage->label() }}</div></div>
                        <div><span class="text-gray-400">Service line</span><div class="font-medium">{{ $lead->service_line ?? '—' }}</div></div>
                        <div><span class="text-gray-400">Assignee</span><div class="font-medium">{{ $lead->assignee?->first_name }} {{ $lead->assignee?->last_name ?: '—' }}</div></div>
                        <div><span class="text-gray-400">Last activity</span><div class="font-medium">{{ $lead->last_activity_at?->diffForHumans() }}</div></div>
                    </div>

                    {{-- Risk --}}
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-semibold text-gray-700">Risk score</h4>
                            <button wire:click="recomputeRisk" class="text-xs text-indigo-600 hover:underline">Recompute</button>
                        </div>
                        @if($lead->riskScore)
                            <div class="flex items-center gap-3 mb-2">
                                <span class="text-2xl font-bold text-gray-800">{{ $lead->riskScore->score }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full
                                    {{ $lead->riskScore->band->value === 'critical' ? 'bg-red-100 text-red-700' :
                                       ($lead->riskScore->band->value === 'high' ? 'bg-orange-100 text-orange-700' :
                                       ($lead->riskScore->band->value === 'medium' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700')) }}">
                                    {{ $lead->riskScore->band->label() }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mb-2">{{ $lead->riskScore->reasoning }}</p>
                            @if(is_array($lead->riskScore->factors))
                                <ul class="text-xs text-gray-600 space-y-0.5">
                                    @foreach($lead->riskScore->factors as $f)
                                        <li>• {{ $f['label'] }} <span class="text-gray-400">({{ $f['impact'] }})</span></li>
                                    @endforeach
                                </ul>
                            @endif
                        @else
                            <p class="text-xs text-gray-400">Not yet computed. Click Recompute.</p>
                        @endif
                    </div>

                    {{-- Move stage --}}
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Move stage</h4>
                        <div class="space-y-2">
                            <select wire:model="moveTo" class="w-full text-sm border-gray-300 rounded-lg">
                                <option value="">Select stage…</option>
                                @foreach($stages as $stage)
                                    @if($stage->value !== $lead->current_stage->value)
                                        <option value="{{ $stage->value }}">{{ $stage->label() }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('moveTo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            <input type="text" wire:model="moveNotes" placeholder="Notes (optional)" class="w-full text-sm border-gray-300 rounded-lg" />
                            <button wire:click="moveStage" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">Move</button>
                        </div>
                    </div>

                    {{-- Flags --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Flags</h4>
                        @if($lead->activeFlags->isEmpty())
                            <p class="text-xs text-gray-400 mb-2">No active flags.</p>
                        @else
                            <div class="flex flex-wrap gap-2 mb-2">
                                @foreach($lead->activeFlags as $flag)
                                    <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700 flex items-center gap-1">
                                        {{ $flag->kind->label() }}
                                        <button wire:click="clearFlag({{ $flag->id }})" class="text-gray-400 hover:text-red-500">&times;</button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        <div class="flex gap-2">
                            <select wire:model="flagKind" class="text-sm border-gray-300 rounded-lg flex-1">
                                <option value="">Add flag…</option>
                                @foreach($flagKinds as $kind)
                                    <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                                @endforeach
                            </select>
                            <button wire:click="raiseFlag" class="px-3 py-1.5 rounded-lg text-sm text-white bg-gray-700 hover:bg-gray-800">Add</button>
                        </div>
                        @error('flagKind') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Notes --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Notes</h4>
                        <div class="space-y-2 mb-3">
                            @forelse($lead->notes as $note)
                                <div class="text-sm border border-gray-100 rounded-lg px-3 py-2">
                                    @if($note->pinned) <span class="text-[10px] text-amber-600">📌 pinned</span> @endif
                                    <p class="text-gray-700">{{ $note->body }}</p>
                                    <p class="text-[11px] text-gray-400">{{ $note->author?->first_name }} · {{ $note->created_at?->diffForHumans() }}</p>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400">No notes.</p>
                            @endforelse
                        </div>
                        <textarea wire:model="noteBody" rows="2" placeholder="Add a note" class="w-full text-sm border-gray-300 rounded-lg"></textarea>
                        @error('noteBody') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        <label class="flex items-center gap-2 text-xs text-gray-500 mt-1">
                            <input type="checkbox" wire:model="notePinned" class="rounded border-gray-300" /> Pin this note
                        </label>
                        <button wire:click="addNote" class="mt-2 px-3 py-1.5 rounded-lg text-sm text-white bg-indigo-600 hover:bg-indigo-700">Add note</button>
                    </div>

                    {{-- Funnel timeline --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Funnel history</h4>
                        <ol class="relative border-l border-gray-200 ml-2 space-y-3">
                            @foreach($lead->funnelEvents as $event)
                                <li class="ml-4" wire:key="fe-{{ $event->id }}">
                                    <span class="absolute -left-1.5 w-3 h-3 rounded-full bg-indigo-400 border border-white"></span>
                                    <div class="text-sm">
                                        <span class="text-gray-400">{{ $event->from_stage?->label() ?? 'created' }}</span>
                                        <span class="mx-1 text-gray-300">→</span>
                                        <span class="font-medium text-gray-800">{{ $event->to_stage->label() }}</span>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        {{ $event->occurred_at?->format('d M Y H:i') }} @if($event->actor) · {{ $event->actor }} @endif
                                    </div>
                                    @if($event->notes) <div class="text-xs text-gray-500">{{ $event->notes }}</div> @endif
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
