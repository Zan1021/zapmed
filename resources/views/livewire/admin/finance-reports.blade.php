<div>
    <x-slot name="header">Finance</x-slot>

    @php
        $summary = $this->revenueSummary;
        $kindLabels = [
            'cash_collected' => 'Cash collected',
            'recognised' => 'Recognised',
            'pipeline' => 'Pipeline',
            'refund' => 'Refund',
            'chargeback' => 'Chargeback',
            'discount' => 'Discount',
        ];
    @endphp

    @if(session('message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('message') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ session('error') }}</div>
    @endif

    {{-- Window + export --}}
    <div class="mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs text-gray-500 mb-1">From</label>
            <input type="date" wire:model.live="dateFrom" class="text-sm border-gray-300 rounded-lg" />
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">To</label>
            <input type="date" wire:model.live="dateTo" class="text-sm border-gray-300 rounded-lg" />
        </div>
        <button wire:click="exportRevenueCsv"
                class="ml-auto px-4 py-2 rounded-lg text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700">
            Export revenue CSV
        </button>
    </div>

    {{-- Revenue summary by kind --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-8">
        @foreach($kindLabels as $key => $label)
            @php $row = $summary[$key] ?? ['amount_cents' => 0, 'entry_count' => 0]; @endphp
            <div class="bg-white rounded-lg border border-gray-200 p-3">
                <div class="text-[11px] text-gray-400 uppercase tracking-wide">{{ $label }}</div>
                <div class="mt-1 text-lg font-semibold {{ $row['amount_cents'] < 0 ? 'text-red-600' : 'text-gray-800' }}">
                    R{{ number_format($row['amount_cents'] / 100, 2) }}
                </div>
                <div class="text-[11px] text-gray-400">{{ $row['entry_count'] }} entr{{ $row['entry_count'] === 1 ? 'y' : 'ies' }}</div>
            </div>
        @endforeach
    </div>

    {{-- Daily series --}}
    <div class="bg-white rounded-lg border border-gray-200 p-5 mb-8">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Daily cash vs recognised</h3>
        @if(count($this->revenueSeries) === 0)
            <p class="text-xs text-gray-400">No revenue entries in this window.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="text-left px-3 py-2 font-medium">Date</th>
                            <th class="text-right px-3 py-2 font-medium">Cash collected</th>
                            <th class="text-right px-3 py-2 font-medium">Recognised</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($this->revenueSeries as $point)
                            <tr>
                                <td class="px-3 py-2">{{ $point['date'] }}</td>
                                <td class="px-3 py-2 text-right">R{{ number_format($point['cash_cents'] / 100, 2) }}</td>
                                <td class="px-3 py-2 text-right">R{{ number_format($point['recognised_cents'] / 100, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Reconciliation worklist --}}
    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">PayFast ↔ pharmacy reconciliation</h3>
        @if($this->reconWorklist->isEmpty())
            <p class="text-xs text-gray-400">Nothing to reconcile — all clear.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="text-left px-3 py-2 font-medium">Order</th>
                            <th class="text-left px-3 py-2 font-medium">Invoice</th>
                            <th class="text-right px-3 py-2 font-medium">Pharmacy</th>
                            <th class="text-right px-3 py-2 font-medium">Payment</th>
                            <th class="text-right px-3 py-2 font-medium">Delta</th>
                            <th class="text-left px-3 py-2 font-medium">Status</th>
                            <th class="text-right px-3 py-2 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($this->reconWorklist as $recon)
                            <tr wire:key="recon-{{ $recon->id }}">
                                <td class="px-3 py-2 font-mono text-xs">{{ $recon->order?->reference ?? $recon->order_id }}</td>
                                <td class="px-3 py-2 text-xs">{{ $recon->pharmacy_invoice_ref ?? '—' }}</td>
                                <td class="px-3 py-2 text-right">{{ $recon->pharmacy_amount_cents !== null ? 'R' . number_format($recon->pharmacy_amount_cents / 100, 2) : '—' }}</td>
                                <td class="px-3 py-2 text-right">{{ $recon->payment_amount_cents !== null ? 'R' . number_format($recon->payment_amount_cents / 100, 2) : '—' }}</td>
                                <td class="px-3 py-2 text-right {{ $recon->delta_cents !== 0 ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                    R{{ number_format($recon->delta_cents / 100, 2) }}
                                </td>
                                <td class="px-3 py-2">
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $recon->status->label() }}</span>
                                </td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">
                                    <button wire:click="matchRecon({{ $recon->id }})" class="text-xs text-emerald-600 hover:underline">Match</button>
                                    <button wire:click="openAction({{ $recon->id }}, 'dispute')" class="ml-2 text-xs text-amber-600 hover:underline">Dispute</button>
                                    <button wire:click="openAction({{ $recon->id }}, 'write_off')" class="ml-2 text-xs text-red-600 hover:underline">Write off</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Action modal (dispute / write-off) --}}
    @if($actionReconId !== null)
        <div class="fixed inset-0 z-40 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/30" wire:click="cancelAction"></div>
            <div class="relative z-50 w-full max-w-md bg-white rounded-lg shadow-xl p-6">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">
                    {{ $actionType === 'write_off' ? 'Write off reconciliation entry' : 'Dispute reconciliation entry' }}
                </h4>
                <textarea wire:model="actionNote" rows="3"
                          placeholder="{{ $actionType === 'write_off' ? 'Reason for write-off (required)' : 'Dispute notes (required)' }}"
                          class="w-full text-sm border-gray-300 rounded-lg"></textarea>
                @error('actionNote') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="cancelAction" class="px-3 py-2 text-sm text-gray-500 hover:underline">Cancel</button>
                    <button wire:click="confirmAction"
                            class="px-4 py-2 rounded-lg text-sm font-medium text-white {{ $actionType === 'write_off' ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-600 hover:bg-amber-700' }}">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
