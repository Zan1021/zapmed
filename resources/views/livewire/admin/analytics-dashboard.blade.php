<div>
    <x-slot name="header">CRM Analytics</x-slot>

    @php
        $s = $this->summary;
        $funnelLabels = [
            'page_view' => 'Page views',
            'sign_up_started' => 'Sign-up started',
            'sign_up_complete' => 'Sign-up complete',
            'intake_started' => 'Intake started',
            'intake_complete' => 'Intake complete',
            'consult_booked' => 'Consult booked',
            'consult_complete' => 'Consult complete',
            'first_payment' => 'First payment',
            'subscribed' => 'Subscribed',
        ];
        $maxCount = max(1, ...array_values($s['funnel'] ?? [1]));
    @endphp

    {{-- Date window --}}
    <div class="mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs text-gray-500 mb-1">From</label>
            <input type="date" wire:model.live="dateFrom" class="text-sm border-gray-300 rounded-lg" />
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">To</label>
            <input type="date" wire:model.live="dateTo" class="text-sm border-gray-300 rounded-lg" />
        </div>
        <span wire:loading class="text-xs text-gray-400 pb-2">Loading…</span>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="text-xs text-gray-400 uppercase tracking-wide">New patients</div>
            <div class="mt-1 text-2xl font-semibold text-gray-800">{{ number_format($s['new_patients']) }}</div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="text-xs text-gray-400 uppercase tracking-wide">Signup → first payment</div>
            <div class="mt-1 text-2xl font-semibold text-gray-800">{{ number_format($s['conversion_to_first_payment'] * 100, 1) }}%</div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="text-xs text-gray-400 uppercase tracking-wide">Signup → consult complete</div>
            <div class="mt-1 text-2xl font-semibold text-gray-800">{{ number_format($s['activation_to_consult_complete'] * 100, 1) }}%</div>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="text-xs text-gray-400 uppercase tracking-wide">Blended CAC</div>
            <div class="mt-1 text-2xl font-semibold text-gray-800">
                {{ $s['cac_cents'] !== null ? 'R' . number_format($s['cac_cents'] / 100, 2) : '—' }}
            </div>
        </div>
    </div>

    {{-- Funnel --}}
    <div class="bg-white rounded-lg border border-gray-200 p-5 mb-8">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Acquisition funnel</h3>
        <div class="space-y-2">
            @foreach($funnelLabels as $key => $label)
                @php $count = $s['funnel'][$key] ?? 0; @endphp
                <div class="flex items-center gap-3">
                    <div class="w-40 text-xs text-gray-500 flex-shrink-0">{{ $label }}</div>
                    <div class="flex-1 bg-gray-100 rounded-full h-5 overflow-hidden">
                        <div class="h-5 bg-indigo-400 rounded-full" style="width: {{ max(2, round($count / $maxCount * 100)) }}%"></div>
                    </div>
                    <div class="w-16 text-right text-sm font-medium text-gray-700">{{ number_format($count) }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Step conversion --}}
    <div class="bg-white rounded-lg border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Step-to-step conversion</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach($this->conversionRates as $step => $rate)
                <div class="flex items-center justify-between text-sm border border-gray-100 rounded-lg px-3 py-2">
                    <span class="text-gray-500">{{ str_replace('_', ' ', $step) }}</span>
                    <span class="font-medium text-gray-800">{{ number_format($rate * 100, 1) }}%</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
