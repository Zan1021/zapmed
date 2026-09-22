<div>
    <x-slot name="header">SPAR Win-back</x-slot>

    <x-spar::page-header eyebrow="SPAR Group" title="Win-back"
        subtitle="Patients who opted out of reminders. Reach out with a personal incentive to bring them back." />

    @if($actionNotice)
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700"
             role="status" wire:key="winback-notice">
            {{ $actionNotice }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="p-4 font-semibold">Patient</th>
                        <th class="p-4 font-semibold">Pharmacy</th>
                        <th class="p-4 font-semibold">Personal incentive</th>
                        <th class="p-4 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($this->lostCustomers as $patient)
                        <tr wire:key="winback-{{ $patient->id }}" class="hover:bg-green-50/30">
                            <td class="p-4 font-semibold text-gray-900">{{ $patient->display_name }}</td>
                            <td class="p-4 text-gray-600">{{ $patient->pharmacy?->name ?? '—' }}</td>
                            <td class="p-4">
                                <input type="text" wire:model="incentive.{{ $patient->id }}" maxlength="200"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm"
                                       placeholder="e.g. 15% off your next repeat + free BP check" />
                            </td>
                            <td class="p-4 text-right">
                                <button wire:click="sendWinBack({{ $patient->id }})"
                                        class="rounded-lg bg-green-600 hover:bg-green-700 px-3 py-1.5 text-xs font-semibold text-white">
                                    Send offer
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-8 text-center text-gray-500">
                                No lost customers right now — nobody has opted out of reminders.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
