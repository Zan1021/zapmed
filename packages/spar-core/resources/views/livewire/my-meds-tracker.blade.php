<div class="max-w-md mx-auto">

    {{-- ============================ OTP RE-VERIFY ============================ --}}
    @if($step === 'otp')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mt-6">
            <h2 class="text-xl font-bold text-gray-900 mb-1">Verify it's you</h2>
            <p class="text-sm text-gray-500 mb-4">
                We've sent a 6-digit code to your registered contact. Enter it to view your medication.
            </p>

            @if(session('dev_otp'))
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-2 mb-3 text-xs text-blue-700">
                    Dev code: <strong>{{ session('dev_otp') }}</strong>
                </div>
            @endif

            @error('otp') <p class="text-sm text-red-600 mb-2">{{ $message }}</p> @enderror
            @if($error) <p class="text-sm text-red-600 mb-2">{{ $error }}</p> @endif

            <input type="text" wire:model="otp" inputmode="numeric" maxlength="6"
                   class="w-full text-center text-2xl tracking-[0.5em] font-mono border border-gray-300 rounded-xl py-3 mb-4"
                   placeholder="______" />

            <button wire:click="verifyOtp"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl py-3">
                Verify
            </button>
        </div>

    {{-- ============================ CONSENT GATE ============================ --}}
    @elseif($step === 'consent')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mt-6">
            <h2 class="text-xl font-bold text-gray-900 mb-1">Your consent</h2>
            <p class="text-sm text-gray-500 mb-4">
                Before we show your medication details, we need your permission to process your
                health information and send you medication reminders.
            </p>

            @if(session('declined'))
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-sm text-amber-800">
                    You've opted out. You can consent again below at any time to use the service.
                </div>
            @endif

            <div class="bg-gray-50 rounded-xl p-4 text-xs text-gray-600 mb-4 max-h-40 overflow-y-auto">
                <p class="mb-2 font-medium text-gray-700">What you're agreeing to:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>{{ config('spar.branding.name', 'SPAR Pharmacy') }} may process your prescription
                        and contact details to manage your chronic medication.</li>
                    <li>We may send you medication collection and renewal reminders.</li>
                    <li>You can withdraw consent at any time, which stops all reminders.</li>
                </ul>
                <p class="mt-2 text-gray-400">Consent version {{ config('spar.consent_version', '1.0') }}</p>
            </div>

            @if($error) <p class="text-sm text-red-600 mb-2">{{ $error }}</p> @endif

            <label class="flex items-start gap-2 mb-4 cursor-pointer">
                <input type="checkbox" wire:model="consentAccepted" class="mt-1 rounded border-gray-300 text-green-600" />
                <span class="text-sm text-gray-700">
                    I have read and I give my consent as described above.
                </span>
            </label>

            <button wire:click="grantConsent"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl py-3 mb-2">
                I consent &mdash; continue
            </button>
            <button wire:click="declineConsent"
                    class="w-full text-gray-500 text-sm py-2">
                No thanks
            </button>
        </div>

    {{-- ============================ DASHBOARD ============================ --}}
    @else
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-xl p-3 my-4">
                <p class="text-sm text-green-800">{{ session('success') }}</p>
            </div>
        @endif

        @php $patient = $this->sparPatient; @endphp

        {{-- ===================== PROMO BANNER SLIDER ===================== --}}
        {{-- Group-scoped ads, shown ONLY here (post-consent), under the logo.
             Lightweight Alpine carousel, lazy WebP, capped at 5. --}}
        @if($this->banners->isNotEmpty())
            <div class="my-4" x-data="{ i: 0, n: {{ $this->banners->count() }} }"
                 x-init="if (n > 1) setInterval(() => i = (i + 1) % n, 5000)">
                <div class="relative overflow-hidden rounded-2xl aspect-[1080/420]">
                    @foreach($this->banners as $idx => $banner)
                        <div x-show="i === {{ $idx }}" x-transition.opacity class="absolute inset-0 w-full h-full">
                            @if($banner->link_url)
                                <a href="{{ route('spar.banner.click', $banner->id) }}" target="_blank" rel="noopener">
                                    <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" loading="lazy" class="w-full h-full object-cover" />
                                </a>
                            @else
                                <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" loading="lazy" class="w-full h-full object-cover" />
                            @endif
                        </div>
                    @endforeach
                    @if($this->banners->count() > 1)
                        <div class="absolute bottom-2 left-0 right-0 flex justify-center gap-1.5">
                            @foreach($this->banners as $idx => $banner)
                                <button @click="i = {{ $idx }}"
                                        class="w-2 h-2 rounded-full"
                                        :class="i === {{ $idx }} ? 'bg-white' : 'bg-white/50'"></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <div class="my-6">
            <h2 class="text-xl font-bold text-gray-900">Hi, {{ $patient?->first_name ?: $patient?->display_name }}!</h2>
            <p class="text-sm text-gray-500">Here's your medication status.</p>
        </div>

        {{-- Journeys across the whole profile (self + dependants, spec FR-8).
             Alpine filter toggle: "My Prescriptions" (main member) vs
             "Dependants". Cards are tagged data-owner="mine|dependant". --}}
        <div x-data="{ filter: 'mine' }">
            <div class="grid grid-cols-3 gap-2 mb-4">
                <button type="button" @click="filter = 'mine'"
                        :style="filter === 'mine' ? 'background: {{ config('spar.branding.primary_color', '#006B3F') }}; color: #fff;' : ''"
                        :class="filter === 'mine' ? '' : 'bg-white text-gray-700 border border-gray-200'"
                        class="text-center font-semibold rounded-xl py-2.5 text-sm transition-colors">
                    My Prescriptions
                </button>
                <button type="button" @click="filter = 'dependants'"
                        :style="filter === 'dependants' ? 'background: {{ config('spar.branding.primary_color', '#006B3F') }}; color: #fff;' : ''"
                        :class="filter === 'dependants' ? '' : 'bg-white text-gray-700 border border-gray-200'"
                        class="text-center font-semibold rounded-xl py-2.5 text-sm transition-colors">
                    Dependants
                </button>
                <button type="button" @click="filter = 'renewals'"
                        :style="filter === 'renewals' ? 'background: {{ config('spar.branding.primary_color', '#006B3F') }}; color: #fff;' : ''"
                        :class="filter === 'renewals' ? '' : 'bg-white text-gray-700 border border-gray-200'"
                        class="text-center font-semibold rounded-xl py-2.5 text-sm transition-colors">
                    Renewals
                </button>
            </div>

            @forelse($this->journeys as $journey)
                @php
                    $owner = ($journey->patient && !$journey->patient->is_primary_member) ? 'dependants' : 'mine';
                    $isRenewal = $journey->status === 'renewal_due' ? 'true' : 'false';
                @endphp
                <div data-owner="{{ $owner }}"
                     x-show="filter === '{{ $owner }}' || (filter === 'renewals' && {{ $isRenewal }})"
                     class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <h3 class="font-semibold text-gray-900">Prescription</h3>
                        @if($journey->pharmacy)
                            <p class="text-xs text-gray-500 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Collected at: {{ $journey->pharmacy->name }}
                            </p>
                        @endif
                        @if($journey->patient && !$journey->patient->is_primary_member)
                            <p class="text-xs font-medium text-green-600">
                                For dependant: {{ $journey->patient->first_name ?: 'Dependant #'.$journey->patient->dependent_code }}
                            </p>
                        @else
                            <p class="text-xs font-medium text-red-600">Main member</p>
                        @endif
                    </div>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full
                        {{ $journey->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-600 text-white' }}">
                        {{ $journey->status === 'renewal_due' ? 'Renewal Due' : 'Active' }}
                    </span>
                </div>
                <p class="text-sm text-gray-600">
                    <span class="font-semibold">Dispenses:</span> {{ $journey->dispenses_completed }} / {{ $journey->total_dispenses }}
                </p>
                <dl class="mt-2 grid grid-cols-1 gap-y-1 text-xs text-gray-500">
                    @if($journey->start_date)
                        <div class="flex justify-between">
                            <dt>Started</dt>
                            <dd class="text-gray-700">{{ $journey->start_date->format('d M Y') }}</dd>
                        </div>
                    @endif
                    @if($journey->next_dispense_date)
                        <div class="flex justify-between">
                            <dt>Next collection</dt>
                            <dd class="text-gray-700">{{ $journey->next_dispense_date->format('d M Y') }}</dd>
                        </div>
                    @endif
                    @if($journey->renewal_due_date)
                        <div class="flex justify-between">
                            <dt>Renewal due</dt>
                            <dd class="text-gray-700">{{ $journey->renewal_due_date->format('d M Y') }}</dd>
                        </div>
                    @endif
                </dl>
                @if(!empty($journey->medications))
                    <p class="mt-3 text-sm font-semibold text-gray-700">Medication</p>
                    <ul class="mt-1 text-sm text-gray-700 list-disc list-inside">
                        @foreach($journey->medications as $med)
                            <li>{{ $med['name'] ?? '' }}</li>
                        @endforeach
                    </ul>
                @endif

                @if($journey->status === 'renewal_due' && config('spar.online_consult.enabled') && config('spar.online_consult.url'))
                    <div class="mt-4">
                        <a href="{{ config('spar.online_consult.url') }}" target="_blank" rel="noopener"
                           class="block w-full text-center bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl py-3">
                            {{ config('spar.online_consult.label', 'Get a new prescription online') }}
                        </a>
                        <p class="text-xs text-gray-400 text-center mt-1">www.zapmed.co.za</p>
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
                <p class="text-amber-800">No active prescription found on your profile.</p>
                <p class="text-sm text-amber-600 mt-2">Contact your SPAR pharmacy for assistance.</p>
            </div>
        @endforelse

        @php
            $mineCount = $this->journeys->filter(fn ($j) => !($j->patient && !$j->patient->is_primary_member))->count();
            $depCount = $this->journeys->filter(fn ($j) => $j->patient && !$j->patient->is_primary_member)->count();
            $renewalCount = $this->journeys->filter(fn ($j) => $j->status === 'renewal_due')->count();
        @endphp
        @if($mineCount === 0)
            <div x-show="filter === 'mine'" class="bg-gray-50 rounded-xl p-6 text-center text-sm text-gray-600">
                No prescriptions on your own profile.
            </div>
        @endif
        @if($depCount === 0)
            <div x-show="filter === 'dependants'" class="bg-gray-50 rounded-xl p-6 text-center text-sm text-gray-600">
                No dependants have prescriptions on your profile.
            </div>
        @endif
        @if($renewalCount === 0)
            <div x-show="filter === 'renewals'" class="bg-gray-50 rounded-xl p-6 text-center text-sm text-gray-600">
                Nothing due for renewal right now.
            </div>
        @endif
        </div>{{-- /x-data filter wrapper --}}

        {{-- Renewal funnel card (spec FR-13). Moved to bottom, above history.
             CTA varies by host mode. Opens in a new window. --}}
        @if($this->renewalDue)
            <div class="bg-white rounded-2xl shadow-sm border border-amber-200 p-5 mb-4">
                <h3 class="font-semibold text-gray-900 mb-1">Prescription renewal needed</h3>
                <p class="text-sm text-gray-600 mb-3">
                    Your prescription has reached its final repeat. To continue your medication
                    you'll need a new script.
                </p>
                @if(config('spar.host_mode', 'integrated') === 'integrated')
                    <a href="{{ config('app.url') }}" target="_blank" rel="noopener"
                       class="block w-full text-center bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl py-3 mb-2">
                        Book an online consultation
                    </a>
                    <p class="text-xs text-gray-400 text-center">or renew with your own doctor</p>
                @else
                    @if(config('spar.online_consult.enabled') && config('spar.online_consult.url'))
                        <a href="{{ config('spar.online_consult.url') }}" target="_blank" rel="noopener"
                           class="block w-full text-center bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl py-3 mb-2">
                            {{ config('spar.online_consult.label', 'Get a new prescription online') }}
                        </a>
                        <p class="text-xs text-gray-400 text-center">or arrange a new script with your own doctor, then visit
                            {{ $patient?->pharmacy?->name ?? 'your SPAR pharmacy' }}.</p>
                    @else
                        <div class="bg-gray-50 rounded-xl p-3 text-sm text-gray-700">
                            Please arrange a new prescription with your doctor, then visit
                            {{ $patient?->pharmacy?->name ?? 'your SPAR pharmacy' }} to continue.
                        </div>
                    @endif
                @endif
            </div>
        @endif

        <a href="{{ route('my-meds.history') }}" class="block text-center text-sm text-green-700 py-3">
            View full history
        </a>
    @endif
</div>
