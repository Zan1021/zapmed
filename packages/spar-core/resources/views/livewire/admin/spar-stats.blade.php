<div>
    <x-slot name="header">SPAR Insights</x-slot>

    @php
        $scopeLabel = match($data['role']) {
            'super_admin' => 'Platform-wide',
            'group_admin' => 'Your group',
            default => 'Your pharmacy',
        };
        $maxDue = max(1, collect($data['monthly_trend'])->max('due') ?: 1);
        $adherence = $data['adherence']['rate'];
        $adherenceColor = $adherence >= 80 ? 'green' : ($adherence >= 60 ? 'amber' : 'red');
    @endphp

    <!-- Header + filters -->
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide" style="color: #15803d;">Analytics &amp; reporting</p>
            <h1 class="mt-1 text-3xl font-bold text-gray-900">Insights</h1>
            <div class="mt-2 flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800">{{ $scopeLabel }} view</span>
                <span class="text-xs text-gray-400">Last {{ $data['period_days'] }} days</span>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="period"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-green-500 focus:ring-green-500">
                <option value="7">Last 7 days</option>
                <option value="30">Last 30 days</option>
                <option value="90">Last 90 days</option>
                <option value="180">Last 6 months</option>
                <option value="365">Last year</option>
            </select>
            @if($this->pharmacies->count() > 1)
                <select wire:model.live="pharmacyFilter"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-green-500 focus:ring-green-500">
                    <option value="">All pharmacies</option>
                    @foreach($this->pharmacies as $pharmacy)
                        <option value="{{ $pharmacy->id }}">{{ $pharmacy->name }}</option>
                    @endforeach
                </select>
            @endif
            <button type="button" wire:click="downloadCsv"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                    style="background-color: #15803d;">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M12 3v13m0 0l-4-4m4 4l4-4"/></svg>
                Download CSV
            </button>
        </div>
    </div>

    <!-- Hero KPI cards -->
    <div class="mb-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Adherence -->
        <div class="relative overflow-hidden rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="relative">
                <div class="mb-3 flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100">
                        <svg class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Adherence</span>
                </div>
                <p class="text-4xl font-black text-{{ $adherenceColor }}-600">{{ $adherence }}<span class="text-xl">%</span></p>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full bg-{{ $adherenceColor }}-500 transition-all duration-700" style="width: {{ $adherence }}%"></div>
                </div>
                <p class="mt-2 text-xs text-gray-500">{{ $data['adherence']['on_time'] }} of {{ $data['adherence']['total_due'] }} on time</p>
            </div>
        </div>

        <!-- Renewal rate -->
        <div class="relative overflow-hidden rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="mb-3 flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100">
                    <svg class="h-4 w-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </div>
                <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Renewal rate</span>
            </div>
            <p class="text-4xl font-black text-purple-600">{{ $data['renewals']['rate'] }}<span class="text-xl">%</span></p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-purple-500 transition-all duration-700" style="width: {{ $data['renewals']['rate'] }}%"></div>
            </div>
            <p class="mt-2 text-xs text-gray-500">{{ $data['renewals']['renewed'] }} renewed, {{ $data['renewals']['due'] }} pending</p>
        </div>

        <!-- Opt-in rate -->
        <div class="relative overflow-hidden rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="mb-3 flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100">
                    <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                </div>
                <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Opt-in rate</span>
            </div>
            <p class="text-4xl font-black text-blue-600">{{ $data['patients']['opt_in_rate'] }}<span class="text-xl">%</span></p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-blue-500 transition-all duration-700" style="width: {{ $data['patients']['opt_in_rate'] }}%"></div>
            </div>
            <p class="mt-2 text-xs text-gray-500">{{ $data['patients']['consented'] }} of {{ $data['patients']['total'] }} patients</p>
        </div>

        <!-- ZapMed conversion -->
        <div class="relative overflow-hidden rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="mb-3 flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <span class="text-xs font-medium uppercase tracking-wide text-gray-500">ZapMed consults</span>
            </div>
            <p class="text-4xl font-black text-emerald-600">{{ $data['renewals']['zapmed_conversion'] }}<span class="text-xl">%</span></p>
            <div class="mt-3 flex items-center gap-4">
                <div class="flex items-center gap-1"><div class="h-2 w-2 rounded-full bg-emerald-500"></div><span class="text-xs text-gray-500">ZapMed: {{ $data['renewals']['via_zapmed'] }}</span></div>
                <div class="flex items-center gap-1"><div class="h-2 w-2 rounded-full bg-gray-300"></div><span class="text-xs text-gray-500">GP: {{ $data['renewals']['via_primary_doctor'] }}</span></div>
            </div>
        </div>
    </div>

    <!-- Charts row: trend + renewal donut -->
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Monthly adherence trend -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-2">
            <div class="mb-6 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">Monthly adherence trend</h3>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1"><div class="h-3 w-3 rounded bg-green-500"></div><span class="text-xs text-gray-500">Completed</span></div>
                    <div class="flex items-center gap-1"><div class="h-3 w-3 rounded bg-gray-200"></div><span class="text-xs text-gray-500">Due</span></div>
                </div>
            </div>
            <div class="flex h-48 items-end gap-4" x-data="{ shown: false }" x-intersect="shown = true">
                @foreach($data['monthly_trend'] as $month)
                    @php
                        $duePct = $month['due'] > 0 ? min(100, ($month['due'] / $maxDue) * 100) : 0;
                        $donePct = $month['due'] > 0 ? min(100, ($month['completed'] / $maxDue) * 100) : 0;
                        $barColor = $month['rate'] >= 80 ? 'green' : ($month['rate'] >= 60 ? 'amber' : 'red');
                    @endphp
                    <div class="flex flex-1 flex-col items-center gap-2">
                        <div class="relative flex h-40 w-full flex-col items-center justify-end">
                            <div class="absolute bottom-0 w-full rounded-t-lg bg-gray-100 transition-all duration-700"
                                 :style="shown ? 'height: {{ $duePct }}%' : 'height: 0%'"></div>
                            <div class="absolute bottom-0 w-3/4 rounded-t-lg bg-{{ $barColor }}-500 transition-all delay-300 duration-1000"
                                 :style="shown ? 'height: {{ $donePct }}%' : 'height: 0%'"></div>
                        </div>
                        <span class="text-xs font-medium text-gray-500">{{ \Illuminate\Support\Str::before($month['label'], ' ') }}</span>
                        <span class="text-xs font-bold text-{{ $barColor }}-600">{{ $month['rate'] }}%</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Renewal route donut -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 class="mb-6 text-lg font-bold text-gray-900">Renewal route</h3>
            @php
                $zapmedPct = $data['renewals']['zapmed_conversion'];
                $gpPct = 100 - $zapmedPct;
                $circ = 2 * 3.14159 * 45;
                $zapmedDash = ($zapmedPct / 100) * $circ;
                $gpDash = ($gpPct / 100) * $circ;
            @endphp
            <div class="mb-6 flex items-center justify-center">
                <div class="relative" x-data="{ shown: false }" x-intersect="shown = true">
                    <svg class="h-40 w-40 -rotate-90 transform" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#f3f4f6" stroke-width="10"/>
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#d1d5db" stroke-width="10"
                                :stroke-dasharray="shown ? '{{ $gpDash }} {{ $circ }}' : '0 {{ $circ }}'"
                                stroke-dashoffset="0" class="transition-all duration-1000"/>
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#10b981" stroke-width="10"
                                :stroke-dasharray="shown ? '{{ $zapmedDash }} {{ $circ }}' : '0 {{ $circ }}'"
                                stroke-dashoffset="-{{ $gpDash }}" class="transition-all delay-500 duration-1000"/>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="text-center">
                            <p class="text-2xl font-black text-gray-900">{{ $data['renewals']['renewed'] + $data['renewals']['due'] }}</p>
                            <p class="text-xs text-gray-500">Total</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2"><div class="h-3 w-3 rounded-full bg-emerald-500"></div><span class="text-sm text-gray-600">ZapMed consultation</span></div>
                    <span class="text-sm font-bold text-gray-900">{{ $data['renewals']['via_zapmed'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2"><div class="h-3 w-3 rounded-full bg-gray-300"></div><span class="text-sm text-gray-600">Primary doctor</span></div>
                    <span class="text-sm font-bold text-gray-900">{{ $data['renewals']['via_primary_doctor'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2"><div class="h-3 w-3 rounded-full bg-amber-400"></div><span class="text-sm text-gray-600">Pending renewal</span></div>
                    <span class="text-sm font-bold text-gray-900">{{ $data['renewals']['due'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Onboarding funnel + Consent + Orders -->
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Onboarding funnel -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-bold text-gray-900">Onboarding funnel</h3>
            @php
                $funnel = $data['onboarding_funnel'];
                $funnelTotal = max(1, $funnel['total']);
            @endphp
            @foreach(['awaiting_contact' => ['Awaiting contact', 'gray'], 'pending_consent' => ['Pending consent', 'amber'], 'active' => ['Active', 'green'], 'opted_out' => ['Opted out', 'red']] as $key => [$label, $c])
                <div class="mb-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">{{ $label }}</span>
                        <span class="font-medium text-gray-900">{{ $funnel[$key] }}</span>
                    </div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-{{ $c }}-500" style="width: {{ ($funnel[$key] / $funnelTotal) * 100 }}%"></div>
                    </div>
                </div>
            @endforeach
            <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 text-sm">
                <span class="text-gray-600">Activation rate</span>
                <span class="font-semibold" style="color: #15803d;">{{ $funnel['activation_rate'] }}%</span>
            </div>
        </div>

        <!-- Consent (POPIA) -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-bold text-gray-900">Consent (POPIA)</h3>
            @php
                $pt = $data['patients'];
                $ptTotal = max(1, $pt['total']);
                $optInW = ($pt['consented'] / $ptTotal) * 100;
                $optOutW = ($pt['opted_out'] / $ptTotal) * 100;
                $pendingW = max(0, 100 - $optInW - $optOutW);
            @endphp
            <div class="mb-3 flex h-6 overflow-hidden rounded-full bg-gray-100">
                <div class="flex h-full items-center justify-center bg-green-500 transition-all duration-700" style="width: {{ $optInW }}%">
                    @if($optInW > 12)<span class="text-[10px] font-bold text-white">{{ round($optInW) }}%</span>@endif
                </div>
                <div class="h-full bg-gray-300 transition-all duration-700" style="width: {{ $pendingW }}%"></div>
                <div class="flex h-full items-center justify-center bg-red-400 transition-all duration-700" style="width: {{ $optOutW }}%">
                    @if($optOutW > 12)<span class="text-[10px] font-bold text-white">{{ round($optOutW) }}%</span>@endif
                </div>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between"><span class="text-green-700">Opted in</span><span class="font-medium">{{ $data['consent']['opted_in'] }}</span></div>
                <div class="flex items-center justify-between"><span class="text-amber-600">Pending</span><span class="font-medium">{{ $data['consent']['pending'] }}</span></div>
                <div class="flex items-center justify-between"><span class="text-red-600">Opted out</span><span class="font-medium">{{ $data['consent']['opted_out'] }}</span></div>
            </div>
            <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3 text-sm">
                <span class="text-gray-600">Consent rate</span>
                <span class="font-semibold" style="color: #15803d;">{{ $data['consent']['consent_rate'] }}%</span>
            </div>
        </div>

        <!-- Order fulfilment -->
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-bold text-gray-900">Order fulfilment</h3>
            <div class="mb-4 grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-green-100 bg-green-50 p-4 text-center">
                    <p class="text-3xl font-black text-green-700">{{ $data['orders']['collections'] }}</p>
                    <p class="mt-1 text-xs font-medium text-green-600">Collections ({{ $data['orders']['collection_pct'] }}%)</p>
                </div>
                <div class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-center">
                    <p class="text-3xl font-black text-blue-700">{{ $data['orders']['deliveries'] }}</p>
                    <p class="mt-1 text-xs font-medium text-blue-600">Deliveries ({{ 100 - $data['orders']['collection_pct'] }}%)</p>
                </div>
            </div>
            <div class="space-y-2 border-t border-gray-100 pt-3 text-sm">
                <div class="flex items-center justify-between"><span class="text-gray-500">Total orders</span><span class="font-bold text-gray-900">{{ $data['orders']['total'] }}</span></div>
                <div class="flex items-center justify-between"><span class="text-gray-500">Completed</span><span class="font-bold text-green-600">{{ $data['orders']['completed'] }}</span></div>
                @if($data['orders']['avg_prep_minutes'] !== null)
                    <div class="flex items-center justify-between"><span class="text-gray-500">Avg preparation time</span><span class="font-bold text-gray-900">{{ $data['orders']['avg_prep_minutes'] }} min</span></div>
                @endif
                <div class="flex items-center justify-between"><span class="text-gray-500">Reminders sent</span><span class="font-bold text-gray-900">{{ $data['messaging']['reminders_sent'] }}</span></div>
            </div>
        </div>
    </div>

    <!-- Pharmacy performance ranking -->
    <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 p-6">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Pharmacy performance ranking</h3>
                <p class="mt-1 text-sm text-gray-500">Ranked by active patient count</p>
            </div>
            <span class="inline-flex items-center gap-1 rounded-lg bg-green-50 px-3 py-1.5 text-xs font-medium text-green-700">
                {{ $data['ranking']->count() }} {{ \Illuminate\Support\Str::plural('pharmacy', $data['ranking']->count()) }}
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/80 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="w-12 p-4 font-semibold">#</th>
                        <th class="p-4 font-semibold">Pharmacy</th>
                        <th class="p-4 text-center font-semibold">Active patients</th>
                        <th class="p-4 text-center font-semibold">Active journeys</th>
                        <th class="p-4 text-center font-semibold">Orders completed</th>
                        <th class="p-4 text-center font-semibold">Performance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($data['ranking'] as $index => $pharmacy)
                        @php $perf = $pharmacy->active_patients > 0 ? min(100, round(($pharmacy->completed_orders / max(1, $pharmacy->active_patients)) * 100)) : 0; @endphp
                        <tr class="transition hover:bg-green-50/30">
                            <td class="p-4">
                                @if($index === 0)
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-yellow-100 text-xs font-bold text-yellow-700">1</span>
                                @elseif($index === 1)
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-600">2</span>
                                @elseif($index === 2)
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-amber-50 text-xs font-bold text-amber-700">3</span>
                                @else
                                    <span class="pl-2 font-mono text-gray-400">{{ $index + 1 }}</span>
                                @endif
                            </td>
                            <td class="p-4">
                                <p class="font-semibold text-gray-900">{{ $pharmacy->name }}</p>
                                <p class="text-xs text-gray-500">{{ $pharmacy->city ?? '' }}</p>
                            </td>
                            <td class="p-4 text-center"><span class="inline-flex items-center rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">{{ $pharmacy->active_patients }}</span></td>
                            <td class="p-4 text-center text-sm font-medium text-gray-700">{{ $pharmacy->active_journeys }}</td>
                            <td class="p-4 text-center text-sm font-bold text-green-600">{{ $pharmacy->completed_orders }}</td>
                            <td class="p-4">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="h-2 w-16 overflow-hidden rounded-full bg-gray-100">
                                        <div class="h-full rounded-full {{ $perf >= 70 ? 'bg-green-500' : ($perf >= 40 ? 'bg-amber-500' : 'bg-red-400') }}" style="width: {{ $perf }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium text-gray-500">{{ $perf }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-gray-500">No pharmacies in scope.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
