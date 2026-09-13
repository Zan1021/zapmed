<div>
    <x-slot name="header">Alerts</x-slot>

    @if(session('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('message') }}</div>
    @endif

    {{-- Open-count strip --}}
    <div class="mb-4 grid grid-cols-3 gap-3">
        <div class="border border-red-200 bg-red-50 rounded-lg p-3">
            <div class="text-xs text-red-500">Critical (open)</div>
            <div class="text-2xl font-bold text-red-700">{{ $this->openCounts['critical'] }}</div>
        </div>
        <div class="border border-amber-200 bg-amber-50 rounded-lg p-3">
            <div class="text-xs text-amber-600">Warning (open)</div>
            <div class="text-2xl font-bold text-amber-700">{{ $this->openCounts['warning'] }}</div>
        </div>
        <div class="border border-sky-200 bg-sky-50 rounded-lg p-3">
            <div class="text-xs text-sky-600">Informational (open)</div>
            <div class="text-2xl font-bold text-sky-700">{{ $this->openCounts['informational'] }}</div>
        </div>
    </div>

    {{-- Filters + scan --}}
    <div class="mb-4 flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500">Severity:</span>
            @foreach($allSeverities as $sev)
                <label class="text-xs flex items-center gap-1">
                    <input type="checkbox" wire:model.live="severities" value="{{ $sev->value }}" class="rounded border-gray-300" />
                    {{ $sev->label() }}
                </label>
            @endforeach
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500">Status:</span>
            @foreach($allStatuses as $st)
                <label class="text-xs flex items-center gap-1">
                    <input type="checkbox" wire:model.live="statuses" value="{{ $st->value }}" class="rounded border-gray-300" />
                    {{ $st->label() }}
                </label>
            @endforeach
        </div>
        <button wire:click="runScan" wire:loading.attr="disabled"
                class="ml-auto px-3 py-1.5 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40">
            Run scan now
        </button>
        <span wire:loading wire:target="runScan" class="text-xs text-gray-400">Scanning…</span>
    </div>

    {{-- Alert list --}}
    <div class="border border-gray-200 rounded-lg overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-500">
                <tr>
                    <th class="text-left px-4 py-2 font-medium">Severity</th>
                    <th class="text-left px-4 py-2 font-medium">Alert</th>
                    <th class="text-left px-4 py-2 font-medium">Status</th>
                    <th class="text-left px-4 py-2 font-medium">Assignee</th>
                    <th class="text-left px-4 py-2 font-medium">Raised</th>
                    <th class="text-right px-4 py-2 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($this->alerts as $alert)
                    <tr wire:key="alert-{{ $alert->id }}" class="hover:bg-gray-50">
                        <td class="px-4 py-2">
                            <span class="text-xs px-2 py-0.5 rounded-full
                                {{ $alert->severity->value === 'critical' ? 'bg-red-100 text-red-700' :
                                   ($alert->severity->value === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700') }}">
                                {{ $alert->severity->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <button wire:click="select({{ $alert->id }})" class="text-left hover:underline">
                                <div class="font-medium text-gray-800">{{ $alert->title }}</div>
                                <div class="text-xs text-gray-400 font-mono">{{ $alert->definition_code }}
                                    @if($alert->re_raise_count > 0) · re-raised ×{{ $alert->re_raise_count }} @endif
                                </div>
                            </button>
                        </td>
                        <td class="px-4 py-2">
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $alert->status->label() }}</span>
                        </td>
                        <td class="px-4 py-2 text-gray-600">
                            {{ $alert->assignee ? $alert->assignee->first_name . ' ' . $alert->assignee->last_name : '—' }}
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ $alert->raised_at?->diffForHumans() }}</td>
                        <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                            @if($alert->status->value === 'open')
                                <button wire:click="acknowledge({{ $alert->id }})" class="text-xs text-blue-600 hover:underline">Ack</button>
                            @endif
                            <button wire:click="resolve({{ $alert->id }})" class="text-xs text-green-700 hover:underline">Resolve</button>
                            <button wire:click="snooze({{ $alert->id }})" class="text-xs text-amber-600 hover:underline">Snooze</button>
                            <button wire:click="assignToMe({{ $alert->id }})" class="text-xs text-gray-500 hover:underline">Assign me</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">No alerts match the filters. 🎉</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $this->alerts->links() }}</div>

    {{-- Detail drawer --}}
    @if($this->selectedAlert)
        @php $a = $this->selectedAlert; @endphp
        <div class="fixed inset-0 z-40 flex justify-end" wire:key="alert-drawer-{{ $a->id }}">
            <div class="absolute inset-0 bg-black/30" wire:click="closeDrawer"></div>
            <div class="relative z-50 w-full max-w-lg bg-white h-full shadow-xl overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                    <div class="font-semibold text-gray-800">{{ $a->title }}</div>
                    <button wire:click="closeDrawer" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
                <div class="px-6 py-4 space-y-5">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-400">Definition</span><div class="font-mono text-xs">{{ $a->definition_code }}</div></div>
                        <div><span class="text-gray-400">Severity</span><div>{{ $a->severity->label() }}</div></div>
                        <div><span class="text-gray-400">Status</span><div>{{ $a->status->label() }}</div></div>
                        <div><span class="text-gray-400">Subject</span><div class="text-xs">{{ $a->subject_type }} #{{ $a->subject_id }}</div></div>
                        <div><span class="text-gray-400">Raised</span><div>{{ $a->raised_at?->format('d M Y H:i') }}</div></div>
                        <div><span class="text-gray-400">Re-raises</span><div>{{ $a->re_raise_count }}</div></div>
                    </div>
                    @if($a->detail)
                        <p class="text-sm text-gray-600 border border-gray-100 rounded-lg p-3">{{ $a->detail }}</p>
                    @endif

                    {{-- AI suggested next action (Task 8) --}}
                    <div class="border border-indigo-200 bg-indigo-50/40 rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-indigo-800">Suggested action</h4>
                            <button wire:click="suggestAction" wire:loading.attr="disabled" wire:target="suggestAction"
                                    class="text-xs px-3 py-1.5 rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40">
                                {{ $aiAction ? 'Regenerate' : 'Suggest' }}
                            </button>
                        </div>
                        <span wire:loading wire:target="suggestAction" class="text-xs text-gray-400">Thinking…</span>
                        @if($aiAction)
                            <p class="mt-2 text-sm text-gray-700">{{ $aiAction }}</p>
                            <p class="mt-1 text-[11px] text-gray-400">Generated by: {{ $aiActionBy === 'ai' ? 'OpenAI' : 'rule-based fallback' }}</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if($a->status->value === 'open')
                            <button wire:click="acknowledge({{ $a->id }})" class="px-3 py-1.5 rounded-lg text-sm text-white bg-blue-600 hover:bg-blue-700">Acknowledge</button>
                        @endif
                        <button wire:click="resolve({{ $a->id }})" class="px-3 py-1.5 rounded-lg text-sm text-white bg-green-600 hover:bg-green-700">Resolve</button>
                        <div class="flex items-center gap-1">
                            <button wire:click="snooze({{ $a->id }})" class="px-3 py-1.5 rounded-lg text-sm text-white bg-amber-600 hover:bg-amber-700">Snooze</button>
                            <input type="number" wire:model="snoozeHours" min="1" max="720" class="w-16 text-sm border-gray-300 rounded-lg" /><span class="text-xs text-gray-400">h</span>
                        </div>
                        <button wire:click="assignToMe({{ $a->id }})" class="px-3 py-1.5 rounded-lg text-sm bg-gray-100 text-gray-700 hover:bg-gray-200">Assign to me</button>
                    </div>

                    {{-- Comments --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Activity</h4>
                        <div class="space-y-2 mb-3">
                            @forelse($a->comments as $c)
                                <div class="text-sm border border-gray-100 rounded-lg px-3 py-2" wire:key="c-{{ $c->id }}">
                                    <p class="text-gray-700">{{ $c->body }}</p>
                                    <p class="text-[11px] text-gray-400">{{ $c->author?->first_name }} · {{ $c->created_at?->diffForHumans() }}</p>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400">No comments.</p>
                            @endforelse
                        </div>
                        <textarea wire:model="commentBody" rows="2" placeholder="Add a comment" class="w-full text-sm border-gray-300 rounded-lg"></textarea>
                        @error('commentBody') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        <button wire:click="addComment" class="mt-2 px-3 py-1.5 rounded-lg text-sm text-white bg-indigo-600 hover:bg-indigo-700">Comment</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
