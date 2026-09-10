<div wire:poll.30s>
    <x-slot name="header">Statistics</x-slot>

    @php
        $scopeLabel = match($stats['role']) {
            'super_admin' => 'Platform-wide',
            'group_admin' => 'Your group',
            default => 'Your pharmacy',
        };
    @endphp

    <div class="mb-6">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
            {{ $scopeLabel }} view
        </span>
    </div>

    <!-- Counts -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        @if($stats['role'] === 'super_admin')
            <div class="bg-white rounded-xl border border-gray-100 p-4">
                <p class="text-xs text-gray-500">Groups</p>
                <p class="text-2xl font-semibold text-gray-900">{{ $stats['counts']['groups'] }}</p>
            </div>
        @endif
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Pharmacies</p>
            <p class="text-2xl font-semibold text-gray-900">{{ $stats['counts']['pharmacies'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Patients</p>
            <p class="text-2xl font-semibold text-gray-900">{{ $stats['counts']['patients'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Active patients</p>
            <p class="text-2xl font-semibold text-gray-900">{{ $stats['counts']['active_patients'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Active journeys</p>
            <p class="text-2xl font-semibold text-gray-900">{{ $stats['counts']['active_journeys'] }}</p>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6 mb-8">
        <!-- Onboarding funnel -->
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Onboarding funnel</h3>
            @foreach(['awaiting_contact' => 'Awaiting contact', 'pending_consent' => 'Pending consent', 'active' => 'Active', 'opted_out' => 'Opted out'] as $key => $label)
                <div class="flex items-center justify-between py-1 text-sm">
                    <span class="text-gray-600">{{ $label }}</span>
                    <span class="font-medium">{{ $stats['onboarding_funnel'][$key] }}</span>
                </div>
            @endforeach
            <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between text-sm">
                <span class="text-gray-600">Activation rate</span>
                <span class="font-semibold text-green-700">{{ $stats['onboarding_funnel']['activation_rate'] }}%</span>
            </div>
        </div>

        <!-- Consent -->
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Consent (POPIA)</h3>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-green-700">Opted in</span><span class="font-medium">{{ $stats['consent']['opted_in'] }}</span></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-amber-600">Pending</span><span class="font-medium">{{ $stats['consent']['pending'] }}</span></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-red-600">Opted out</span><span class="font-medium">{{ $stats['consent']['opted_out'] }}</span></div>
            <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between text-sm">
                <span class="text-gray-600">Consent rate</span>
                <span class="font-semibold text-green-700">{{ $stats['consent']['consent_rate'] }}%</span>
            </div>
        </div>

        <!-- Adherence -->
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Adherence</h3>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-gray-600">Collected</span><span class="font-medium">{{ $stats['adherence']['collected'] }}</span></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-gray-600">Upcoming</span><span class="font-medium">{{ $stats['adherence']['upcoming'] }}</span></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-red-600">Overdue</span><span class="font-medium">{{ $stats['adherence']['overdue'] }}</span></div>
        </div>

        <!-- Renewals + Orders -->
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Renewals &amp; orders</h3>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-gray-600">Renewals due</span><span class="font-medium">{{ $stats['renewals']['due'] }}</span></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-gray-600">Renewed</span><span class="font-medium">{{ $stats['renewals']['renewed'] }}</span></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-red-600">Lapsed (30d+)</span><span class="font-medium">{{ $stats['renewals']['lapsed'] }}</span></div>
            <div class="mt-2 pt-2 border-t border-gray-100"></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-gray-600">Orders ready</span><span class="font-medium">{{ $stats['orders']['ready'] }}</span></div>
            <div class="flex items-center justify-between py-1 text-sm"><span class="text-gray-600">Reminders sent</span><span class="font-medium">{{ $stats['messaging']['reminders_sent'] }}</span></div>
        </div>
    </div>

    <!-- Leaderboard (super/group only) -->
    @if(!empty($stats['leaderboard']))
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Pharmacy leaderboard — consent rate</h3>
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500">
                    <tr><th class="py-1">Pharmacy</th><th class="py-1 text-center">Patients</th><th class="py-1 text-right">Consent rate</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($stats['leaderboard'] as $row)
                        <tr>
                            <td class="py-2">{{ $row['pharmacy'] }}</td>
                            <td class="py-2 text-center">{{ $row['patients'] }}</td>
                            <td class="py-2 text-right font-medium">{{ $row['consent_rate'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
