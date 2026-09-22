<div>
    <x-slot name="header">SPAR Renewals</x-slot>

    <x-spar::page-header eyebrow="SPAR Group" title="Renewals"
        subtitle="Prescriptions due for renewal. Prompt patients to prepare their next script." />

    @if($actionNotice)
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700"
             role="status" wire:key="renewal-notice">
            {{ $actionNotice }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex flex-wrap gap-2">
            <button wire:click="setWindow('week')"
                    class="px-4 py-2 text-sm rounded-lg transition {{ $window === 'week' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
                Due this week <span class="ml-1 opacity-70">{{ $this->counts['week'] }}</span>
            </button>
            <button wire:click="setWindow('month')"
                    class="px-4 py-2 text-sm rounded-lg transition {{ $window === 'month' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
                Due this month <span class="ml-1 opacity-70">{{ $this->counts['month'] }}</span>
            </button>
            <button wire:click="setWindow('all')"
                    class="px-4 py-2 text-sm rounded-lg transition {{ $window === 'all' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
                All due <span class="ml-1 opacity-70">{{ $this->counts['all'] }}</span>
            </button>
        </div>

        @if($this->renewals->isNotEmpty())
            <button wire:click="remindAll"
                    wire:confirm="Send a renewal reminder to every consented patient in this list?"
                    class="inline-flex items-center gap-2 rounded-lg bg-green-600 hover:bg-green-700 px-4 py-2 text-sm font-semibold text-white">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                Remind everyone
            </button>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="p-4 font-semibold">Patient</th>
                        <th class="p-4 font-semibold">Pharmacy</th>
                        <th class="p-4 font-semibold">Renewal due</th>
                        <th class="p-4 font-semibold">Dispenses</th>
                        <th class="p-4 font-semibold">State</th>
                        <th class="p-4 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($this->renewals as $journey)
                        <tr wire:key="renewal-{{ $journey->id }}" class="hover:bg-green-50/30">
                            <td class="p-4 font-semibold text-gray-900">{{ $journey->patient?->display_name ?? '—' }}</td>
                            <td class="p-4 text-gray-600">{{ $journey->pharmacy?->name ?? '—' }}</td>
                            <td class="p-4 text-gray-700">
                                {{ $journey->renewal_due_date?->format('d M Y') ?? 'Due now' }}
                            </td>
                            <td class="p-4 text-gray-700">{{ $journey->dispenses_completed }}/{{ $journey->total_dispenses }}</td>
                            <td class="p-4">
                                @php
                                    $st = $journey->action_status instanceof \Zapmed\SparCore\Enums\SparActionableStatus
                                        ? $journey->action_status->value
                                        : (string) ($journey->action_status ?? 'open');
                                    $stClass = match($st) {
                                        'awaiting_patient' => 'bg-amber-100 text-amber-700',
                                        'actioned' => 'bg-blue-100 text-blue-700',
                                        'resolved' => 'bg-green-100 text-green-700',
                                        'snoozed' => 'bg-gray-100 text-gray-500',
                                        default => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $stClass }}">{{ str_replace('_', ' ', $st) }}</span>
                            </td>
                            <td class="p-4 text-right">
                                <button wire:click="remindOne({{ $journey->id }})"
                                        class="rounded-lg bg-green-600 hover:bg-green-700 px-3 py-1.5 text-xs font-semibold text-white">
                                    Remind
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-500">No renewals due in this window.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
