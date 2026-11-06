{{--
    Onboarding / charge-at-booking journey — App\Livewire\Patient\BookingJourney.
    Styled entirely with Tailwind utilities bound to the shared moodboard tokens
    (brand = dark teal #203E3E, teal-500 = primary #2E767F, ivory, font-serif = Prata,
    font-sans = Manrope). One centred card, slim progress bar, large tappable choices.
--}}
@php
    $stages = ['assessment' => 'Assessment','review' => 'Review','details' => 'Your details','book' => 'Book','pay' => 'Pay'];
    $stageKeys = array_keys($stages);
    $activeIndex = array_search($stage === 'confirm' ? 'pay' : $stage, $stageKeys, true);
    $feeRand = 'R' . number_format($feeCents / 100, 0);

    // shared control classes (kept in one place so every field/button matches)
    $inputCls  = 'w-full rounded-xl border border-teal-200 bg-white px-4 py-3 text-brand placeholder-teal-400/70 shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500/30 focus:outline-none transition';
    $primaryCls = 'inline-flex w-full items-center justify-center rounded-full bg-brand px-6 py-3.5 text-base font-semibold text-ivory shadow-sm hover:bg-brand-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-500 transition disabled:opacity-50 disabled:cursor-not-allowed';
    $ghostCls   = 'inline-flex items-center justify-center rounded-full border border-teal-300 bg-white px-5 py-2.5 text-sm font-semibold text-teal-700 hover:bg-teal-50 transition';
    $errCls     = 'mt-2 text-sm font-medium text-red-600';
@endphp

<div class="mx-auto w-full max-w-2xl px-4 sm:px-6 pb-20 pt-6">

    {{-- top bar: Back (left) only --}}
    <div class="flex items-center">
        <div class="min-w-20">
            @if($stage === 'assessment' && $this->progress['current'] > 1)
                <button type="button" class="{{ $ghostCls }}" wire:click="prevQuestion">
                    <span aria-hidden="true" class="mr-1">&larr;</span> Back
                </button>
            @elseif($stage === 'review')
                <button type="button" class="{{ $ghostCls }}" wire:click="$set('stage','assessment')"><span aria-hidden="true" class="mr-1">&larr;</span> Back</button>
            @elseif($stage === 'details')
                <button type="button" class="{{ $ghostCls }}" wire:click="$set('stage','review')"><span aria-hidden="true" class="mr-1">&larr;</span> Back</button>
            @elseif($stage === 'book')
                <button type="button" class="{{ $ghostCls }}" wire:click="$set('stage','details')"><span aria-hidden="true" class="mr-1">&larr;</span> Back</button>
            @elseif($stage === 'pay')
                <button type="button" class="{{ $ghostCls }}" wire:click="$set('stage','book')"><span aria-hidden="true" class="mr-1">&larr;</span> Back</button>
            @endif
        </div>
    </div>

    {{-- stage stepper — each step uses a distinct brand colour from the moodboard --}}
    @php
        // distinct website colours per stage (moodboard palette)
        $stageColors = [
            'assessment' => '#203E3E', // dark teal
            'review'     => '#2E767F', // primary teal
            'details'    => '#729D94', // soft sage
            'book'       => '#617D8D', // steel
            'pay'        => '#A8BEC8', // dusty blue
        ];
    @endphp
    <ol class="mt-6 flex items-center gap-2" aria-label="Progress">
        @foreach($stages as $key => $label)
            @php
                $i = array_search($key, $stageKeys, true);
                $isActive = $i === $activeIndex;
                $isDone   = $i < $activeIndex;
                $reached  = $isActive || $isDone;
                $color    = $stageColors[$key] ?? '#2E767F';
            @endphp
            <li class="flex flex-1 flex-col items-center gap-1.5">
                <span class="h-1.5 w-full rounded-full transition-colors"
                      style="background-color: {{ $reached ? $color : '#E3E8E3' }}"></span>
                <span class="text-[11px] font-semibold uppercase tracking-wide {{ $isActive ? '' : 'text-teal-300' }}"
                      @if($isActive) style="color: {{ $color }}" @endif>{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    {{-- the card --}}
    <div class="mt-6 rounded-3xl border border-teal-100 bg-white p-6 shadow-sm sm:p-10">

        {{-- ─────────────── ASSESSMENT ─────────────── --}}
        @if($stage === 'assessment' && $this->currentQuestion)
            @php $q = $this->currentQuestion; $id = $q['id']; $type = $q['type'] ?? 'text'; @endphp

            {{-- per-question progress --}}
            <div class="mb-6">
                <div class="mb-1.5 flex items-center justify-between text-sm font-semibold text-teal-700">
                    <span>Question {{ $this->progress['current'] }} of {{ $this->progress['total'] }}</span>
                    <span>{{ $this->progress['percent'] }}%</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-teal-100">
                    <div class="h-full rounded-full bg-brand-gradient transition-all duration-300" style="width: {{ $this->progress['percent'] }}%"></div>
                </div>
            </div>

            <h2 class="font-serif text-2xl leading-snug text-brand sm:text-3xl">{{ $q['label'] ?? '' }}</h2>

            @error('answer') <p class="{{ $errCls }}">{{ $message }}</p> @enderror

            <div class="mt-6">
                @if($type === 'number')
                    <div class="flex items-center gap-3">
                        <input type="number" wire:model="answers.{{ $id }}" placeholder="{{ $q['placeholder'] ?? '' }}" inputmode="numeric" class="{{ $inputCls }} max-w-40">
                        @if(!empty($q['unit']))<span class="text-teal-700">{{ $q['unit'] }}</span>@endif
                    </div>

                @elseif($type === 'measurements')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-semibold text-teal-700">Height <span class="font-normal text-teal-400">(cm)</span>
                            <input type="number" wire:model="answers.height" inputmode="numeric" class="{{ $inputCls }} mt-1.5">
                        </label>
                        <label class="block text-sm font-semibold text-teal-700">Weight <span class="font-normal text-teal-400">(kg)</span>
                            <input type="number" wire:model="answers.weight" inputmode="numeric" class="{{ $inputCls }} mt-1.5">
                        </label>
                    </div>

                @elseif(in_array($type, ['single','radio']))
                    <div class="grid gap-3">
                        @foreach(($q['options'] ?? []) as $opt)
                            @php $label = is_array($opt) ? ($opt['label'] ?? $opt['code'] ?? '') : $opt; $val = is_array($opt) ? ($opt['code'] ?? $label) : $opt; $sel = ($answers[$id] ?? null) === $val; @endphp
                            <button type="button"
                                    wire:click="$set('answers.{{ $id }}', @js($val))"
                                    class="flex w-full items-center justify-between rounded-2xl border px-5 py-4 text-left text-base transition {{ $sel ? 'border-teal-500 bg-teal-50 text-brand ring-1 ring-teal-500' : 'border-teal-200 bg-white text-brand hover:border-teal-400 hover:bg-teal-50/50' }}">
                                <span>{{ $label }}</span>
                                <span class="ml-3 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border {{ $sel ? 'border-teal-500 bg-teal-500 text-white' : 'border-teal-300' }}">@if($sel)&#10003;@endif</span>
                            </button>
                        @endforeach
                    </div>

                @elseif(in_array($type, ['multi','checkbox','checklist']))
                    <div class="grid gap-3">
                        @foreach(($q['options'] ?? []) as $opt)
                            @php $label = is_array($opt) ? ($opt['label'] ?? $opt['code'] ?? '') : $opt; $val = is_array($opt) ? ($opt['code'] ?? $label) : $opt; @endphp
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-teal-200 bg-white px-5 py-4 text-base text-brand transition hover:border-teal-400 hover:bg-teal-50/50 has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50 has-[:checked]:ring-1 has-[:checked]:ring-teal-500">
                                <input type="checkbox" wire:model="answers.{{ $id }}" value="{{ $val }}" class="h-5 w-5 rounded border-teal-300 text-teal-500 focus:ring-teal-500/40">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>

                @elseif($type === 'date')
                    {{-- Month + Year pickers (people rarely recall the exact day). Stored as "Month YYYY". --}}
                    @php
                        $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                        $thisYear = (int) date('Y');
                        $years = range($thisYear, $thisYear - 80);
                    @endphp
                    <div class="grid grid-cols-2 gap-4 max-w-md">
                        <label class="block text-sm font-semibold text-teal-700">Month
                            <select wire:model="answers.{{ $id }}_month" class="{{ $inputCls }} mt-1.5">
                                <option value="">Select month</option>
                                @foreach($months as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-teal-700">Year
                            <select wire:model="answers.{{ $id }}_year" class="{{ $inputCls }} mt-1.5">
                                <option value="">Select year</option>
                                @foreach($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach
                            </select>
                        </label>
                    </div>

                @elseif($type === 'select')
                    <div class="max-w-md">
                        <select wire:model="answers.{{ $id }}" class="{{ $inputCls }}">
                            <option value="">{{ $q['placeholder'] ?? 'Select an option' }}</option>
                            @foreach(($q['options'] ?? []) as $opt)
                                @php $label = is_array($opt) ? ($opt['label'] ?? $opt['code'] ?? '') : $opt; $val = is_array($opt) ? ($opt['code'] ?? $label) : $opt; @endphp
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                @else
                    <textarea wire:model="answers.{{ $id }}" rows="4" placeholder="{{ $q['placeholder'] ?? 'Type your answer' }}" class="{{ $inputCls }}"></textarea>
                @endif
            </div>

            <div class="mt-8">
                <button type="button" class="{{ $primaryCls }}" wire:click="nextQuestion">
                    {{ $this->progress['current'] === $this->progress['total'] ? 'Review answers' : 'Continue' }}
                </button>
            </div>

            @if(!empty($q['why_we_ask']) || !empty($q['helper_text']))
                <div class="mt-6 rounded-2xl bg-ivory px-5 py-4 text-sm text-teal-700">
                    <p class="font-semibold text-brand">Why we ask</p>
                    <p class="mt-1">{{ $q['why_we_ask'] ?? $q['helper_text'] }}</p>
                </div>
            @endif

        {{-- ─────────────── REVIEW ─────────────── --}}
        @elseif($stage === 'review')
            <p class="text-xs font-semibold uppercase tracking-widest text-teal-500">Review</p>
            <h2 class="mt-2 font-serif text-2xl text-brand sm:text-3xl">Check your answers.</h2>
            <p class="mt-2 text-teal-700">Please review before you continue. You can edit anything here.</p>

            <div class="mt-6 divide-y divide-teal-100">
                @foreach($this->visibleQuestions() as $q)
                    @php $a = $answers[$q['id']] ?? null; if($a === null || $a === '' || $a === []) continue; @endphp
                    <div class="flex items-start justify-between gap-4 py-4">
                        <div class="min-w-0">
                            <p class="font-semibold text-brand">{{ $q['label'] ?? $q['id'] }}</p>
                            <p class="mt-0.5 text-teal-700">{{ is_array($a) ? implode(', ', $a) : $a }}</p>
                        </div>
                        <button type="button" class="{{ $ghostCls }} shrink-0" wire:click="editAnswer('{{ $q['id'] }}')">Edit</button>
                    </div>
                @endforeach
            </div>

            <div class="mt-8">
                <button type="button" class="{{ $primaryCls }}" wire:click="toDetails">Continue</button>
            </div>

        {{-- ─────────────── YOUR DETAILS + OTP ─────────────── --}}
        @elseif($stage === 'details')
            <p class="text-xs font-semibold uppercase tracking-widest text-teal-500">Your details</p>
            <h2 class="mt-2 font-serif text-2xl text-brand sm:text-3xl">A few details to secure your booking.</h2>

            @if(!$otpSent)
                <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-semibold text-teal-700">First name
                        <input type="text" wire:model="firstName" class="{{ $inputCls }} mt-1.5">
                    </label>
                    <label class="block text-sm font-semibold text-teal-700">Last name
                        <input type="text" wire:model="lastName" class="{{ $inputCls }} mt-1.5">
                    </label>
                    <label class="block text-sm font-semibold text-teal-700 sm:col-span-2">Email
                        <input type="email" wire:model="email" class="{{ $inputCls }} mt-1.5">
                    </label>
                    <label class="block text-sm font-semibold text-teal-700 sm:col-span-2">Mobile number
                        <input type="tel" wire:model="cell" inputmode="tel" class="{{ $inputCls }} mt-1.5">
                    </label>
                </div>
                @error('firstName')<p class="{{ $errCls }}">{{ $message }}</p>@enderror
                @error('email')<p class="{{ $errCls }}">{{ $message }}</p>@enderror
                @error('cell')<p class="{{ $errCls }}">{{ $message }}</p>@enderror

                <div class="mt-6 space-y-4">
                    <label class="flex cursor-pointer items-start gap-3 text-sm text-brand">
                        <input type="checkbox" wire:model="consentCare" class="mt-0.5 h-5 w-5 rounded border-teal-300 text-teal-500 focus:ring-teal-500/40">
                        <span>I agree to the privacy notice and telehealth care terms.</span>
                    </label>
                    @error('consentCare')<p class="{{ $errCls }}">{{ $message }}</p>@enderror
                    <label class="flex cursor-pointer items-start gap-3 text-sm text-teal-700">
                        <input type="checkbox" wire:model="consentMarketing" class="mt-0.5 h-5 w-5 rounded border-teal-300 text-teal-500 focus:ring-teal-500/40">
                        <span>Send me occasional health tips and offers (optional).</span>
                    </label>
                </div>

                <div class="mt-8">
                    <button type="button" class="{{ $primaryCls }}" wire:click="sendOtp">Send verification code</button>
                </div>
            @else
                <p class="mt-4 text-teal-700">We sent a 6-digit code to <b class="text-brand">{{ $cell }}</b>. Enter it to continue.</p>
                <div class="mt-5 flex justify-center">
                    <input type="text" wire:model="otp" inputmode="numeric" maxlength="6" placeholder="______"
                           class="{{ $inputCls }} max-w-56 text-center text-2xl tracking-[0.5em]">
                </div>
                @error('otp')<p class="{{ $errCls }} text-center">{{ $message }}</p>@enderror
                <div class="mt-8 space-y-3">
                    <button type="button" class="{{ $primaryCls }}" wire:click="verifyOtp">Verify &amp; continue</button>
                    <button type="button" class="{{ $ghostCls }} w-full" wire:click="sendOtp">Resend code</button>
                </div>
            @endif

        {{-- ─────────────── BOOK (doctor + slot) ─────────────── --}}
        @elseif($stage === 'book')
            <p class="text-xs font-semibold uppercase tracking-widest text-teal-500">Book</p>
            <h2 class="mt-2 font-serif text-2xl text-brand sm:text-3xl">Choose your appointment.</h2>

            <label class="mt-6 block text-sm font-semibold text-teal-700">Date
                <input type="date" wire:model.live="selectedDate" min="{{ now()->addDay()->format('Y-m-d') }}" max="{{ now()->addDays(14)->format('Y-m-d') }}" class="{{ $inputCls }} mt-1.5 max-w-xs">
            </label>

            <p class="mt-6 font-semibold text-brand">Available times</p>
            <div class="mt-3 grid grid-cols-3 gap-2.5 sm:grid-cols-4">
                @forelse($this->availableSlots as $slot)
                    <button type="button" wire:click="selectTime('{{ $slot }}')"
                            class="rounded-xl border px-2 py-3 text-sm font-semibold transition {{ $selectedTime === $slot ? 'border-teal-500 bg-brand text-ivory' : 'border-teal-200 bg-white text-teal-700 hover:border-teal-400 hover:bg-teal-50' }}">
                        {{ $slot }}
                    </button>
                @empty
                    <p class="col-span-full text-sm text-teal-700">No times available on this date — try another day.</p>
                @endforelse
            </div>
            @error('selectedTime')<p class="{{ $errCls }}">{{ $message }}</p>@enderror

            @if($selectedTime)
                <p class="mt-6 font-semibold text-brand">Choose your doctor</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @forelse($this->doctorsForSlot as $doc)
                        <button type="button" wire:click="selectDoctor({{ $doc->user_id }})"
                                class="flex flex-col items-start rounded-2xl border px-5 py-4 text-left transition {{ $selectedDoctorId === $doc->user_id ? 'border-teal-500 bg-teal-50 ring-1 ring-teal-500' : 'border-teal-200 bg-white hover:border-teal-400 hover:bg-teal-50/50' }}">
                            <span class="font-semibold text-brand">Dr {{ $doc->user->last_name ?? '' }}</span>
                            <span class="text-sm text-teal-700">{{ $doc->specialisation ?? 'General Practitioner' }}</span>
                        </button>
                    @empty
                        <p class="text-sm text-teal-700">We'll assign the best available doctor for this slot.</p>
                    @endforelse
                </div>

                <p class="mt-6 font-semibold text-brand">Consultation mode</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-teal-200 bg-white px-5 py-4 text-brand transition hover:border-teal-400 has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50 has-[:checked]:ring-1 has-[:checked]:ring-teal-500">
                        <input type="radio" wire:model="mode" value="video" class="h-5 w-5 border-teal-300 text-teal-500 focus:ring-teal-500/40"><span>Video consultation</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-teal-200 bg-white px-5 py-4 text-brand transition hover:border-teal-400 has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50 has-[:checked]:ring-1 has-[:checked]:ring-teal-500">
                        <input type="radio" wire:model="mode" value="telephone" class="h-5 w-5 border-teal-300 text-teal-500 focus:ring-teal-500/40"><span>Telephone consultation</span>
                    </label>
                </div>
            @endif

            <div class="mt-8">
                <button type="button" class="{{ $primaryCls }}" wire:click="toPay">Continue to payment</button>
            </div>

        {{-- ─────────────── PAY ─────────────── --}}
        @elseif($stage === 'pay')
            <p class="text-xs font-semibold uppercase tracking-widest text-teal-500">Pay</p>
            <h2 class="mt-2 font-serif text-2xl text-brand sm:text-3xl">Secure your consultation.</h2>
            @error('pay')<p class="{{ $errCls }}">{{ $message }}</p>@enderror

            <div class="mt-6 rounded-2xl bg-ivory p-6">
                <p class="font-semibold text-brand">{{ $treatmentName }}</p>
                <p class="mt-1 text-teal-700">
                    {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('l, j F') }} · {{ $selectedTime }}<br>
                    {{ $mode === 'telephone' ? 'Telephone' : 'Video' }} consultation
                </p>
                <div class="mt-5 flex items-center justify-between border-t border-teal-200 pt-4">
                    <span class="text-teal-700">Consultation fee</span>
                    <b class="text-xl text-brand">{{ $feeRand }}</b>
                </div>
                <p class="mt-2 text-sm text-teal-700">Treatment, if prescribed, is charged separately.</p>
            </div>

            <p class="mt-4 text-sm text-teal-700">You'll pay {{ $feeRand }} now to complete this booking. Payment is processed securely by PayFast.</p>

            <div class="mt-8">
                <button type="button" class="{{ $primaryCls }}" wire:click="confirmAndPay">Confirm booking &amp; pay {{ $feeRand }}</button>
            </div>
        @endif

    </div>
</div>
