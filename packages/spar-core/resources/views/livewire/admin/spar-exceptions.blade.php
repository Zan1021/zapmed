<div>
    <x-slot name="header">SPAR Exceptions</x-slot>

    <x-spar::page-header eyebrow="SPAR Group" title="Exceptions"
        subtitle="Overdue dispenses, missing renewals and unresponsive patients." />

    <!-- Action outcome flash -->
    @if($actionNotice)
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700"
             role="status" wire:key="action-notice">
            {{ $actionNotice }}
        </div>
    @endif

    <!-- Filter Tabs -->
    <div class="flex gap-2 mb-6">
        <button wire:click="$set('filter', 'all')" class="px-4 py-2 text-sm rounded-lg transition {{ $filter === 'all' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">All</button>
        <button wire:click="$set('filter', 'overdue')" class="px-4 py-2 text-sm rounded-lg transition {{ $filter === 'overdue' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">Overdue Dispenses</button>
        <button wire:click="$set('filter', 'renewals')" class="px-4 py-2 text-sm rounded-lg transition {{ $filter === 'renewals' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">Missing Renewals</button>
        <button wire:click="$set('filter', 'unresponsive')" class="px-4 py-2 text-sm rounded-lg transition {{ $filter === 'unresponsive' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">Unresponsive</button>
    </div>

    <!-- Overdue Dispenses -->
    @if($filter === 'all' || $filter === 'overdue')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Overdue Dispenses</h3>
                <span class="text-sm text-red-600">{{ $this->overdueDispenses->count() }} patients</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 font-medium text-gray-600">Patient</th>
                            <th class="text-left p-3 font-medium text-gray-600">Pharmacy</th>
                            <th class="text-left p-3 font-medium text-gray-600">Due Date</th>
                            <th class="text-left p-3 font-medium text-gray-600">Days Overdue</th>
                            <th class="text-left p-3 font-medium text-gray-600">Reminded</th>
                            <th class="text-left p-3 font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($this->overdueDispenses as $dispense)
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-900">{{ $dispense->patient->display_name }}</td>
                                <td class="p-3 text-gray-600">{{ $dispense->journey->pharmacy->name ?? '-' }}</td>
                                <td class="p-3 text-gray-600">{{ $dispense->due_date->format('d M Y') }}</td>
                                <td class="p-3">
                                    @php $daysOverdue = (int) floor($dispense->due_date->diffInDays(now())); @endphp
                                    <span class="text-red-600 font-medium">{{ $daysOverdue }} {{ \Illuminate\Support\Str::plural('day', $daysOverdue) }}</span>
                                </td>
                                <td class="p-3">
                                    @if($dispense->reminded_at)
                                        <span class="text-green-600">{{ $dispense->reminded_at->format('d M') }}</span>
                                    @else
                                        <span class="text-gray-400">Not yet</span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    <x-spar::action-buttons
                                        :subject-type="\Zapmed\SparCore\Models\SparDispenseRecord::class"
                                        :subject-id="$dispense->id" />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-6 text-center text-gray-500">No overdue dispenses.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Missing Renewals -->
    @if($filter === 'all' || $filter === 'renewals')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Missing Renewals (7+ days overdue)</h3>
                <span class="text-sm text-amber-600">{{ $this->missingRenewals->count() }} journeys</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 font-medium text-gray-600">Patient</th>
                            <th class="text-left p-3 font-medium text-gray-600">Pharmacy</th>
                            <th class="text-left p-3 font-medium text-gray-600">Renewal Due</th>
                            <th class="text-left p-3 font-medium text-gray-600">Doctor</th>
                            <th class="text-left p-3 font-medium text-gray-600">Dispenses</th>
                            <th class="text-left p-3 font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($this->missingRenewals as $journey)
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-900">{{ $journey->patient->display_name }}</td>
                                <td class="p-3 text-gray-600">{{ $journey->pharmacy->name ?? '-' }}</td>
                                <td class="p-3 text-amber-600">{{ $journey->renewal_due_date?->format('d M Y') ?? '-' }}</td>
                                <td class="p-3 text-gray-600">{{ $journey->doctor_name ?? '-' }}</td>
                                <td class="p-3 text-gray-600">{{ $journey->dispenses_completed }}/{{ $journey->total_dispenses }}</td>
                                <td class="p-3">
                                    <x-spar::action-buttons
                                        :subject-type="\Zapmed\SparCore\Models\SparPrescriptionJourney::class"
                                        :subject-id="$journey->id" />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-6 text-center text-gray-500">No missing renewals.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Unresponsive Patients -->
    @if($filter === 'all' || $filter === 'unresponsive')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Unresponsive (10+ days after reminder)</h3>
                <span class="text-sm text-gray-500">{{ $this->unresponsivePatients->count() }} patients</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left p-3 font-medium text-gray-600">Patient</th>
                            <th class="text-left p-3 font-medium text-gray-600">Pharmacy</th>
                            <th class="text-left p-3 font-medium text-gray-600">Reminder Sent</th>
                            <th class="text-left p-3 font-medium text-gray-600">Due Date</th>
                            <th class="text-left p-3 font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($this->unresponsivePatients as $dispense)
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 font-medium text-gray-900">{{ $dispense->patient->display_name }}</td>
                                <td class="p-3 text-gray-600">{{ $dispense->journey->pharmacy->name ?? '-' }}</td>
                                <td class="p-3 text-gray-600">{{ $dispense->reminded_at?->format('d M Y') ?? '-' }}</td>
                                <td class="p-3 text-gray-600">{{ $dispense->due_date->format('d M Y') }}</td>
                                <td class="p-3">
                                    <x-spar::action-buttons
                                        :subject-type="\Zapmed\SparCore\Models\SparDispenseRecord::class"
                                        :subject-id="$dispense->id" />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-6 text-center text-gray-500">No unresponsive patients.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Personal message compose modal (FR-B6) -->
    @if($showMessageModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-labelledby="spar-message-title"
             wire:key="spar-message-modal">
            <div class="absolute inset-0 bg-black/40" wire:click="closeMessageModal"></div>

            <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 id="spar-message-title" class="text-lg font-semibold text-gray-900">Send a personal message</h3>
                    <p class="mt-1 text-sm text-gray-500">Goes into the patient's Health Coach thread. A no-PHI nudge is sent if they've consented.</p>
                </div>

                <div class="px-6 py-4">
                    <label for="spar-message-body" class="mb-1 block text-sm font-medium text-gray-700">Message</label>
                    <textarea id="spar-message-body" wire:model="messageBody" rows="4"
                              class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                              placeholder="Write a short, friendly message…" maxlength="2000"></textarea>
                    @error('messageBody')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4">
                    <button type="button" wire:click="closeMessageModal"
                            class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" wire:click="sendMessage" wire:loading.attr="disabled"
                            class="rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-sm"
                            style="background-color: {{ config('spar.branding.primary_color', '#006B3F') }};">
                        Send message
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
