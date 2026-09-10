<div class="max-w-6xl mx-auto py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Consent</h1>
        <p class="text-sm text-gray-500">POPIA consent status and audit trail across all SPAR patients.</p>
    </div>

    {{-- Summary tiles --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Consented</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['consented'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Pending</p>
            <p class="text-2xl font-bold text-amber-500">{{ $stats['pending'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <p class="text-xs text-gray-500">Opted out</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['opted_out'] }}</p>
        </div>
    </div>

    {{-- Filter --}}
    <div class="mb-4">
        <select wire:model.live="statusFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="all">All statuses</option>
            <option value="opted_in">Consented</option>
            <option value="pending">Pending</option>
            <option value="opted_out">Opted out</option>
        </select>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Pharmacy</th>
                    <th class="px-4 py-3">Consent</th>
                    <th class="px-4 py-3 text-right">Detail</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($patients as $patient)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $patient->display_name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $patient->pharmacy?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php $s = $patient->consent_status; @endphp
                            @if($s === 'opted_in')
                                <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-green-100 text-green-700">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                    Consented
                                </span>
                            @elseif($s === 'opted_out')
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-red-100 text-red-700">Opted out</span>
                            @else
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Pending</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="view({{ $patient->id }})" class="text-green-700 font-medium hover:text-green-800">View</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No patients.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $patients->links() }}</div>
    </div>

    {{-- Detail drawer --}}
    @if($this->viewingPatient)
        @php $p = $this->viewingPatient; @endphp
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">{{ $p->display_name }}</h2>
                        <p class="text-sm text-gray-500">{{ $p->pharmacy?->name }} &middot; Profile {{ $p->profile_code }}</p>
                    </div>
                    <button wire:click="closeDetail" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                {{-- Status banner --}}
                @if($p->consent_status === 'opted_in')
                    <div class="flex items-center gap-2 bg-green-50 border border-green-200 rounded-xl p-3 mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <div>
                            <p class="font-semibold text-green-800">Consent granted</p>
                            <p class="text-xs text-green-600">{{ optional($p->consent_given_at)->format('d M Y H:i') }} via {{ $p->consent_channel }}</p>
                        </div>
                    </div>
                @elseif($p->consent_status === 'opted_out')
                    <div class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4">
                        <p class="font-semibold text-red-800">Opted out</p>
                        <p class="text-xs text-red-600">{{ optional($p->consent_revoked_at)->format('d M Y H:i') }}</p>
                    </div>
                @else
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4">
                        <p class="font-semibold text-amber-800">Consent pending</p>
                    </div>
                @endif

                {{-- Details --}}
                <dl class="text-sm mb-4 space-y-1">
                    <div class="flex justify-between"><dt class="text-gray-500">Cellphone</dt><dd class="text-gray-900">{{ $p->cellphone ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Email</dt><dd class="text-gray-900">{{ $p->email ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Onboarding</dt><dd class="text-gray-900">{{ str_replace('_', ' ', $p->onboarding_status) }}</dd></div>
                </dl>

                {{-- Dependants --}}
                @if($this->dependants->isNotEmpty())
                    <div class="mb-4">
                        <p class="text-xs font-medium text-gray-500 mb-1">Dependants under this profile</p>
                        <ul class="text-sm text-gray-700 list-disc list-inside">
                            @foreach($this->dependants as $dep)
                                <li>{{ $dep->display_name }} (dependant {{ $dep->dependent_code }})</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Audit trail --}}
                <div>
                    <p class="text-xs font-medium text-gray-500 mb-2">Consent audit trail</p>
                    <div class="space-y-2">
                        @forelse($p->consents as $c)
                            <div class="border border-gray-100 rounded-lg p-3 text-xs">
                                <div class="flex justify-between">
                                    <span class="font-medium {{ $c->granted ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $c->granted ? 'Granted' : 'Revoked' }} &middot; {{ $c->consent_type }}
                                    </span>
                                    <span class="text-gray-400">v{{ $c->version }}</span>
                                </div>
                                <p class="text-gray-500 mt-1">
                                    {{ optional($c->granted_at ?? $c->revoked_at)->format('d M Y H:i') }}
                                    &middot; {{ $c->channel }} &middot; {{ $c->source }}
                                    @if($c->ip_address) &middot; {{ $c->ip_address }} @endif
                                </p>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400">No recorded consent events.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
