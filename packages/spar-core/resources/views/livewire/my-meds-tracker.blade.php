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
                <div class="relative overflow-hidden rounded-2xl">
                    @foreach($this->banners as $idx => $banner)
                        <div x-show="i === {{ $idx }}" x-transition.opacity class="w-full">
                            @if($banner->link_url)
                                <a href="{{ route('spar.banner.click', $banner->id) }}" target="_blank" rel="noopener">
                                    <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" loading="lazy" class="w-full object-cover" />
                                </a>
                            @else
                                <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" loading="lazy" class="w-full object-cover" />
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

        {{-- Permanent online-consultation button (config-driven external link).
             Lets a patient book an online consult for a NEW prescription any
             time — not only at renewal. Plain outbound link, no telehealth
             coupling (AC-4 preserved). --}}
        @if(config('spar.online_consult.enabled') && config('spar.online_consult.url'))
            <a href="{{ config('spar.online_consult.url') }}" target="_blank" rel="noopener"
               class="flex items-center justify-center gap-2 w-full text-center bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl py-3 mb-4">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                {{ config('spar.online_consult.label', 'Get a new prescription online') }}
            </a>
        @endif

        {{-- Renewal funnel card (spec FR-13). CTA varies by host mode. --}}
        @if($this->renewalDue)
            <div class="bg-white rounded-2xl shadow-sm border border-amber-200 p-5 mb-4">
                <h3 class="font-semibold text-gray-900 mb-1">Prescription renewal needed</h3>
                <p class="text-sm text-gray-600 mb-3">
                    Your prescription has reached its final repeat. To continue your medication
                    you'll need a new script.
                </p>
                @if(config('spar.host_mode', 'integrated') === 'integrated')
                    <a href="{{ config('app.url') }}" target="_blank"
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

        {{-- Journeys across the whole profile (self + dependants, spec FR-8). --}}
        @forelse($this->journeys as $journey)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-4">
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
                            <p class="text-xs text-gray-500">
                                For dependant: {{ $journey->patient->first_name ?: 'Dependant #'.$journey->patient->dependent_code }}
                            </p>
                        @endif
                    </div>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full
                        {{ $journey->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $journey->status === 'renewal_due' ? 'Renewal Due' : 'Active' }}
                    </span>
                </div>
                <p class="text-sm text-gray-600">
                    Dispenses: {{ $journey->dispenses_completed }} / {{ $journey->total_dispenses }}
                </p>
                @if(!empty($journey->medications))
                    <ul class="mt-2 text-sm text-gray-700 list-disc list-inside">
                        @foreach($journey->medications as $med)
                            <li>{{ $med['name'] ?? '' }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @empty
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
                <p class="text-amber-800">No active prescription found on your profile.</p>
                <p class="text-sm text-amber-600 mt-2">Contact your SPAR pharmacy for assistance.</p>
            </div>
        @endforelse

        <a href="{{ route('my-meds.history') }}" class="block text-center text-sm text-green-700 py-3">
            View full history
        </a>
    @endif
</div>
