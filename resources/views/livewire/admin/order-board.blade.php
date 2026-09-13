<div>
    <x-slot name="header">Order Board</x-slot>

    @php
        // Tailwind can't see dynamically-built class names, so map lane colours to full literals.
        $laneChrome = [
            'amber'  => ['head' => 'bg-amber-50 border-amber-200', 'dot' => 'bg-amber-400', 'count' => 'bg-amber-100 text-amber-700'],
            'sky'    => ['head' => 'bg-sky-50 border-sky-200', 'dot' => 'bg-sky-400', 'count' => 'bg-sky-100 text-sky-700'],
            'violet' => ['head' => 'bg-violet-50 border-violet-200', 'dot' => 'bg-violet-400', 'count' => 'bg-violet-100 text-violet-700'],
            'indigo' => ['head' => 'bg-indigo-50 border-indigo-200', 'dot' => 'bg-indigo-400', 'count' => 'bg-indigo-100 text-indigo-700'],
            'blue'   => ['head' => 'bg-blue-50 border-blue-200', 'dot' => 'bg-blue-400', 'count' => 'bg-blue-100 text-blue-700'],
            'teal'   => ['head' => 'bg-teal-50 border-teal-200', 'dot' => 'bg-teal-400', 'count' => 'bg-teal-100 text-teal-700'],
            'slate'  => ['head' => 'bg-slate-50 border-slate-200', 'dot' => 'bg-slate-400', 'count' => 'bg-slate-100 text-slate-700'],
        ];
    @endphp

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

    {{-- Filters --}}
    <div class="mb-6 grid grid-cols-1 md:grid-cols-6 gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search ref / Contro no. / patient"
               class="md:col-span-2 text-sm border-gray-300 rounded-lg" />

        <select wire:model.live="serviceLine" class="text-sm border-gray-300 rounded-lg">
            <option value="">All service lines</option>
            @foreach($this->serviceLines as $line)
                <option value="{{ $line }}">{{ $line }}</option>
            @endforeach
        </select>

        <select wire:model.live="assignee" class="text-sm border-gray-300 rounded-lg">
            <option value="">All doctors</option>
            @foreach($this->assignableDoctors as $doc)
                <option value="{{ $doc->id }}">{{ $doc->first_name }} {{ $doc->last_name }}</option>
            @endforeach
        </select>

        <input type="date" wire:model.live="dateFrom" class="text-sm border-gray-300 rounded-lg" />
        <input type="date" wire:model.live="dateTo" class="text-sm border-gray-300 rounded-lg" />
    </div>

    <div class="mb-4 flex items-center gap-3">
        <button wire:click="resetFilters" class="text-xs text-gray-500 hover:underline">Reset filters</button>
        <span wire:loading class="text-xs text-gray-400">Loading…</span>
    </div>

    {{-- Board --}}
    <div class="overflow-x-auto pb-4">
        <div class="flex gap-4 min-w-max">
            @foreach($laneDefinitions as $key => $lane)
                @php $chrome = $laneChrome[$lane['colour']] ?? $laneChrome['slate']; @endphp
                <div class="w-72 flex-shrink-0">
                    <div class="rounded-t-lg border px-3 py-2 flex items-center justify-between {{ $chrome['head'] }}">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full {{ $chrome['dot'] }}"></span>
                            <span class="text-sm font-semibold text-gray-700">{{ $lane['label'] }}</span>
                        </div>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $chrome['count'] }}">
                            {{ $this->laneCounts[$key] ?? 0 }}
                        </span>
                    </div>

                    <div class="border border-t-0 border-gray-200 rounded-b-lg bg-gray-50 p-2 space-y-2 min-h-[8rem]">
                        @forelse($this->lanes[$key] as $order)
                            <button type="button" wire:click="selectOrder({{ $order->id }})"
                                    wire:key="order-{{ $order->id }}"
                                    class="w-full text-left bg-white rounded-lg border border-gray-200 p-3 hover:border-indigo-300 hover:shadow-sm transition
                                           {{ $this->selectedOrderId === $order->id ? 'ring-2 ring-indigo-400' : '' }}">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs text-gray-500">{{ $order->reference }}</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">{{ $order->status }}</span>
                                </div>
                                <div class="mt-1 text-sm font-medium text-gray-800">
                                    {{ $order->patient?->first_name }} {{ $order->patient?->last_name ?: '—' }}
                                </div>
                                <div class="mt-1 flex items-center justify-between text-xs text-gray-500">
                                    <span>R{{ number_format($order->total_minor / 100, 2) }}</span>
                                    <span>{{ $order->ordered_at?->format('d M Y') ?? '—' }}</span>
                                </div>
                                @if($order->doctor)
                                    <div class="mt-1 text-[11px] text-gray-400">Dr {{ $order->doctor->last_name }}</div>
                                @endif
                            </button>
                        @empty
                            <p class="text-xs text-gray-400 text-center py-6">No orders.</p>
                        @endforelse

                        @if(($this->laneCounts[$key] ?? 0) > $this->lanes[$key]->count())
                            <p class="text-[11px] text-gray-400 text-center pt-1">
                                Showing {{ $this->lanes[$key]->count() }} of {{ $this->laneCounts[$key] }} — refine filters to see more.
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Detail drawer --}}
    @if($this->selectedOrder)
        @php $order = $this->selectedOrder; @endphp
        <div class="fixed inset-0 z-40 flex justify-end" wire:key="drawer-{{ $order->id }}">
            <div class="absolute inset-0 bg-black/30" wire:click="closeDrawer"></div>

            <div class="relative z-50 w-full max-w-xl bg-white h-full shadow-xl overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                    <div>
                        <div class="font-mono text-sm text-gray-500">{{ $order->reference }}</div>
                        <div class="text-lg font-semibold text-gray-800">
                            {{ $order->patient?->first_name }} {{ $order->patient?->last_name }}
                        </div>
                    </div>
                    <button wire:click="closeDrawer" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>

                <div class="px-6 py-4 space-y-6">
                    {{-- Summary --}}
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-400">Status</span><div class="font-medium">{{ $order->status }}</div></div>
                        <div><span class="text-gray-400">Total</span><div class="font-medium">R{{ number_format($order->total_minor / 100, 2) }}</div></div>
                        <div><span class="text-gray-400">Service line</span><div class="font-medium">{{ $order->service_category ?? '—' }}</div></div>
                        <div><span class="text-gray-400">Doctor</span><div class="font-medium">{{ $order->doctor ? 'Dr ' . $order->doctor->last_name : '—' }}</div></div>
                        <div><span class="text-gray-400">Ordered</span><div class="font-medium">{{ $order->ordered_at?->format('d M Y H:i') ?? '—' }}</div></div>
                        <div><span class="text-gray-400">Contro no.</span><div class="font-mono text-xs">{{ $order->contro_order_number ?? '—' }}</div></div>
                    </div>

                    {{-- Transition control (data-driven; only legal moves) --}}
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Change status</h4>
                        @if(count($this->allowedNextStatuses) === 0)
                            <p class="text-xs text-gray-400">No transitions available from <strong>{{ $order->status }}</strong>.</p>
                        @else
                            <div class="space-y-2">
                                <select wire:model="transitionTo" class="w-full text-sm border-gray-300 rounded-lg">
                                    <option value="">Select target status…</option>
                                    @foreach($this->allowedNextStatuses as $status)
                                        <option value="{{ $status }}">{{ $status }}</option>
                                    @endforeach
                                </select>
                                @error('transitionTo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                <textarea wire:model="transitionNotes" rows="2" placeholder="Notes (optional)"
                                          class="w-full text-sm border-gray-300 rounded-lg"></textarea>
                                <button wire:click="applyTransition" wire:loading.attr="disabled"
                                        class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40">
                                    Apply transition
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- Line items --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Items</h4>
                        @if($order->items->isEmpty())
                            <p class="text-xs text-gray-400">No line items.</p>
                        @else
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 text-gray-500">
                                        <tr>
                                            <th class="text-left px-3 py-2 font-medium">Description</th>
                                            <th class="text-right px-3 py-2 font-medium">Qty</th>
                                            <th class="text-right px-3 py-2 font-medium">Line total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($order->items as $item)
                                            <tr>
                                                <td class="px-3 py-2">{{ $item->description }}</td>
                                                <td class="px-3 py-2 text-right">{{ $item->quantity }}</td>
                                                <td class="px-3 py-2 text-right">R{{ number_format($item->line_total_minor / 100, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- Linked payments --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Payments</h4>
                        @if($order->payments->isEmpty())
                            <p class="text-xs text-gray-400">No payments linked.</p>
                        @else
                            <div class="space-y-1">
                                @foreach($order->payments as $payment)
                                    <div class="flex items-center justify-between text-sm border border-gray-100 rounded-lg px-3 py-2">
                                        <span class="font-mono text-xs text-gray-500">{{ $payment->reference }}</span>
                                        <span>R{{ number_format($payment->amount / 100, 2) }}</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $payment->status }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Linked prescriptions (by shared script ref) --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Prescriptions</h4>
                        @if($order->prescriptions->isEmpty())
                            <p class="text-xs text-gray-400">No prescription linked (script ref: {{ $order->pharmacy_script_ref ?? '—' }}).</p>
                        @else
                            <div class="space-y-1">
                                @foreach($order->prescriptions as $rx)
                                    <div class="flex items-center justify-between text-sm border border-gray-100 rounded-lg px-3 py-2">
                                        <span class="font-mono text-xs text-gray-500">{{ $rx->reference }}</span>
                                        <span class="text-xs text-gray-500">{{ $rx->pharmacy_script_ref }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Immutable status timeline --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Status history</h4>
                        @if($order->statusHistory->isEmpty())
                            <p class="text-xs text-gray-400">No history recorded.</p>
                        @else
                            <ol class="relative border-l border-gray-200 ml-2 space-y-4">
                                @foreach($order->statusHistory as $h)
                                    <li class="ml-4" wire:key="hist-{{ $h->id }}">
                                        <span class="absolute -left-1.5 w-3 h-3 rounded-full bg-indigo-400 border border-white"></span>
                                        <div class="text-sm">
                                            <span class="text-gray-400">{{ $h->from_status ?? 'initial' }}</span>
                                            <span class="mx-1 text-gray-300">→</span>
                                            <span class="font-medium text-gray-800">{{ $h->to_status }}</span>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            {{ $h->trigger_type }} · {{ $h->occurred_at?->format('d M Y H:i') }}
                                            @if($h->triggered_by) · {{ $h->triggered_by }} @endif
                                        </div>
                                        @if($h->notes)
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $h->notes }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
