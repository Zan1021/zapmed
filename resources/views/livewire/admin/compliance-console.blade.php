<div>
    <x-slot name="header">Compliance (POPIA)</x-slot>

    @if(session('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('message') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ session('error') }}</div>
    @endif

    {{-- Tabs --}}
    <div class="mb-6 flex items-center gap-2 border-b border-gray-200">
        <button wire:click="$set('tab', 'dsars')"
                class="px-4 py-2 text-sm font-medium -mb-px border-b-2 {{ $tab === 'dsars' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            DSAR queue
        </button>
        <button wire:click="$set('tab', 'retention')"
                class="px-4 py-2 text-sm font-medium -mb-px border-b-2 {{ $tab === 'retention' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Retention schedule
        </button>
    </div>

    @if($tab === 'dsars')
        <div class="mb-4 flex items-center gap-2">
            @foreach(['open' => 'Open', 'overdue' => 'Overdue', 'all' => 'All'] as $key => $label)
                <button wire:click="$set('dsarFilter', '{{ $key }}')"
                        class="text-xs px-3 py-1.5 rounded-full border {{ $dsarFilter === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="text-left px-3 py-2 font-medium">DSAR</th>
                        <th class="text-left px-3 py-2 font-medium">Patient</th>
                        <th class="text-left px-3 py-2 font-medium">Kind</th>
                        <th class="text-left px-3 py-2 font-medium">Status</th>
                        <th class="text-left px-3 py-2 font-medium">Due</th>
                        <th class="text-right px-3 py-2 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($this->dsars as $dsar)
                        <tr wire:key="dsar-{{ $dsar->id }}" class="{{ $dsar->isOverdue() ? 'bg-red-50' : '' }}">
                            <td class="px-3 py-2 font-mono text-xs">{{ $dsar->dsar_number }}</td>
                            <td class="px-3 py-2">{{ $dsar->principal?->first_name }} {{ $dsar->principal?->last_name }}</td>
                            <td class="px-3 py-2">{{ $dsar->kind->label() }}</td>
                            <td class="px-3 py-2">
                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $dsar->status->label() }}</span>
                                @if($dsar->isOverdue())
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700 ml-1">Overdue</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs {{ $dsar->isOverdue() ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                {{ $dsar->due_at?->format('d M Y') }}
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                @if($dsar->status->value === 'received')
                                    <button wire:click="acknowledge({{ $dsar->id }})" class="text-xs text-blue-600 hover:underline">Acknowledge</button>
                                @endif
                                @if(in_array($dsar->status->value, ['received','acknowledged']))
                                    <button wire:click="start({{ $dsar->id }})" class="ml-2 text-xs text-indigo-600 hover:underline">Start</button>
                                @endif
                                @if(in_array($dsar->status->value, ['acknowledged','in_progress']))
                                    <button wire:click="complete({{ $dsar->id }})" class="ml-2 text-xs text-green-600 hover:underline">Complete</button>
                                @endif
                                @if(in_array($dsar->status->value, ['received','acknowledged','in_progress']))
                                    <button wire:click="openReject({{ $dsar->id }})" class="ml-2 text-xs text-red-600 hover:underline">Reject</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-sm text-gray-400">No DSARs in this view.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->dsars->links() }}</div>
    @else
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="text-left px-3 py-2 font-medium">Data class</th>
                        <th class="text-left px-3 py-2 font-medium">Record</th>
                        <th class="text-left px-3 py-2 font-medium">Action</th>
                        <th class="text-left px-3 py-2 font-medium">Due</th>
                        <th class="text-left px-3 py-2 font-medium">Status</th>
                        <th class="text-right px-3 py-2 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($this->retentionItems as $item)
                        <tr wire:key="ret-{{ $item->id }}">
                            <td class="px-3 py-2">{{ $item->data_class }}</td>
                            <td class="px-3 py-2 font-mono text-xs">#{{ $item->aggregate_id }}</td>
                            <td class="px-3 py-2">{{ $item->policy?->action?->label() ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ $item->due_at?->format('d M Y') }}</td>
                            <td class="px-3 py-2">
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $item->legal_hold ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $item->status->label() }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                @if($item->legal_hold)
                                    <button wire:click="releaseHold({{ $item->id }})" class="text-xs text-amber-600 hover:underline">Release hold</button>
                                @else
                                    @if($item->status->value === 'scheduled')
                                        <button wire:click="runRetention({{ $item->id }})" class="text-xs text-green-600 hover:underline">Run</button>
                                    @endif
                                    <button wire:click="openHold({{ $item->id }})" class="ml-2 text-xs text-gray-500 hover:underline">Legal hold</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-sm text-gray-400">No retention items scheduled.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $this->retentionItems->links() }}</div>
    @endif

    {{-- Reject modal --}}
    @if($rejectId !== null)
        <div class="fixed inset-0 z-40 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/30" wire:click="cancelReject"></div>
            <div class="relative z-50 w-full max-w-md bg-white rounded-lg shadow-xl p-6">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Reject DSAR</h4>
                <textarea wire:model="rejectReason" rows="3" placeholder="Reason (required — e.g. legal hold blocks erasure)"
                          class="w-full text-sm border-gray-300 rounded-lg"></textarea>
                @error('rejectReason') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="cancelReject" class="px-3 py-2 text-sm text-gray-500 hover:underline">Cancel</button>
                    <button wire:click="confirmReject" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700">Reject</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Legal hold modal --}}
    @if($holdId !== null)
        <div class="fixed inset-0 z-40 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/30" wire:click="cancelHold"></div>
            <div class="relative z-50 w-full max-w-md bg-white rounded-lg shadow-xl p-6">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Place legal hold</h4>
                <textarea wire:model="holdReason" rows="3" placeholder="Reason for hold (required)"
                          class="w-full text-sm border-gray-300 rounded-lg"></textarea>
                @error('holdReason') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="cancelHold" class="px-3 py-2 text-sm text-gray-500 hover:underline">Cancel</button>
                    <button wire:click="confirmHold" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-amber-600 hover:bg-amber-700">Place hold</button>
                </div>
            </div>
        </div>
    @endif
</div>
