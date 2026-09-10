<div>
    <x-slot name="header">Patient — {{ $this->patient->display_name }}</x-slot>

    <div class="mb-4">
        <a href="{{ route('spar.patients') }}" class="text-sm text-green-700 hover:text-green-800">&larr; Back to patients</a>
    </div>

    @if($this->patient->needs_identity_review)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
            <p class="text-sm text-amber-800"><strong>Identity review needed:</strong>
                {{ $this->patient->identity_review_reason ?? 'Flagged during import.' }}</p>
        </div>
    @endif

    {{-- ===================== TABS ===================== --}}
    <div class="flex items-center gap-1 border-b border-gray-200 mb-6">
        <button wire:click="setTab('overview')"
                dusk="overview-tab"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition
                    {{ $tab === 'overview' ? 'border-green-600 text-green-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Overview
        </button>
        <button wire:click="setTab('dependants')"
                dusk="dependants-tab"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition
                    {{ $tab === 'dependants' ? 'border-green-600 text-green-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Dependants ({{ $this->dependants->count() }})
        </button>
    </div>

    {{-- ===================== DEPENDANTS TAB ===================== --}}
    @if($tab === 'dependants')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-900 mb-4">Dependants under this profile</h3>
            @forelse($this->dependants as $dep)
                <div class="flex items-center justify-between py-3 border-b border-gray-50 last:border-0">
                    <div>
                        <p class="font-medium text-gray-900">{{ $dep->display_name }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $dep->dependent_relation ?: 'dependant' }} · code {{ $dep->dependent_code }}
                            @if($dep->cellphone) · {{ $dep->cellphone }} @endif
                        </p>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full
                        {{ $dep->consent_status === 'opted_in' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                        {{ ucfirst(str_replace('_',' ', $dep->consent_status)) }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-400">No dependants under this profile.</p>
            @endforelse
            <p class="text-xs text-gray-400 mt-4">Dependants roll up under the primary member — they have no separate login and are reached via the principal.</p>
        </div>

    {{-- ===================== OVERVIEW TAB ===================== --}}
    @else
    <div class="grid gap-6 lg:grid-cols-3">

        {{-- PATIENT MIRROR (read-only) --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900">What the patient sees (mobi view)</h3>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">read-only</span>
                </div>

                @php $consented = $this->patient->hasConsented(); @endphp
                <div class="mb-4 text-sm {{ $consented ? 'text-green-700' : 'text-amber-700' }}">
                    @if($consented)
                        ✓ Patient has consented — they see their dashboard below.
                    @else
                        ⚠ Patient has NOT consented — on their phone they currently see only the consent gate.
                        (Shown here to staff for support.)
                    @endif
                </div>

                @if($this->renewalDue)
                    <div class="rounded-xl border border-amber-200 p-4 mb-4">
                        <h4 class="font-semibold text-gray-900 mb-1">Prescription renewal needed</h4>
                        <p class="text-sm text-gray-600">Final repeat reached — the patient is prompted to renew.</p>
                    </div>
                @endif

                @forelse($this->journeys as $journey)
                    <div class="rounded-xl border border-gray-100 p-4 mb-3">
                        <div class="flex items-center justify-between mb-1">
                            <div>
                                <p class="font-medium text-gray-900">Prescription</p>
                                @if($journey->pharmacy)
                                    <p class="text-xs text-gray-500">Collected at: {{ $journey->pharmacy->name }}</p>
                                @endif
                                @if($journey->patient && !$journey->patient->is_primary_member)
                                    <p class="text-xs text-gray-500">For dependant:
                                        {{ $journey->patient->first_name ?: 'Dependant #'.$journey->patient->dependent_code }}</p>
                                @endif
                            </div>
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full
                                {{ $journey->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $journey->status === 'renewal_due' ? 'Renewal Due' : 'Active' }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-600">Dispenses: {{ $journey->dispenses_completed }} / {{ $journey->total_dispenses }}</p>
                        @if(!empty($journey->medications))
                            <ul class="mt-2 text-sm text-gray-700 list-disc list-inside">
                                @foreach($journey->medications as $med)
                                    <li>{{ $med['name'] ?? '' }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No active prescription on this profile.</p>
                @endforelse
            </div>

            {{-- PREVIOUS PRESCRIPTIONS (past/completed/renewed/expired journeys) --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Previous prescriptions</h3>
                @forelse($this->pastJourneys as $journey)
                    <div class="rounded-lg border border-gray-100 p-3 mb-2">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-800">
                                    {{ $journey->medications[0]['name'] ?? 'Prescription' }}
                                    @if(count($journey->medications ?? []) > 1)
                                        <span class="text-xs text-gray-400">+{{ count($journey->medications) - 1 }} more</span>
                                    @endif
                                </p>
                                @if($journey->pharmacy)
                                    <p class="text-xs text-gray-500">{{ $journey->pharmacy->name }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                                    {{ ucfirst(str_replace('_',' ', $journey->status)) }}
                                </span>
                                <p class="text-xs text-gray-400 mt-1">{{ $journey->dispenses_completed }}/{{ $journey->total_dispenses }} · {{ optional($journey->start_date)->format('M Y') }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No previous prescriptions.</p>
                @endforelse
            </div>

            {{-- COLLECTION HISTORY --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Collection history</h3>
                @forelse($this->history as $record)
                    <div class="flex items-center justify-between text-sm border-b border-gray-50 pb-1 mb-1 last:border-0">
                        <span class="text-gray-700">
                            Dispense #{{ $record->dispense_number }}
                            <span class="text-gray-400">({{ ucfirst($record->status) }})</span>
                            @if($record->journey?->pharmacy)
                                <span class="text-xs text-gray-400">— {{ $record->journey->pharmacy->name }}</span>
                            @endif
                        </span>
                        <span class="text-xs text-gray-400">{{ $record->completed_at?->format('d M Y') }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No collections yet.</p>
                @endforelse
            </div>
        </div>

        {{-- STAFF PROVENANCE PANEL --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <h4 class="font-semibold text-gray-900 mb-2 text-sm">Patient mobi link</h4>
                <p class="text-xs text-gray-500 mb-2">The patient's signed tracker URL — copy to resend.</p>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ $this->mobiUrl }}"
                           class="flex-1 text-xs font-mono border border-gray-200 rounded-lg px-2 py-1.5 bg-gray-50"
                           onclick="this.select()" />
                    <a href="{{ $this->mobiUrl }}" target="_blank" rel="noopener"
                       class="text-xs px-2 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700">Open</a>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <h4 class="font-semibold text-gray-900 mb-2 text-sm">Contact &amp; consent</h4>
                <dl class="text-sm space-y-1">
                    <div class="flex justify-between"><dt class="text-gray-500">Cellphone</dt><dd class="text-gray-900">{{ $this->patient->cellphone ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Email</dt><dd class="text-gray-900">{{ $this->patient->email ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Consent</dt>
                        <dd class="{{ $this->patient->consent_status === 'opted_in' ? 'text-green-700' : 'text-gray-900' }}">
                            {{ ucfirst(str_replace('_',' ', $this->patient->consent_status)) }}</dd></div>
                </dl>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <h4 class="font-semibold text-gray-900 mb-2 text-sm">Onboarding</h4>
                <dl class="text-sm space-y-1">
                    <div class="flex justify-between"><dt class="text-gray-500">Signed up at</dt>
                        <dd class="text-gray-900">{{ optional($this->patient->onboardingPharmacy)->name ?? optional($this->patient->pharmacy)->name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Onboarded by</dt>
                        <dd class="text-gray-900">{{ $this->capturedByName ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">When</dt>
                        <dd class="text-gray-900">{{ optional($this->patient->captured_at)->format('d M Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Profile code</dt>
                        <dd class="font-mono text-xs text-gray-900">{{ $this->patient->profile_code }}</dd></div>
                </dl>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <h4 class="font-semibold text-gray-900 mb-2 text-sm">Consent history</h4>
                @forelse($this->consentTrail as $c)
                    <div class="text-xs py-1 border-b border-gray-50 last:border-0">
                        <span class="{{ $c->granted ? 'text-green-700' : 'text-red-600' }}">
                            {{ $c->granted ? 'Granted' : 'Revoked' }}</span>
                        <span class="text-gray-500">v{{ $c->version }} · {{ $c->channel }} · {{ $c->source }}</span>
                        <span class="text-gray-400 float-right">{{ optional($c->granted_at ?? $c->revoked_at ?? $c->created_at)->format('d M Y H:i') }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No consent events recorded.</p>
                @endforelse
            </div>
        </div>
    </div>
    @endif
</div>
