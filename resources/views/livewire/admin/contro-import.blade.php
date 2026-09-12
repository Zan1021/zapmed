<div>
    <x-slot name="header">Contro Import</x-slot>

    @if(session('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
            {{ session('message') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Configuration banner --}}
    @unless($this->isConfigured)
        <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
            Contro API is not configured yet (base URL + service credentials pending from Craig). You can
            still <strong>reconcile</strong> and review <strong>quarantine</strong> for any already-staged
            data, but <strong>pull / backfill</strong> are disabled until credentials are set.
        </div>
    @endunless

    {{-- Actions --}}
    <div class="mb-6 flex flex-wrap gap-3">
        <button wire:click="backfill" wire:loading.attr="disabled"
                @unless($this->isConfigured) disabled @endunless
                class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed">
            Backfill (all entities)
        </button>
        <button wire:click="pullAll" wire:loading.attr="disabled"
                @unless($this->isConfigured) disabled @endunless
                class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed">
            Delta pull
        </button>
        <button wire:click="reconcile" wire:loading.attr="disabled"
                class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40">
            Reconcile staging → canonical
        </button>
        <span wire:loading class="self-center text-sm text-gray-500">Working…</span>
    </div>

    {{-- Parity report --}}
    <div class="mb-8">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-gray-700">Parity report</h3>
            @if($this->parityClean)
                <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-700">Clean — all accounted for</span>
            @else
                <span class="text-xs px-2 py-1 rounded-full bg-red-100 text-red-700">Unaccounted rows — investigate</span>
            @endif
        </div>
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-2 font-medium">Entity</th>
                        <th class="text-right px-4 py-2 font-medium">Staged</th>
                        <th class="text-right px-4 py-2 font-medium">Reconciled</th>
                        <th class="text-right px-4 py-2 font-medium">Quarantined</th>
                        <th class="text-right px-4 py-2 font-medium">Unaccounted</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($this->parity as $entity => $c)
                        <tr class="{{ $c['unaccounted'] > 0 ? 'bg-red-50' : '' }}">
                            <td class="px-4 py-2 font-mono text-gray-700">{{ $entity }}</td>
                            <td class="px-4 py-2 text-right">{{ $c['staged'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $c['reconciled'] }}</td>
                            <td class="px-4 py-2 text-right">{{ $c['quarantined'] }}</td>
                            <td class="px-4 py-2 text-right font-semibold {{ $c['unaccounted'] > 0 ? 'text-red-600' : 'text-gray-400' }}">
                                {{ $c['unaccounted'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent sync runs --}}
    <div class="mb-8">
        <h3 class="text-sm font-semibold text-gray-700 mb-2">Recent sync runs</h3>
        @if($runs->isEmpty())
            <p class="text-sm text-gray-400">No sync runs yet.</p>
        @else
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="text-left px-4 py-2 font-medium">Entity</th>
                            <th class="text-left px-4 py-2 font-medium">Trigger</th>
                            <th class="text-left px-4 py-2 font-medium">Status</th>
                            <th class="text-right px-4 py-2 font-medium">Pulled</th>
                            <th class="text-right px-4 py-2 font-medium">Upserted</th>
                            <th class="text-left px-4 py-2 font-medium">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($runs as $run)
                            <tr>
                                <td class="px-4 py-2 font-mono text-gray-700">{{ $run->entity_set ?? 'all' }}</td>
                                <td class="px-4 py-2">{{ $run->trigger }}</td>
                                <td class="px-4 py-2">
                                    <span class="text-xs px-2 py-0.5 rounded-full
                                        {{ $run->status === 'completed' ? 'bg-green-100 text-green-700' : ($run->status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600') }}">
                                        {{ $run->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right">{{ $run->rows_pulled }}</td>
                                <td class="px-4 py-2 text-right">{{ $run->rows_upserted }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $run->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Quarantine queue --}}
    <div>
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-gray-700">Quarantine queue</h3>
            <select wire:model.live="quarantineFilter" class="text-sm border-gray-300 rounded-lg">
                <option value="open">Open only</option>
                <option value="all">All</option>
            </select>
        </div>
        @if($quarantine->isEmpty())
            <p class="text-sm text-gray-400">Nothing quarantined. 🎉</p>
        @else
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="text-left px-4 py-2 font-medium">Entity</th>
                            <th class="text-left px-4 py-2 font-medium">Upstream ID</th>
                            <th class="text-left px-4 py-2 font-medium">Reason</th>
                            <th class="text-left px-4 py-2 font-medium">Detail</th>
                            <th class="text-left px-4 py-2 font-medium">Status</th>
                            <th class="text-right px-4 py-2 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($quarantine as $q)
                            <tr>
                                <td class="px-4 py-2 font-mono text-gray-700">{{ $q->entity_set }}</td>
                                <td class="px-4 py-2 font-mono text-gray-500">{{ $q->upstream_id ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $q->reason }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ Str::limit($q->detail, 60) }}</td>
                                <td class="px-4 py-2">{{ $q->status }}</td>
                                <td class="px-4 py-2 text-right space-x-2">
                                    @if($q->status === 'open')
                                        <button wire:click="resolveQuarantine({{ $q->id }})"
                                                class="text-xs text-green-700 hover:underline">Resolve</button>
                                        <button wire:click="ignoreQuarantine({{ $q->id }})"
                                                class="text-xs text-gray-500 hover:underline">Ignore</button>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $quarantine->links() }}</div>
        @endif
    </div>
</div>
