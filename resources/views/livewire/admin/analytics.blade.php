<div>
    <x-slot name="header">Analytics Dashboard</x-slot>

    <!-- Period Selector -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-gray-900">Business Intelligence</h2>
        <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
            @foreach(['today' => 'Today', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year', 'all' => 'All'] as $key => $label)
            <button wire:click="$set('period', '{{ $key }}')"
                class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors {{ $period === $key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                {{ $label }}
            </button>
            @endforeach
        </div>
    </div>

    <!-- ═══ SECTION 1: REVENUE ═══ -->
    <div class="mb-10">
        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Revenue & Financial</h3>

        <!-- Top Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">Total Revenue</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">R{{ number_format($revenue['total'] / 100, 0) }}</p>
                <p class="text-xs mt-1 {{ $revenue['growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $revenue['growth'] >= 0 ? '+' : '' }}{{ $revenue['growth'] }}% vs prev period</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">Transactions</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $revenue['count'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">Avg per Transaction</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">R{{ number_format($revenue['average_per_transaction'] / 100, 0) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">Platform Profit</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">R{{ number_format(($profit['platform_profit'] ?? 0) / 100, 0) }}</p>
            </div>
        </div>

        <!-- Revenue Chart + Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h4 class="text-sm font-semibold text-gray-900 mb-4">Revenue (12 months)</h4>
                <canvas id="revenueChart" height="200"></canvas>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h4 class="text-sm font-semibold text-gray-900 mb-4">Revenue by Type</h4>
                <canvas id="revenueTypeChart" height="200"></canvas>
                <div class="mt-4 space-y-2 text-xs">
                    <div class="flex justify-between"><span class="text-gray-500">Consultations</span><span class="font-medium">R{{ number_format($revenueByType['consultations'] / 100, 0) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Medications</span><span class="font-medium">R{{ number_format($revenueByType['medications'] / 100, 0) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Subscriptions</span><span class="font-medium">R{{ number_format($revenueByType['subscriptions'] / 100, 0) }}</span></div>
                </div>
            </div>
        </div>

        <!-- Payout Breakdown -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mt-4">
            <div class="bg-blue-50 rounded-lg p-3"><p class="text-xs text-blue-600">Doctors Owed</p><p class="text-sm font-bold text-blue-800">R{{ number_format(($profit['doctor_payouts'] ?? 0) / 100, 0) }}</p></div>
            <div class="bg-purple-50 rounded-lg p-3"><p class="text-xs text-purple-600">Pharmacy Owed</p><p class="text-sm font-bold text-purple-800">R{{ number_format(($profit['pharmacy_payouts'] ?? 0) / 100, 0) }}</p></div>
            <div class="bg-amber-50 rounded-lg p-3"><p class="text-xs text-amber-600">Partners Owed</p><p class="text-sm font-bold text-amber-800">R{{ number_format(($profit['partner_payouts'] ?? 0) / 100, 0) }}</p></div>
            <div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-600">Delivery Costs</p><p class="text-sm font-bold text-gray-800">R{{ number_format(($profit['delivery_costs'] ?? 0) / 100, 0) }}</p></div>
            <div class="bg-emerald-50 rounded-lg p-3"><p class="text-xs text-emerald-600">Net Profit</p><p class="text-sm font-bold text-emerald-800">R{{ number_format(($profit['platform_profit'] ?? 0) / 100, 0) }}</p></div>
        </div>
    </div>


    <!-- ═══ SECTION 2: PATIENTS & GROWTH ═══ -->
    <div class="mb-10">
        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Patients & Growth</h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">Total Patients</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($patients['total']) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">New This Month</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $patients['this_month'] }}</p>
                <p class="text-xs mt-1 {{ $patients['growth'] >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $patients['growth'] >= 0 ? '+' : '' }}{{ $patients['growth'] }}%</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">Last Month</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $patients['last_month'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">Avg Revenue/Patient</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">R{{ $patients['total'] > 0 ? number_format($revenue['total'] / $patients['total'] / 100, 0) : 0 }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Signup Chart -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h4 class="text-sm font-semibold text-gray-900 mb-4">New Signups (12 months)</h4>
                <canvas id="signupChart" height="180"></canvas>
            </div>

            <!-- Conversion Funnel -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h4 class="text-sm font-semibold text-gray-900 mb-4">Conversion Funnel</h4>
                <div class="space-y-3">
                    @foreach($funnel as $stage)
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-gray-700 font-medium">{{ $stage['stage'] }}</span>
                            <span class="text-gray-500">{{ $stage['count'] }} ({{ $stage['percent'] }}%)</span>
                        </div>
                        <div class="h-3 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-zapmed-500 rounded-full transition-all" style="width: {{ $stage['percent'] }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ SECTION 3: CONSULTATIONS & DOCTORS ═══ -->
    <div class="mb-10">
        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Consultations & Doctors</h3>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">Total Consultations</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $consultations['total'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">Completed</p>
                <p class="text-2xl font-bold text-green-600 mt-1">{{ $consultations['completed'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">Avg Duration</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $consultations['avg_duration'] }} min</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">No-Shows</p>
                <p class="text-2xl font-bold text-red-600 mt-1">{{ $consultations['no_shows'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">No-Show Rate</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $consultations['no_show_rate'] }}%</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Doctor Performance -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h4 class="text-sm font-semibold text-gray-900 mb-4">Doctor Performance</h4>
                @if(count($doctorPerformance) > 0)
                <div class="space-y-3">
                    @foreach($doctorPerformance as $doc)
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700">{{ $doc['name'] }}</span>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-500">This month: {{ $doc['this_month'] }}</span>
                            <span class="text-xs font-semibold text-gray-900 bg-gray-100 px-2 py-0.5 rounded">{{ $doc['total'] }} total</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-sm text-gray-400">No data yet.</p>
                @endif
            </div>

            <!-- Treatment Popularity -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h4 class="text-sm font-semibold text-gray-900 mb-4">Treatment Popularity</h4>
                <canvas id="treatmentChart" height="180"></canvas>
            </div>
        </div>
    </div>


    <!-- ═══ SECTION 4: PRESCRIPTIONS & PHARMACY ═══ -->
    <div class="mb-10">
        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Prescriptions & Pharmacy</h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">Total Prescriptions</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $prescriptions['total'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">Chronic / One-off</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $prescriptions['chronic'] }} / {{ $prescriptions['one_off'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">Chronic Ratio</p>
                <p class="text-2xl font-bold text-purple-600 mt-1">{{ $prescriptions['chronic_ratio'] }}%</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-500">Avg Prescription Value</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">R{{ number_format($prescriptions['avg_value'] / 100, 0) }}</p>
            </div>
        </div>

        <!-- Top Medications -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h4 class="text-sm font-semibold text-gray-900 mb-4">Top Prescribed Medications</h4>
            @if(count($topMeds) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-gray-500 border-b"><th class="pb-2">Medication</th><th class="pb-2 text-center">Times Prescribed</th><th class="pb-2 text-right">Total Units</th></tr></thead>
                    <tbody>
                        @foreach($topMeds as $med)
                        <tr class="border-b border-gray-50"><td class="py-2 font-medium text-gray-900">{{ $med['medication_name'] }}</td><td class="py-2 text-center">{{ $med['times_prescribed'] }}</td><td class="py-2 text-right">{{ $med['total_units'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-sm text-gray-400">No prescriptions yet.</p>
            @endif
        </div>
    </div>

    <!-- ═══ SECTION 5: PARTNERS ═══ -->
    <div class="mb-10">
        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Partners & Affiliates</h3>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            @if(count($partners) > 0)
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-gray-500 border-b"><th class="pb-2">Partner</th><th class="pb-2 text-center">Referrals</th><th class="pb-2 text-center">Conversions</th><th class="pb-2 text-center">Rate</th><th class="pb-2 text-right">Earned</th></tr></thead>
                <tbody>
                    @foreach($partners as $partner)
                    <tr class="border-b border-gray-50"><td class="py-2 font-medium text-gray-900">{{ $partner['name'] }} <span class="text-xs text-gray-400">({{ $partner['slug'] }})</span></td><td class="py-2 text-center">{{ $partner['referrals'] }}</td><td class="py-2 text-center">{{ $partner['conversions'] }}</td><td class="py-2 text-center">{{ $partner['conversion_rate'] }}%</td><td class="py-2 text-right font-medium">R{{ number_format($partner['earned'] / 100, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p class="text-sm text-gray-400">No partners yet.</p>
            @endif
        </div>
    </div>

    <!-- ═══ SECTION 6: PREDICTIONS ═══ -->
    <div class="mb-10">
        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Predictions & Insights</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div class="bg-gradient-to-br from-zapmed-500 to-zapmed-700 rounded-xl p-5 text-white">
                <p class="text-xs text-zapmed-100">Next Month Revenue (forecast)</p>
                <p class="text-2xl font-bold mt-1">R{{ number_format($predictions['next_month_revenue'] / 100, 0) }}</p>
                <p class="text-xs text-zapmed-200 mt-1">Trend: {{ ucfirst($predictions['revenue_trend']) }}</p>
            </div>
            <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl p-5 text-white">
                <p class="text-xs text-blue-100">Expected New Patients</p>
                <p class="text-2xl font-bold mt-1">{{ $predictions['next_month_patients'] }}</p>
            </div>
            <div class="bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl p-5 text-white">
                <p class="text-xs text-purple-100">Top Growing Treatment</p>
                <p class="text-lg font-bold mt-1">{{ ucfirst(str_replace('-', ' ', $predictions['top_growing_treatment'])) }}</p>
            </div>
        </div>
        <div class="bg-amber-50 rounded-xl border border-amber-100 p-5">
            <p class="text-xs font-semibold text-amber-800 mb-1">Marketing Suggestion</p>
            <p class="text-sm text-amber-700">{{ $predictions['marketing_suggestion'] }}</p>
        </div>
    </div>

    <!-- ═══ SECTION 7: EXTERNAL (PLACEHOLDERS) ═══ -->
    <div class="mb-10">
        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Marketing & External</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-center">
                <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <p class="text-xs font-semibold text-gray-900">Google Analytics</p>
                <p class="text-xs text-gray-400 mt-1">Connect to see traffic data</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-center">
                <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <p class="text-xs font-semibold text-gray-900">PageSpeed Insights</p>
                <p class="text-xs text-gray-400 mt-1">Connect for performance scores</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-center">
                <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <p class="text-xs font-semibold text-gray-900">SEO Health</p>
                <p class="text-xs text-gray-400 mt-1">Connect Search Console</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-center">
                <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2m-8 0h8m-8 0H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2h-2"/></svg>
                <p class="text-xs font-semibold text-gray-900">Social Media</p>
                <p class="text-xs text-gray-400 mt-1">Connect accounts for stats</p>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('livewire:navigated', initCharts);
document.addEventListener('DOMContentLoaded', initCharts);

function initCharts() {
    // Revenue Chart (Line)
    const revCtx = document.getElementById('revenueChart');
    if (revCtx) {
        new Chart(revCtx, {
            type: 'line',
            data: {
                labels: @json(collect($revenueChart)->pluck('label')),
                datasets: [{
                    label: 'Revenue (R)',
                    data: @json(collect($revenueChart)->pluck('value')->map(fn($v) => $v / 100)),
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.1)',
                    fill: true, tension: 0.4, borderWidth: 2,
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    // Revenue by Type (Doughnut)
    const typeCtx = document.getElementById('revenueTypeChart');
    if (typeCtx) {
        new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Consultations', 'Medications', 'Subscriptions'],
                datasets: [{ data: [{{ $revenueByType['consultations'] }}, {{ $revenueByType['medications'] }}, {{ $revenueByType['subscriptions'] }}], backgroundColor: ['#059669', '#7c3aed', '#f59e0b'] }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
        });
    }

    // Signups Chart (Bar)
    const signCtx = document.getElementById('signupChart');
    if (signCtx) {
        new Chart(signCtx, {
            type: 'bar',
            data: {
                labels: @json(collect($signupChart)->pluck('label')),
                datasets: [{ label: 'New Patients', data: @json(collect($signupChart)->pluck('value')), backgroundColor: '#059669', borderRadius: 4 }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    // Treatment Popularity (Horizontal Bar)
    const treatCtx = document.getElementById('treatmentChart');
    if (treatCtx) {
        const treatData = @json($treatmentPopularity);
        new Chart(treatCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(treatData).map(k => k.replace(/_/g, ' ')),
                datasets: [{ data: Object.values(treatData), backgroundColor: '#6366f1', borderRadius: 4 }]
            },
            options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
        });
    }
}
</script>
