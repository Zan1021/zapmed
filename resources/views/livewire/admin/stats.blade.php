<div>
    <x-slot name="header">Stats</x-slot>

    @php
        $rand = fn ($cents) => 'R' . number_format(((int) $cents) / 100, 2);
        $pct = fn ($f) => number_format(((float) $f) * 100, 1) . '%';
        $ov = $this->overview;
        $acq = $this->acquisition;
        $subs = $this->subscriptions;
        $ord = $this->orders;
        $fin = $this->finance;
        $crm = $this->crm;
    @endphp

    {{-- Header: period switcher + cross-links --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500">Period:</span>
            @foreach ($periods as $p)
                <button wire:click="setPeriod('{{ $p }}')"
                    class="px-3 py-1 rounded-full text-xs font-medium capitalize {{ $period === $p ? 'bg-zapmed-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                    {{ $p }}
                </button>
            @endforeach
            <span wire:loading class="text-xs text-gray-400">Loading…</span>
        </div>
        <div class="flex items-center gap-3 text-xs">
            <a href="{{ route('admin.analytics') }}" class="text-zapmed-700 hover:underline">Business analytics →</a>
            <a href="{{ route('admin.crm-analytics') }}" class="text-zapmed-700 hover:underline">CRM acquisition →</a>
        </div>
    </div>

    {{-- ── Business overview ─────────────────────────────────────────── --}}
    <h2 class="text-lg font-semibold text-gray-900 mb-3">Business overview</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Revenue ({{ $period }})</p>
            <p class="text-2xl font-bold text-gray-900">{{ $rand($ov['revenue']['total'] ?? 0) }}</p>
            <p class="text-xs text-gray-400">{{ $ov['revenue']['count'] ?? 0 }} payments · avg {{ $rand($ov['revenue']['average_per_transaction'] ?? 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Platform profit</p>
            <p class="text-2xl font-bold text-gray-900">{{ $rand($ov['profit']['platform_profit'] ?? 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Patients (total)</p>
            <p class="text-2xl font-bold text-gray-900">{{ $ov['patients']['total'] ?? 0 }}</p>
            <p class="text-xs text-gray-400">+{{ $ov['patients']['this_month'] ?? 0 }} this month</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Consultations</p>
            <p class="text-2xl font-bold text-gray-900">{{ $ov['consultations']['completed'] ?? 0 }}</p>
            <p class="text-xs text-gray-400">{{ $ov['consultations']['no_show_rate'] ?? 0 }}% no-show</p>
        </div>
    </div>

    {{-- ── Acquisition funnel ────────────────────────────────────────── --}}
    <h2 class="text-lg font-semibold text-gray-900 mb-3">Acquisition</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500 mb-2">Funnel counts</p>
            <table class="w-full text-sm">
                @foreach ($acq['funnel_counts'] ?? [] as $kind => $count)
                    <tr class="border-b border-gray-50 last:border-0">
                        <td class="py-1 text-gray-600">{{ ucwords(str_replace('_', ' ', $kind)) }}</td>
                        <td class="py-1 text-right font-medium">{{ $count }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500 mb-2">Headline KPIs</p>
            <p class="text-sm text-gray-600">CAC: <span class="font-medium">{{ isset($acq['cac_cents']) ? $rand($acq['cac_cents']) : 'n/a' }}</span></p>
            <p class="text-xs text-gray-400 mt-2">Step conversions</p>
            <table class="w-full text-xs">
                @foreach ($acq['conversion_rates'] ?? [] as $step => $rate)
                    <tr><td class="py-0.5 text-gray-500">{{ str_replace('→', ' → ', $step) }}</td><td class="py-0.5 text-right">{{ $pct($rate) }}</td></tr>
                @endforeach
            </table>
        </div>
    </div>

    {{-- ── Subscriptions ─────────────────────────────────────────────── --}}
    <h2 class="text-lg font-semibold text-gray-900 mb-3">Subscriptions</h2>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Active</p>
            <p class="text-2xl font-bold text-gray-900">{{ $subs['active'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">MRR</p>
            <p class="text-2xl font-bold text-gray-900">{{ $rand($subs['mrr_cents'] ?? 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Churn rate</p>
            <p class="text-2xl font-bold text-gray-900">{{ $pct($subs['churn_rate'] ?? 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Churned</p>
            <p class="text-2xl font-bold text-gray-900">{{ $subs['churned'] ?? 0 }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Cycles ok / fail</p>
            <p class="text-2xl font-bold text-gray-900">{{ $subs['cycles_fulfilled'] ?? 0 }} / {{ $subs['cycles_failed'] ?? 0 }}</p>
        </div>
    </div>

    {{-- ── Orders ────────────────────────────────────────────────────── --}}
    <h2 class="text-lg font-semibold text-gray-900 mb-3">Orders <span class="text-sm font-normal text-gray-400">({{ $ord['total'] ?? 0 }} total)</span></h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500 mb-2">By lane</p>
            <table class="w-full text-sm">
                @foreach ($ord['by_lane'] ?? [] as $lane)
                    <tr class="border-b border-gray-50 last:border-0">
                        <td class="py-1 text-gray-600">{{ $lane['label'] }}</td>
                        <td class="py-1 text-right font-medium">{{ $lane['count'] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500 mb-2">Fulfilment funnel</p>
            <table class="w-full text-sm">
                @foreach ($ord['fulfilment_funnel'] ?? [] as $stage => $count)
                    <tr class="border-b border-gray-50 last:border-0">
                        <td class="py-1 text-gray-600">{{ ucwords(str_replace('_', ' ', $stage)) }}</td>
                        <td class="py-1 text-right font-medium">{{ $count }}</td>
                    </tr>
                @endforeach
            </table>
            <p class="text-xs text-amber-600 mt-2">{{ $ord['pending_payment']['aged_over_threshold'] ?? 0 }} orders stuck awaiting payment > {{ $ord['pending_payment']['threshold_hours'] ?? 24 }}h</p>
        </div>
    </div>

    {{-- ── Finance ───────────────────────────────────────────────────── --}}
    <h2 class="text-lg font-semibold text-gray-900 mb-3">Finance</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500 mb-2">Revenue by service line</p>
            <table class="w-full text-sm">
                @forelse ($fin['by_service_line'] ?? [] as $line => $row)
                    <tr class="border-b border-gray-50 last:border-0">
                        <td class="py-1 text-gray-600">{{ ucwords(str_replace('-', ' ', $line)) }}</td>
                        <td class="py-1 text-right font-medium">{{ $rand($row['amount_cents']) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-1 text-gray-400 text-sm">No revenue entries yet.</td></tr>
                @endforelse
            </table>
            <p class="text-sm text-gray-600 mt-2">Net: <span class="font-medium">{{ $rand($fin['net_cents'] ?? 0) }}</span> · Refunds: <span class="font-medium">{{ $rand($fin['refunds_cents'] ?? 0) }}</span></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500 mb-2">Reconciliation</p>
            <table class="w-full text-sm">
                @forelse ($fin['recon_by_status'] ?? [] as $status => $count)
                    <tr class="border-b border-gray-50 last:border-0">
                        <td class="py-1 text-gray-600 capitalize">{{ str_replace('_', ' ', $status) }}</td>
                        <td class="py-1 text-right font-medium">{{ $count }}</td>
                    </tr>
                @empty
                    <tr><td class="py-1 text-gray-400 text-sm">No reconciliation entries.</td></tr>
                @endforelse
            </table>
            <p class="text-xs text-amber-600 mt-2">Outstanding: {{ $rand($fin['outstanding']['pending_amount_cents'] ?? 0) }} ({{ $fin['outstanding']['aged_over_threshold'] ?? 0 }} aged)</p>
        </div>
    </div>

    {{-- ── CRM funnel ────────────────────────────────────────────────── --}}
    <h2 class="text-lg font-semibold text-gray-900 mb-3">CRM funnel <span class="text-sm font-normal text-gray-400">({{ $crm['total_leads'] ?? 0 }} leads)</span></h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500 mb-2">Leads by stage</p>
            <table class="w-full text-sm">
                @foreach ($crm['by_stage'] ?? [] as $stage => $count)
                    <tr class="border-b border-gray-50 last:border-0">
                        <td class="py-1 text-gray-600">{{ ucwords(str_replace('_', ' ', $stage)) }}</td>
                        <td class="py-1 text-right font-medium">{{ $count }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500 mb-2">Risk band</p>
            <table class="w-full text-sm">
                @foreach ($crm['by_risk_band'] ?? [] as $band => $count)
                    <tr class="border-b border-gray-50 last:border-0">
                        <td class="py-1 text-gray-600 capitalize">{{ $band }}</td>
                        <td class="py-1 text-right font-medium">{{ $count }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500 mb-2">Open flags &amp; risk</p>
            <table class="w-full text-sm">
                @forelse ($crm['open_flags_by_kind'] ?? [] as $kind => $count)
                    <tr class="border-b border-gray-50 last:border-0">
                        <td class="py-1 text-gray-600 capitalize">{{ str_replace('_', ' ', $kind) }}</td>
                        <td class="py-1 text-right font-medium">{{ $count }}</td>
                    </tr>
                @empty
                    <tr><td class="py-1 text-gray-400 text-sm">No open flags.</td></tr>
                @endforelse
            </table>
            <p class="text-xs text-gray-500 mt-2">Dropped off: {{ $crm['dropped_off'] ?? 0 }} · Cold (>{{ $crm['cold_threshold_days'] ?? 14 }}d): {{ $crm['cold'] ?? 0 }}</p>
        </div>
    </div>

    <p class="text-xs text-gray-400">Money figures reflect recorded ledger/payment data; some values may read low in pre-launch (medication seed pricing + payment stub — see UAT notes).</p>
</div>
