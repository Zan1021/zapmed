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

        @if($orderPlaced)
            <div class="bg-green-50 border border-green-200 rounded-xl p-3 my-4 flex items-start gap-2">
                <svg class="w-5 h-5 text-green-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <p class="text-sm text-green-800">
                    Order <span class="font-semibold">{{ $orderPlaced }}</span> placed. Your SPAR pharmacy will prepare it and let you know when it's ready.
                </p>
            </div>
        @endif

        @php $patient = $this->sparPatient; @endphp

        {{-- ===================== PROMO BANNER SLIDER ===================== --}}
        {{-- Group-scoped ads, shown ONLY here (post-consent), under the logo.
             Lightweight Alpine carousel, lazy WebP, capped at 5. --}}
        @if($this->banners->isNotEmpty())
            <div class="mt-0 mb-4" x-data="{ i: 0, n: {{ $this->banners->count() }} }"
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

        {{-- SCRIPTS-ON-HAND SUMMARY (Craig — quick glance of what's on the go).
             One chip per active journey: "<med> X/Y" (dispenses done / total). --}}
        @php
            $onHand = $this->journeys->filter(fn ($j) => $j->status !== 'renewal_due');
        @endphp
        @if($onHand->isNotEmpty())
            <div class="mb-5 rounded-2xl bg-white shadow-sm border border-gray-100 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">Scripts on hand</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($onHand as $j)
                        @php
                            $medName = $j->medications[0]['name'] ?? 'Prescription';
                            $done = (int) $j->dispenses_completed;
                            $total = (int) $j->total_dispenses;
                            $complete = $total > 0 && $done >= $total;
                        @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs
                            {{ $complete ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-green-200 bg-green-50 text-green-700' }}">
                            <span class="font-medium text-gray-800">{{ \Illuminate\Support\Str::title(\Illuminate\Support\Str::of($medName)->limit(22)) }}</span>
                            <span class="font-semibold">{{ $done }}/{{ $total ?: '—' }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

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

            {{-- HEALTH COACH entry — distinct accent button below the tab row
                 (not a 4th filter pill). Opens the coach on its own page,
                 inside the same signed session (no new OTP). --}}
            <a href="{{ route('my-meds.coach') }}"
               class="relative flex items-center gap-3 w-full rounded-xl border border-green-200 bg-green-50 hover:bg-green-100 transition-colors p-3 mb-4">
                <span class="w-9 h-9 rounded-full bg-green-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.9 9.9 0 01-4-.83L3 20l1.3-3.2A7.6 7.6 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </span>
                <span class="flex-1 text-left">
                    <span class="block font-semibold text-gray-900 leading-tight">Your Health Coach</span>
                    <span class="block text-xs text-gray-500">Chat with your SPAR pharmacy — questions &amp; product tips.</span>
                </span>
                @if($this->coachUnreadCount > 0)
                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-red-600 text-white text-xs font-semibold">
                        {{ $this->coachUnreadCount > 9 ? '9+' : $this->coachUnreadCount }}
                    </span>
                @endif
                <svg class="w-5 h-5 text-green-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>

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

                {{-- ORDER NEXT MEDS (FR-C1). Active journeys only; consent already
                     granted to reach the dashboard. Collect/deliver + pay intent.
                     If the patient already has an open order on this script, show
                     its status instead of the order button (no double-ordering). --}}
                @if($journey->status !== 'renewal_due')
                    @php $activeOrder = $this->activeOrdersByJourney->get($journey->id); @endphp
                    @if($activeOrder)
                        @php $badge = $this->orderStatusBadge($activeOrder); @endphp
                        <div class="mt-4 rounded-xl border {{ $badge['classes'] }} p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold">{{ $badge['label'] }}</span>
                                <span class="inline-flex items-center rounded-full border {{ $badge['classes'] }} px-2 py-0.5 text-xs font-medium">
                                    {{ $activeOrder->reference }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-600">
                                {{ $activeOrder->modeLabel() }} @if($activeOrder->pharmacy) &middot; {{ $activeOrder->pharmacy->name }} @endif
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
                                @switch($activeOrder->status)
                                    @case('ready')
                                        Your order is ready — pop in to your SPAR pharmacy to collect.
                                        @break
                                    @case('preparing')
                                        Your SPAR pharmacy is preparing your order. We'll let you know when it's ready.
                                        @break
                                    @default
                                        Your SPAR pharmacy has received your order and will prepare it shortly.
                                @endswitch
                            </p>
                        </div>
                    @else
                        @php $modes = $this->orderModesFor($journey); @endphp
                        <div class="mt-4">
                            @if($orderingJourneyId === $journey->id)
                                <div class="rounded-xl border border-green-200 bg-green-50 p-3">
                                    <p class="text-sm font-semibold text-gray-800 mb-2">How would you like it?</p>
                                    @if($error)<p class="text-sm text-red-600 mb-2">{{ $error }}</p>@endif
                                    <select wire:model="orderMode"
                                            class="w-full rounded-xl border border-gray-300 bg-white py-2.5 px-3 text-sm mb-3">
                                        <option value="">Choose an option…</option>
                                        @foreach($modes as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="placeOrder"
                                                class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl py-2.5 text-sm">
                                            Confirm order
                                        </button>
                                        <button type="button" wire:click="cancelOrder"
                                                class="px-4 text-gray-500 text-sm">Cancel</button>
                                    </div>
                                </div>
                            @else
                                <button type="button" wire:click="startOrder({{ $journey->id }})"
                                        class="w-full rounded-xl border border-green-600 text-green-700 hover:bg-green-50 font-semibold py-2.5 text-sm">
                                    Order next meds
                                </button>
                            @endif
                        </div>
                    @endif
                @endif

                @if($journey->status === 'renewal_due' && config('spar.online_consult.enabled') && config('spar.online_consult.url'))
                    <div class="mt-4">
                        <a href="{{ config('spar.online_consult.url') }}" target="_blank" rel="noopener"
                           class="block w-full text-center bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl py-3">
                            {{ config('spar.online_consult.label', 'Book a ZapMed online consult') }}
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
            $mineCount = $this->journeys->count();
            $renewalCount = $this->journeys->filter(fn ($j) => $j->status === 'renewal_due')->count();
        @endphp
        @if($mineCount === 0)
            <div x-show="filter === 'mine'" class="bg-gray-50 rounded-xl p-6 text-center text-sm text-gray-600">
                No prescriptions on your own profile.
            </div>
        @endif

        {{-- DEPENDANTS (spec FR-D / decision D1): dependants are LISTED under the
             main member, but their medication is PRIVATE to them — the primary
             sees the name + a masked placeholder only, never their scripts. --}}
        <div x-show="filter === 'dependants'">
            @forelse($this->dependants as $dependant)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </span>
                            <div>
                                <p class="font-semibold text-gray-900">
                                    {{ $dependant->first_name ?: 'Dependant #'.$dependant->dependent_code }}
                                </p>
                                <p class="text-xs text-gray-500 flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    {{ $dependant->first_name ? $dependant->first_name."'s" : 'This' }} medication — private
                                </p>
                            </div>
                        </div>
                        <span class="text-xs text-gray-400">Dependant</span>
                    </div>
                </div>
            @empty
                <div class="bg-gray-50 rounded-xl p-6 text-center text-sm text-gray-600">
                    No dependants are listed on your profile.
                </div>
            @endforelse
            <p class="text-xs text-gray-400 text-center mb-4">
                For privacy, each dependant's medication is only visible to them.
            </p>
        </div>

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
                        <div class="mb-3 flex items-center justify-center gap-2">
                            <span class="text-xs text-gray-400">Powered by</span>
                            <img src="{{ asset('img/zapmed-logo.png') }}" alt="ZapMed"
                                 width="392" height="108"
                                 class="h-6 w-auto max-w-[120px] object-contain">
                        </div>
                        <a href="{{ config('spar.online_consult.url') }}" target="_blank" rel="noopener"
                           class="block w-full text-center bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl py-3 mb-1">
                            {{ config('spar.online_consult.label', 'Book a ZapMed online consult') }}
                        </a>
                        <p class="text-xs text-gray-500 text-center mb-2">
                            Renew online in minutes at
                            <span class="font-medium text-green-700">{{ \Illuminate\Support\Str::of(config('spar.online_consult.url'))->replace(['https://','http://','www.'], '') }}</span>
                            — no GP visit needed.
                        </p>
                        <p class="text-xs text-gray-400 text-center">Prefer your own doctor? Get a new script, then visit
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
