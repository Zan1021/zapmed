{{--
    Booking journey (/start/{slug}) - App\Livewire\Patient\BookingJourney.
    Markup and class names follow Craig's final handoff (26 Nov 2026, weight-loss/assessment/journey.js) so his
    stylesheet (resources/css/zapmed-journey.css, scoped to .zj) applies as designed.
--}}
@php
    $stageNames = ['Assessment', 'Review', 'Your details', 'Book', 'Pay'];
    $stageIndex = ['assessment' => 0, 'review' => 1, 'details' => 2, 'book' => 3, 'pay' => 4][$stage] ?? 0;
    $screen = $stage === 'assessment' ? $this->currentScreen() : null;
    $progress = $this->progress;
    $inQuestions = $stage === 'assessment' && $screen;
    $pct = $inQuestions ? $progress['percent'] : ($stageIndex + 1) * 20;
    $feeRand = 'R' . number_format($feeCents / 100, 0);
    $tdata = collect(config('treatments', []))->pluck('treatments')->collapse()->get($slug, []);
    $minutes = (int) ($tdata['duration'] ?? 30);
    $consultLabel = ! empty($tdata['fixed_consultation']) ? 'First consultation' : 'Consultation';
    $doc = $stage === 'pay' ? $this->selected_doctor : null;
    if ($stage === 'pay' && ! $doc && $selectedDoctorId) {
        $u = \App\Models\User::with('doctorProfile')->find($selectedDoctorId);
        $doc = $u ? ['user_id' => $u->id, 'name' => 'Dr ' . trim($u->first_name . ' ' . $u->last_name), 'photo' => $u->avatar_url] : null;
    }
@endphp

<main class="zonboarding-page">
<div id="onboardingBody">

    <div class="quizhead">
        <a href="/weight-loss" aria-label="Zapmed home"><img class="zonboarding-logo" src="/zapmed-v3/weight-loss/assessment/assets/zapmed-logo.png" alt="Zapmed"></a>
        <button type="button" class="iconbtn" aria-label="Close" onclick="location.href='/{{ $slug === 'weight-loss' ? 'weight-loss' : '' }}'">&times;</button>
    </div>

    <div class="zjourney-progress">
        <div class="zstage-labels">
            @foreach($stageNames as $i => $label)
                <span class="{{ $i === $stageIndex ? 'active' : '' }} {{ $i < $stageIndex ? 'done' : '' }}">{{ $label }}</span>
            @endforeach
        </div>
        <div class="zprogress-copy">
            <b>{{ $inQuestions ? 'Question ' . $progress['current'] . ' of ' . $progress['total'] : $stageNames[$stageIndex] }}</b>
            <span>{{ $pct }}%</span>
        </div>
        <div class="zprogress-track"><i style="width:{{ $pct }}%"></i></div>
    </div>

    <div class="quizmain {{ $stage === 'assessment' ? 'assessment' : '' }} {{ $stage === 'review' ? 'review' : '' }} {{ $stage === 'details' ? 'details' : '' }} {{ $stage === 'book' ? 'wide' : '' }}">

        {{-- ─────────────── ASSESSMENT ─────────────── --}}
        @if($inQuestions)
            @php
                $q = $screen['q'];
                $children = $screen['children'];
                $grouped = ! empty($q['screen_title']);
                $title = $q['screen_title'] ?? ($q['label'] ?? '');
                $showContinue = ! (($q['type'] ?? '') === 'radio' && empty($children)) && ($q['type'] ?? '') !== 'stop';
                $why = $q['why_we_ask'] ?? null;
            @endphp

            <div class="eyebrow">Question {{ $progress['current'] }} of {{ $progress['total'] }}</div>
            <h2>{{ $title }}</h2>

            @if(! empty($q['helper']) && ($q['type'] ?? '') !== 'medicines')
                <p>{{ $q['helper'] }}</p>
            @endif

            @include('livewire.patient.partials.journey-field', ['q' => $grouped ? $q + ['_sub' => true] : $q, 'answers' => $answers, 'child' => false])
            @foreach($children as $child)
                @include('livewire.patient.partials.journey-field', ['q' => $child, 'answers' => $answers, 'child' => true])
            @endforeach

            <div class="actions">
                @if($showContinue)
                    <button type="button" class="btn" wire:click="nextQuestion">Continue</button>
                @endif
                @if(($q['type'] ?? '') === 'medicines')
                    <button type="button" class="btn secondary" data-nz="skip" wire:click="skipQuestion">SKIP</button>
                @endif
                @if($cursor > 0)
                    <button type="button" class="btn secondary" data-nz="back" wire:click="prevQuestion">Back</button>
                @else
                    <a class="btn secondary" data-nz="back" href="/weight-loss">Back</a>
                @endif
            </div>

            @if($why)
                <div class="zwhy"><b>Why we ask</b><span>{{ $why }}</span></div>
            @endif

        {{-- ─────────────── REVIEW ─────────────── --}}
        @elseif($stage === 'review')
            <div class="eyebrow">Review</div>
            <h2>Check your answers</h2>
            <p>Please check your answers carefully. Your doctor uses this information to help decide which options may suit you.</p>

            <div class="zreview">
                @foreach($this->reviewRows() as $row)
                    <button type="button" wire:key="rv-{{ $row['index'] }}" wire:click="editScreen({{ $row['index'] }})">
                        <span>{{ $row['title'] }}</span><b>{{ $row['answer'] }}</b><em>Edit</em>
                    </button>
                @endforeach
            </div>

            <section class="zconsent-group">
                <h3>Before you continue</h3>
                <label class="zconsent-check">
                    <input type="checkbox" wire:model.live="reviewTruth"><i></i>
                    <span>I am asking for treatment for myself only, and everything I have told you is true and complete.</span>
                </label>
                <label class="zconsent-check">
                    <input type="checkbox" wire:model.live="reviewSideEffects"><i></i>
                    <span>I understand medicines can have side effects. I will read the leaflet that comes with mine.</span>
                </label>
            </section>
            @error('review')<p class="error" role="alert">{{ $message }}</p>@enderror

            <button type="button" class="btn" wire:click="toDetails" @disabled(! ($reviewTruth && $reviewSideEffects))>Continue</button>
            <button type="button" class="btn secondary" data-nz="back" wire:click="editScreen({{ max(count($this->screens()) - 1, 0) }})">Back</button>

        {{-- ─────────────── YOUR DETAILS + SMS CODE ─────────────── --}}
        @elseif($stage === 'details')
            @if(! $otpSent)
                <div class="eyebrow">ALMOST THERE</div>
                <h2>Where should we send your booking?</h2>

                <label for="zj-name">Name</label>
                <input id="zj-name" type="text" wire:model="name" placeholder="Your full name" autocomplete="name">
                @error('name')<p class="error" role="alert">{{ $message }}</p>@enderror

                <label for="zj-email">Email address</label>
                <input id="zj-email" type="email" wire:model="email" placeholder="you@example.com" autocomplete="email">
                @error('email')<p class="error" role="alert">{{ $message }}</p>@enderror

                <label for="zj-cell">Cell number</label>
                <div class="zphone"><span>+27</span><input id="zj-cell" wire:model.blur="cell" placeholder="82 123 4567" inputmode="numeric" maxlength="11" autocomplete="tel-national"></div>
                @error('cell')<p class="error" role="alert">{{ $message }}</p>@enderror

                <label for="zj-id">South African ID number</label>
                <input id="zj-id" wire:model="idNumber" maxlength="13" inputmode="numeric" placeholder="13-digit ID number">
                @error('idNumber')<p class="error" role="alert">{{ $message }}</p>@enderror
                <label class="zcheck"><input type="checkbox" wire:model.live="noId"><span>I don't have a South African ID</span></label>

                <h3>Preferred booking communication</h3>
                <div class="zoption-grid zsmall-options">
                    <label><input type="radio" wire:model="communication" value="WhatsApp"><span><b>WhatsApp</b></span></label>
                    <label><input type="radio" wire:model="communication" value="email"><span><b>Email</b></span></label>
                </div>

                <div class="zaccount-setup">
                    <h3>Secure your account</h3>
                    <p>Set a password now. After your booking, mandatory two-factor authentication protects account access.</p>
                    <label for="zj-pw">Create password</label>
                    <input id="zj-pw" type="password" wire:model="password" placeholder="At least 10 characters" autocomplete="new-password">
                    @error('password')<p class="error" role="alert">{{ $message }}</p>@enderror
                    <label for="zj-pw2">Confirm password</label>
                    <input id="zj-pw2" type="password" wire:model="passwordConfirm" placeholder="Repeat your password" autocomplete="new-password">
                </div>

                <section class="zconsent-group zdetails-consent">
                    <label class="zconsent-check">
                        <input type="checkbox" wire:model.live="consentCare"><i></i>
                        <span>I agree to the <a href="/terms" target="_blank" rel="noopener">Terms and Conditions</a> and the <a href="/privacy-policy" target="_blank" rel="noopener">Privacy Policy</a>. I consent to Zapmed holding and processing my personal and health information to provide my care.</span>
                    </label>
                    @error('consentCare')<p class="error" role="alert">{{ $message }}</p>@enderror
                    <div class="zconsent-divider"></div>
                    <label class="zconsent-check">
                        <input type="checkbox" wire:model.live="consentMarketing"><i></i>
                        <span>Keep me updated by email, WhatsApp and SMS about Zapmed treatments, offers and health tips. I can unsubscribe at any time. <small>Optional</small></span>
                    </label>
                </section>

                <button type="button" class="btn" wire:click="sendOtp" wire:loading.attr="disabled" @disabled(! $consentCare)>Choose your doctor</button>
                <button type="button" class="btn secondary" data-nz="back" wire:click="$set('stage','review')">Back</button>
            @else
                <div class="eyebrow">ALMOST THERE</div>
                <h2>Confirm it's you</h2>
                <p>We sent a 6-digit code by SMS to <b>+27 {{ $cell }}</b>. Enter it to continue.</p>
                <input type="text" wire:model="otp" inputmode="numeric" maxlength="6" placeholder="______" autocomplete="one-time-code" style="text-align:center;letter-spacing:.4em;font-size:24px;max-width:260px;margin:0 auto 16px;display:block">
                @error('otp')<p class="error" role="alert" style="text-align:center">{{ $message }}</p>@enderror
                <div class="actions">
                    <button type="button" class="btn" wire:click="verifyOtp">Verify &amp; continue</button>
                    <button type="button" class="btn secondary" wire:click="sendOtp">Resend code</button>
                </div>
            @endif

        {{-- ─────────────── BOOK ─────────────── --}}
        @elseif($stage === 'book')
            @php $options = $this->doctor_options; @endphp
            <div class="eyebrow">Book</div>
            <h2>To find your soonest available booking &mdash; <button type="button" class="earliest" wire:click="bookSoonest">Book</button></h2>
            <h3>Or choose your preferred specialist</h3>
            @error('selectedTime')<p class="error" role="alert">{{ $message }}</p>@enderror

            <div class="zdoctors">
                @forelse($options as $o)
                    <article class="zdoctor {{ $selectedDoctorId === $o['user_id'] ? 'selected' : '' }}" wire:key="doc-{{ $o['user_id'] }}">
                        @if($o['photo'])<img class="doctor-portrait" src="{{ $o['photo'] }}" alt="{{ $o['name'] }}">@endif
                        <h3>{{ $o['name'] }}</h3>
                        @if($o['qualification'])<p class="credentials">{{ $o['qualification'] }}</p>@endif
                        @if(! empty($o['bio']))
                            <details class="doctor-bio"><summary>Read more</summary><p>{{ $o['bio'] }}</p></details>
                        @endif
                        @forelse($o['days'] as $day)
                            @php
                                $dayKey = $o['user_id'] . '|' . $day['date'];
                                $open = in_array($dayKey, $expandedDays, true);
                                $shown = $open ? $day['slots'] : array_slice($day['slots'], 0, 8);
                            @endphp
                            <div class="zday">
                                <b>{{ $day['label'] }}</b>
                                <div class="slots">
                                    @foreach($shown as $time)
                                        <button type="button" wire:key="s-{{ $o['user_id'] }}-{{ $day['date'] }}-{{ $time }}"
                                                class="slot {{ $selectedDoctorId === $o['user_id'] && $selectedDate === $day['date'] && $selectedTime === $time ? 'selected' : '' }}"
                                                wire:click="selectSlot({{ $o['user_id'] }}, '{{ $day['date'] }}', '{{ $time }}')">{{ $time }}</button>
                                    @endforeach
                                </div>
                                @if(count($day['slots']) > 8)
                                    <button type="button" class="textlink" wire:click="toggleDay('{{ $dayKey }}')">{{ $open ? 'Show fewer times' : 'Show all ' . count($day['slots']) . ' times' }}</button>
                                @endif
                            </div>
                        @empty
                            <p class="fine">No appointments in the next two weeks.</p>
                        @endforelse
                    </article>
                @empty
                    <p>No doctors are taking bookings right now. Please try again soon.</p>
                @endforelse
            </div>

            <div class="zbooking-options">
                <h3>How should the doctor contact you?</h3>
                <div class="zoption-grid">
                    <label><input type="radio" wire:model.live="mode" value="video"><span><b>Video consultation</b><small>We send you a secure link.</small></span></label>
                    <label><input type="radio" wire:model.live="mode" value="telephone"><span><b>Telephone consultation</b><small>The doctor calls you.</small></span></label>
                </div>
                @if($mode === 'telephone')
                    <label for="zj-phone">Number for the doctor to call</label>
                    <div class="zphone"><span>+27</span><input id="zj-phone" wire:model.blur="contactPhone" inputmode="numeric" maxlength="11" placeholder="82 123 4567"></div>
                    <label class="zcheck"><input type="checkbox" wire:model="phoneConfirmed"><span>I confirm this is the correct number for my consultation.</span></label>
                    @error('contactPhone')<p class="error" role="alert">{{ $message }}</p>@enderror
                @endif
            </div>

            <div class="zstickybook">
                <span>
                    @if($selectedDoctorId && $selectedTime && ($sel = $this->selected_doctor))
                        {{ $sel['name'] }}, {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('l j F') }}, {{ $selectedTime }} &middot; {{ $consultLabel }} &middot; {{ $mode === 'telephone' ? 'Telephone consultation' : 'Video consultation' }}
                    @else
                        Choose a time to continue
                    @endif
                </span>
                <button type="button" class="btn" wire:click="toPay" @disabled(! ($selectedDoctorId && $selectedTime))>Continue to payment</button>
            </div>
            <button type="button" class="btn secondary" data-nz="back" wire:click="$set('stage','details')">Back</button>

        {{-- ─────────────── PAY ─────────────── --}}
        @elseif($stage === 'pay')
            <div class="eyebrow">PAYMENT CONFIRMATION</div>
            <h2>Confirm your consultation</h2>

            <div class="zbooksummary">
                @if(! empty($doc['photo']))<img class="doctor-portrait" src="{{ $doc['photo'] }}" alt="{{ $doc['name'] ?? 'Your doctor' }}">@endif
                <div>
                    <h3>{{ $doc['name'] ?? 'Your doctor' }}</h3>
                    <p>{{ \Illuminate\Support\Carbon::parse($selectedDate)->format('l j F') }} &middot; {{ $selectedTime }}<br>
                       {{ $mode === 'telephone' ? 'Telephone consultation' : 'Video consultation' }} &middot; {{ $consultLabel }} &middot; {{ $minutes }} minutes</p>
                    <button type="button" class="textlink" wire:click="changeBooking">Change</button>
                </div>
            </div>

            <div class="zpriceblock">
                <div class="lineitem"><span>Consultation fee</span><b>{{ $feeRand }}</b></div>
                <p>Treatment, if prescribed, costs extra. A prescription is not guaranteed.</p>
                <label class="zconsent-check">
                    <input type="checkbox" wire:model.live="prescribingConsent"><i></i>
                    <span>I understand my doctor prescribes based on my answers, and only when treatment is safe and right for me. A prescription is not guaranteed.</span>
                </label>
            </div>

            <div class="zpayplaceholder">
                <b>Secure payment</b>
                <p>You will pay {{ $feeRand }} on PayFast's secure page. Your card details never touch Zapmed.</p>
            </div>
            @error('pay')<p class="error" role="alert">{{ $message }}</p>@enderror

            <button type="button" class="btn" data-nz="pay" wire:click="confirmAndPay" wire:loading.attr="disabled" @disabled(! $prescribingConsent)>Pay &amp; Confirm Booking</button>
            <button type="button" class="btn secondary" data-nz="back" wire:click="changeBooking">Back</button>
        @endif

        @error('answer')<p id="nzerror" role="alert" class="error">{{ $message }}</p>@enderror
    </div>
</div>
</main>
