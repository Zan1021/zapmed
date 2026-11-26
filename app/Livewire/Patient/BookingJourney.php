<?php

namespace App\Livewire\Patient;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\ConsentRecord;
use App\Models\DoctorProfile;
use App\Models\Payment;
use App\Models\User;
use App\Services\Assessments\AssessmentService;
use App\Services\PayFastService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Charge-at-booking onboarding JOURNEY (spec: .kiro/specs/charge-at-booking, restyled to Craig's final
 * 26 Nov handoff: .kiro/specs/craig-handoff-nov26).
 *
 * NB: distinct from App\Livewire\Patient\Onboarding (that is the patient PROFILE-completion flow at
 * /onboarding). This is the treatment booking journey at /start/{slug}.
 *
 * Stages: assessment -> review -> details (+ SMS code) -> book -> pay -> PayFast.
 * The fee is disclosed up front and CHARGED at confirmation (PayFast). Clinical questions come from the
 * question-bank engine (AssessmentService). Questions flagged layout=with_previous share a screen with the
 * question above them (follow-ups under "Yes", the family-history list under the personal-history list).
 *
 * Security: the fee is bound SERVER-SIDE; the SMS code is never in page state; an existing account is never
 * logged in from this public form (the visitor is asked to log in instead).
 */
class BookingJourney extends Component
{
    public string $slug;
    public string $treatmentName = '';
    public int $feeCents = 0;

    public string $stage = 'assessment'; // assessment|review|details|book|pay

    /** assessment */
    public array $answers = [];
    public int $cursor = 0;

    /** review consents */
    public bool $reviewTruth = false;
    public bool $reviewSideEffects = false;

    /** details + OTP */
    public string $name = '';
    public string $email = '';
    public string $cell = '';           // 9 digits after +27, shown as "82 123 4567"
    public string $idNumber = '';
    public bool $noId = false;
    public string $dateOfBirth = '';   // asked only when there is no usable South African ID number (the ID holds both)
    public string $gender = '';
    public string $communication = 'WhatsApp';
    public string $password = '';
    public string $passwordConfirm = '';
    public bool $consentCare = false;
    public bool $consentMarketing = false;
    public string $otp = '';
    public bool $otpSent = false;
    public bool $verified = false;

    /** book */
    public string $selectedDate = '';
    public string $selectedTime = '';
    public ?int $selectedDoctorId = null;
    public string $mode = 'video';
    public string $contactPhone = '';
    public bool $phoneConfirmed = false;
    /** "doctorId|date" keys whose full list of times is open */
    public array $expandedDays = [];

    /** pay */
    public bool $prescribingConsent = false;

    /** consent log (key, wording, granted, timestamp), flushed to consent_records once the user exists */
    public array $consentLog = [];

    /** persisted ids */
    public ?int $assessmentId = null;
    public ?int $appointmentId = null;
    public ?string $paymentReference = null;

    private const CONSENT_VERSION = '2026-11-26';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        abort_unless($this->onboardingEnabled($slug), 404);

        $data = $this->treatmentData($slug);
        $this->treatmentName = $data['name'] ?? Str::headline($slug);
        $this->feeCents = $this->resolveFeeCents($data);
        $this->selectedDate = now()->addDay()->format('Y-m-d');

        foreach ($this->questions() as $q) {
            $type = $q['type'] ?? null;
            if ($type === 'checkbox' && ! isset($this->answers[$q['id']])) {
                $this->answers[$q['id']] = [];
            }
            if ($type === 'medicines' && ! isset($this->answers[$q['id']])) {
                $this->answers[$q['id']] = [''];
            }
            if ($type === 'measurements') {
                $this->answers[$q['id'] . '_weight'] ??= 85;
                $this->answers[$q['id'] . '_height'] ??= 170;
            }
        }
    }

    // ── assessment ────────────────────────────────────────────────────────────────────────────────

    public function questions(): array
    {
        $flow = app(AssessmentService::class)->flowFor($this->slug);
        if (! empty($flow)) {
            return $flow;
        }
        return config("assessment-questions.{$this->slug}", []);
    }

    public function visibleQuestions(): array
    {
        return array_values(array_filter($this->questions(), fn ($q) => $this->isVisible($q)));
    }

    /**
     * Screens = visible questions grouped so a with_previous question rides on the screen of the question above.
     *
     * @return array<int,array{q:array,children:array<int,array>}>
     */
    public function screens(): array
    {
        $screens = [];
        foreach ($this->visibleQuestions() as $q) {
            if (($q['layout'] ?? null) === 'with_previous' && $screens) {
                $screens[count($screens) - 1]['children'][] = $q;
            } else {
                $screens[] = ['q' => $q, 'children' => []];
            }
        }
        return $screens;
    }

    /**
     * NOT a computed property on purpose: Livewire memoises getXProperty() for the whole request, and this
     * value changes inside one request (a single choice advances the cursor, then the view re-renders).
     */
    public function currentScreen(): ?array
    {
        $screens = $this->screens();
        return $screens[min($this->cursor, max(count($screens) - 1, 0))] ?? null;
    }

    public function getCurrentScreenProperty(): ?array
    {
        return $this->currentScreen();
    }

    /** Kept for callers/tests that ask for "the question" (the screen's first question). */
    public function getCurrentQuestionProperty(): ?array
    {
        return $this->currentScreen()['q'] ?? null;
    }

    public function getProgressProperty(): array
    {
        $total = max(count($this->screens()), 1);
        $current = min($this->cursor + 1, $total);
        return ['current' => $current, 'total' => $total, 'percent' => (int) round($current / $total * 100)];
    }

    /** Choosing a single answer moves on by itself, unless the screen has follow-ups to fill in. */
    public function selectAnswer(string $questionId, string $value): void
    {
        $this->answers[$questionId] = $value;

        $screen = $this->currentScreen();
        if ($screen && $screen['q']['id'] === $questionId && empty($screen['children'])
            && in_array($screen['q']['type'] ?? '', ['radio', 'single'], true)) {
            $this->nextQuestion();
        }
    }

    public function addMedicine(string $questionId): void
    {
        $items = $this->answers[$questionId] ?? [''];
        $this->answers[$questionId] = is_array($items) ? array_merge($items, ['']) : [''];
    }

    public function skipQuestion(): void
    {
        $q = $this->currentScreen()['q'] ?? null;
        if ($q && ($q['type'] ?? null) === 'medicines') {
            $this->answers[$q['id']] = 'Skipped';
            $this->resetErrorBag('answer');
            $this->advance();
        }
    }

    public function nextQuestion(): void
    {
        $screen = $this->currentScreen();
        if (! $screen) {
            return;
        }

        // A "stop" message has nothing to answer and must not be skippable via a crafted request.
        if (($screen['q']['type'] ?? null) === 'stop') {
            return;
        }

        foreach (array_merge([$screen['q']], $screen['children']) as $q) {
            if (($error = $this->normaliseAndCheck($q)) !== null) {
                $this->addError('answer', $error);
                return;
            }
        }
        $this->resetErrorBag('answer');
        $this->advance();
    }

    private function advance(): void
    {
        $total = count($this->screens());
        if ($this->cursor < $total - 1) {
            $this->cursor++;
        } else {
            $this->stage = 'review';
        }
    }

    /** Compose composite answers, then validate. Returns an error message or null. */
    private function normaliseAndCheck(array $q): ?string
    {
        $id = $q['id'];
        $type = $q['type'] ?? 'text';
        $label = $q['label'] ?? 'This question';

        if ($type === 'date') {
            $month = trim((string) ($this->answers[$id . '_month'] ?? ''));
            $year = trim((string) ($this->answers[$id . '_year'] ?? ''));
            $this->answers[$id] = ($month !== '' && $year !== '') ? "{$month} {$year}" : '';
        }

        if ($type === 'measurements') {
            $w = (float) ($this->answers[$id . '_weight'] ?? 0);
            $h = (float) ($this->answers[$id . '_height'] ?? 0);
            if ($w < 30 || $w > 250 || $h < 120 || $h > 220) {
                return 'Enter a valid weight and height.';
            }
            $this->answers[$id] = rtrim(rtrim(number_format($w, 1, '.', ''), '0'), '.') . ' kg, '
                . rtrim(rtrim(number_format($h, 1, '.', ''), '0'), '.') . ' cm';
        }

        if ($type === 'medicines') {
            $items = $this->answers[$id] ?? [];
            if (is_array($items)) {
                $clean = array_values(array_filter(array_map(fn ($m) => trim((string) $m), $items), fn ($m) => $m !== ''));
                $this->answers[$id] = $clean ?: [''];
                if (empty($clean) && ($q['required'] ?? false)) {
                    return 'Add a medicine or select Skip.';
                }
            }
        }

        if (($q['required'] ?? false) && $this->isBlank($this->answers[$id] ?? null)) {
            return $label . ' is required.';
        }

        // "None of these" cannot be combined with another choice.
        if ($type === 'checkbox' && is_array($this->answers[$id] ?? null) && count($this->answers[$id]) > 1) {
            foreach ($this->answers[$id] as $choice) {
                if (preg_match('/^none\b/i', (string) $choice)) {
                    return "You chose 'None of these' and another option. Please check your answer.";
                }
            }
        }

        return null;
    }

    public function prevQuestion(): void
    {
        $this->resetErrorBag('answer');
        if ($this->cursor > 0) {
            $this->cursor--;
        }
    }

    // ── review ────────────────────────────────────────────────────────────────────────────────────

    /** @return array<int,array{title:string,answer:string,index:int}> */
    public function reviewRows(): array
    {
        $rows = [];
        foreach ($this->screens() as $i => $screen) {
            $parts = [];
            foreach (array_merge([$screen['q']], $screen['children']) as $q) {
                $a = $this->answers[$q['id']] ?? null;
                if ($this->isBlank($a) || $a === ['']) {
                    continue;
                }
                $parts[] = is_array($a) ? implode(', ', array_filter($a)) : (string) $a;
            }
            $rows[] = [
                'title' => $screen['q']['screen_title'] ?? $screen['q']['label'] ?? $screen['q']['id'],
                'answer' => $parts ? implode(' · ', $parts) : 'Not answered',
                'index' => $i,
            ];
        }
        return $rows;
    }

    public function editScreen(int $index): void
    {
        $this->cursor = max(0, $index);
        $this->stage = 'assessment';
    }

    /** Back-compat: jump to the screen holding a question id. */
    public function editAnswer(string $questionId): void
    {
        foreach ($this->screens() as $i => $screen) {
            foreach (array_merge([$screen['q']], $screen['children']) as $q) {
                if ($q['id'] === $questionId) {
                    $this->editScreen($i);
                    return;
                }
            }
        }
    }

    public function updatedReviewTruth($value): void
    {
        $this->recordConsent('treatment_self_true', 'I am asking for treatment for myself only, and everything I have told you is true and complete.', (bool) $value);
    }

    public function updatedReviewSideEffects($value): void
    {
        $this->recordConsent('medicine_side_effects', 'I understand medicines can have side effects. I will read the leaflet that comes with mine.', (bool) $value);
    }

    public function toDetails(): void
    {
        if (! ($this->reviewTruth && $this->reviewSideEffects)) {
            $this->addError('review', 'Please tick both statements to continue.');
            return;
        }
        $this->resetErrorBag('review');

        // A patient who is already signed in has an account and a verified phone: no details
        // form, no code. Their assessment is saved to their account and they go straight to booking.
        if (Auth::check() && Auth::user()->role === \App\Enums\UserRole::Patient) {
            $this->verified = true;
            $this->createOrAttachUserAndAssessment();
            $this->stage = 'book';

            return;
        }

        $this->stage = 'details';
    }

    // ── details + OTP ───────────────────────────────────────────────────────────────────────────

    public function updatedCell($value): void
    {
        $this->cell = $this->formatCell((string) $value);
    }

    public function updatedContactPhone($value): void
    {
        $this->contactPhone = $this->formatCell((string) $value);
    }

    public function updatedConsentCare($value): void
    {
        $this->recordConsent('terms_and_privacy', 'I agree to the Terms and Conditions and the Privacy Policy. I consent to Zapmed holding and processing my personal and health information to provide my care.', (bool) $value);
    }

    public function updatedConsentMarketing($value): void
    {
        $this->recordConsent('marketing', 'Keep me updated by email, WhatsApp and SMS about Zapmed treatments, offers and health tips. I can unsubscribe at any time.', (bool) $value);
    }

    public function updatedPrescribingConsent($value): void
    {
        $this->recordConsent('prescribing', 'I understand my doctor prescribes based on my answers, and only when treatment is safe and right for me. A prescription is not guaranteed.', (bool) $value);
    }

    /** True when the ID number cannot tell us the date of birth and sex: none given, or not a valid South African ID. */
    public function needsPersonalDetails(): bool
    {
        return $this->noId || ! \App\Support\SaIdNumber::isValid($this->idNumber);
    }

    public function sendOtp(): void
    {
        $this->validate([
            'dateOfBirth' => $this->needsPersonalDetails() ? 'required|date|before:today|after:1900-01-01' : 'nullable',
            'gender' => $this->needsPersonalDetails() ? 'required|in:male,female,other' : 'nullable',
            'name' => 'required|string|max:190',
            'email' => 'required|email|max:190',
            'cell' => ['required', 'regex:/^\d{2} \d{3} \d{4}$/'],
            'idNumber' => $this->noId ? 'nullable' : ['required', 'regex:/^\d{13}$/'],
            'communication' => 'required|in:WhatsApp,email',
            'password' => 'required|string|min:10|same:passwordConfirm',
            'consentCare' => 'accepted',
        ], [
            'cell.regex' => 'Enter your 9-digit mobile number, for example 82 123 4567.',
            'idNumber.regex' => 'Enter your 13-digit South African ID number, or choose the no-ID option.',
            'dateOfBirth.required' => 'Enter your date of birth.',
            'dateOfBirth.before' => 'Your date of birth must be in the past.',
            'gender.required' => 'Choose the option that applies to you.',
            'password.min' => 'Choose a password of at least 10 characters.',
            'password.same' => 'The two passwords do not match.',
            'consentCare.accepted' => 'Agree to the Terms and Conditions and Privacy Policy to continue.',
        ], ['consentCare' => 'privacy & care consent']);

        // A public form must never sign a visitor in to somebody else's account.
        if (! Auth::check() && User::where('email', $this->email)->exists()) {
            $this->addError('email', 'An account with this email already exists. Please log in to continue.');
            return;
        }

        // The code must NEVER be a public Livewire property: public state is serialised into the page
        // (wire:snapshot) and readable by the visitor, which would defeat the phone check entirely.
        // Keep only a keyed hash server-side in the session.
        $code = (string) random_int(100000, 999999);
        $phone = $this->localCell();
        session(['booking_otp' => [
            'hash' => hash_hmac('sha256', $code, (string) config('app.key')),
            'cell' => $phone,
            'expires' => now()->addMinutes(10)->getTimestamp(),
            'attempts' => 0,
        ]]);
        $this->otpSent = true;

        try {
            app(\App\Services\SmsService::class)->sendOtp($phone, $code);
        } catch (\Throwable $e) {
            Log::info('BookingJourney OTP (fallback log)', ['cell' => $phone, 'code' => $code]);
        }
    }

    public function verifyOtp(): void
    {
        $this->validate(['otp' => 'required|string']);
        $stored = session('booking_otp');
        if (! $stored || $stored['expires'] < now()->getTimestamp() || $stored['cell'] !== $this->localCell() || $stored['attempts'] >= 5) {
            $this->addError('otp', 'That code has expired. Please request a new one.');
            return;
        }
        if (! hash_equals($stored['hash'], hash_hmac('sha256', trim($this->otp), (string) config('app.key')))) {
            $stored['attempts']++;
            session(['booking_otp' => $stored]);
            $this->addError('otp', 'That code is not correct. Please try again.');
            return;
        }
        session()->forget('booking_otp');
        $this->verified = true;
        $this->createOrAttachUserAndAssessment();
        $this->stage = 'book';
    }

    private function createOrAttachUserAndAssessment(): void
    {
        $user = Auth::user();
        if (! $user) {
            [$first, $last] = $this->splitName();
            $user = User::create([
                'first_name' => $first,
                'last_name' => $last,
                'name' => trim($this->name),
                'email' => $this->email,
                'phone' => $this->localCell(),
                'preferred_contact' => strtolower($this->communication),
                'id_number' => $this->noId ? null : $this->idNumber,
                // A valid ID number carries the date of birth and the sex, so the doctor sees the age and gender without asking again.
                'date_of_birth' => $this->needsPersonalDetails() ? ($this->dateOfBirth ?: null) : \App\Support\SaIdNumber::dateOfBirth($this->idNumber)?->toDateString(),
                'gender' => $this->needsPersonalDetails() ? ($this->gender ?: null) : \App\Support\SaIdNumber::gender($this->idNumber),
                'password' => Hash::make($this->password),
                'role' => \App\Enums\UserRole::Patient,
                // Identity is proven by the SMS OTP in this journey, so the account is
                // considered verified - otherwise the 'verified' middleware on the payment
                // route would bounce the brand-new patient to /verify-email before PayFast.
                'email_verified_at' => now(),
            ]);
            // Auth::login() rotates the session id AND the CSRF token. The page the patient is
            // looking at still holds the old token, so the very next Livewire request (picking a
            // slot) would die with a 419 "page expired". Keep the session-id rotation (protects
            // against session fixation) but carry the page's token over.
            $csrfToken = session()->token();
            Auth::login($user);
            session()->put('_token', $csrfToken);
        }

        $this->flushConsentLog($user);
        $this->saveMeasurementsToProfile($user);

        $svc = app(AssessmentService::class);
        $onBank = $svc->categoryIdFor($this->slug) !== null;

        $assessment = Assessment::create([
            'user_id' => $user->id,
            'treatment_slug' => $this->slug,
            'treatment_name' => $this->treatmentName,
            'category_id' => $onBank ? $svc->categoryIdFor($this->slug) : null,
            'manifest_version' => $onBank ? $svc->manifestVersionFor($this->slug) : null,
            'answers' => $this->answersWithLabels(),
            'status' => 'completed',
        ]);

        if ($onBank) {
            foreach ($this->visibleQuestions() as $q) {
                $v = $this->answers[$q['id']] ?? null;
                if ($this->isBlank($v) || $v === ['']) {
                    continue;
                }
                $svc->saveAnswer($assessment, $q['id'], $v);
            }
        }

        $this->assessmentId = $assessment->id;
    }

    // ── book ──────────────────────────────────────────────────────────────────────────────────────

    /** The height and weight the patient gave go on their profile (only where it is still empty), so the doctor sees the BMI. */
    private function saveMeasurementsToProfile(User $user): void
    {
        foreach ($this->visibleQuestions() as $q) {
            if (($q['type'] ?? null) !== 'measurements') {
                continue;
            }
            $m = \App\Services\Doctor\BodyMeasurements::fromAnswer($this->answers[$q['id']] ?? null);
            if (! $m) {
                return;
            }
            $profile = \App\Models\PatientProfile::firstOrNew(['user_id' => $user->id]);
            $profile->height_cm = $profile->height_cm ?: $m['height_cm'];
            $profile->weight_kg = $profile->weight_kg ?: $m['weight_kg'];
            $profile->save();

            return;
        }
    }
    /** Practitioners who take consultations in this journey (dietitians are coaches, not consult doctors). */
    private function consultDoctors()
    {
        return DoctorProfile::verified()->acceptingPatients()->with('user')->get()
            ->reject(fn ($p) => str_contains(strtolower((string) $p->speciality), 'dietitian'))
            ->values();
    }

    /**
     * Each doctor with their next days that have free slots (Craig's "choose your preferred specialist" cards).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getDoctorOptionsProperty(): array
    {
        $out = [];
        foreach ($this->consultDoctors() as $p) {
            $days = [];
            for ($d = 1; $d <= 14 && count($days) < 3; $d++) {
                $date = now()->addDays($d)->format('Y-m-d');
                $slots = $p->availableStartTimes($date, $this->bookingMinutes());
                if (! empty($slots)) {
                    $days[] = [
                        'date' => $date,
                        'label' => \Illuminate\Support\Carbon::parse($date)->format('l j F'),
                        'slots' => array_values($slots),
                    ];
                }
            }
            $out[] = [
                'user_id' => $p->user_id,
                'name' => (($p->user->first_name ?? '') !== '' ? 'Dr ' : '') . trim(($p->user->first_name ?? '') . ' ' . ($p->user->last_name ?? '')),
                'photo' => $p->user?->avatar_url,
                'qualification' => $p->qualification,
                'bio' => $p->bio,
                'days' => $days,
            ];
        }
        return $out;
    }

    /** Length the whole slot must be free for: the treatment's fixed consultation, else one 15-minute slot. */
    private function bookingMinutes(): int
    {
        $data = $this->treatmentData($this->slug);
        return ! empty($data['fixed_consultation']) ? (int) ($data['duration'] ?? 15) : 15;
    }

    public function toggleDay(string $key): void
    {
        $this->expandedDays = in_array($key, $this->expandedDays, true)
            ? array_values(array_diff($this->expandedDays, [$key]))
            : array_merge($this->expandedDays, [$key]);
    }

    public function getSelectedDoctorProperty(): ?array
    {
        foreach ($this->doctor_options as $o) {
            if ($o['user_id'] === $this->selectedDoctorId) {
                return $o;
            }
        }
        // e.g. a doctor auto-assigned for a slot: build a minimal card from the user record
        if ($this->selectedDoctorId && ($u = User::find($this->selectedDoctorId))) {
            return ['user_id' => $u->id, 'name' => 'Dr ' . trim($u->first_name . ' ' . $u->last_name), 'photo' => $u->avatar_url];
        }
        return null;
    }

    public function selectSlot(int $doctorId, string $date, string $time): void
    {
        $this->selectedDoctorId = $doctorId;
        $this->selectedDate = $date;
        $this->selectedTime = $time;
        $this->resetErrorBag('selectedTime');
    }

    /** "To find your soonest available booking": earliest slot across every doctor, straight to payment. */
    public function bookSoonest(): void
    {
        $best = null;
        foreach ($this->doctor_options as $o) {
            foreach ($o['days'] as $day) {
                foreach ($day['slots'] as $time) {
                    $key = $day['date'] . ' ' . $time;
                    if ($best === null || $key < $best['key']) {
                        $best = ['key' => $key, 'doctor' => $o['user_id'], 'date' => $day['date'], 'time' => $time];
                    }
                }
            }
        }
        if (! $best) {
            $this->addError('selectedTime', 'No appointments available right now. Please try again soon.');
            return;
        }
        $this->selectSlot($best['doctor'], $best['date'], $best['time']);
        $this->stage = 'pay';
    }

    // Kept for the older date-picker API (still exercised by tests and used as an admin-friendly fallback).
    public function getAvailableSlotsProperty(): array
    {
        if (! $this->selectedDate) {
            return [];
        }
        $max = now()->addDays(14)->format('Y-m-d');
        if ($this->selectedDate > $max) {
            return [];
        }
        $slots = [];
        foreach (DoctorProfile::verified()->acceptingPatients()->get() as $p) {
            $slots = array_merge($slots, $p->getAvailableSlotsForDate($this->selectedDate));
        }
        $slots = array_values(array_unique($slots));
        sort($slots);
        if ($this->selectedDate === now()->format('Y-m-d')) {
            $now = now()->format('H:i');
            $slots = array_values(array_filter($slots, fn ($s) => $s > $now));
        }
        return $slots;
    }

    public function getDoctorsForSlotProperty()
    {
        if (! $this->selectedDate || ! $this->selectedTime) {
            return collect();
        }
        return DoctorProfile::getAvailableDoctorsForSlot($this->selectedDate, $this->selectedTime);
    }

    public function selectTime(string $time): void
    {
        $this->selectedTime = $time;
        $this->selectedDoctorId = null;
    }

    public function selectDoctor(int $doctorId): void
    {
        $this->selectedDoctorId = $doctorId;
    }

    public function updatedMode(): void
    {
        if ($this->mode === 'telephone' && $this->contactPhone === '') {
            $this->contactPhone = $this->cell;
        }
    }

    public function toPay(): void
    {
        $this->validate([
            'selectedDate' => 'required|date|after_or_equal:today',
            'selectedTime' => 'required',
            'mode' => 'required|in:video,telephone',
        ]);

        if ($this->mode === 'telephone'
            && (! preg_match('/^\d{2} \d{3} \d{4}$/', $this->contactPhone) || ! $this->phoneConfirmed)) {
            $this->addError('contactPhone', 'Confirm the 9-digit number the doctor should call.');
            return;
        }

        if (! $this->selectedDoctorId) {
            $this->selectedDoctorId = $this->autoAssignDoctor();
        }
        if (! $this->selectedDoctorId) {
            $this->addError('selectedTime', 'No doctors available for this slot. Please choose another time.');
            return;
        }
        $this->stage = 'pay';
    }

    private function autoAssignDoctor(): ?int
    {
        $available = $this->doctors_for_slot;
        if ($available->isEmpty()) {
            return null;
        }
        $previous = Appointment::where('patient_id', Auth::id())
            ->where('status', 'completed')->orderByDesc('created_at')->value('doctor_id');
        if ($previous && $available->contains('user_id', $previous)) {
            return $previous;
        }
        $loads = Appointment::whereIn('doctor_id', $available->pluck('user_id'))
            ->where('appointment_date', $this->selectedDate)
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('doctor_id, count(*) c')->groupBy('doctor_id')->pluck('c', 'doctor_id');
        return $available->sortBy(fn ($p) => $loads->get($p->user_id, 0))->first()->user_id;
    }

    public function changeBooking(): void
    {
        $this->stage = 'book';
    }

    // ── pay ─────────────────────────────────────────────────────────────────────────────────────

    public function confirmAndPay(PayFastService $payfast)
    {
        abort_unless($this->verified && $this->selectedDoctorId, 403);

        if (! $this->prescribingConsent) {
            $this->addError('pay', 'Please tick the box to confirm you understand a prescription is not guaranteed.');
            return;
        }

        $stillFree = DoctorProfile::getAvailableDoctorsForSlot($this->selectedDate, $this->selectedTime)
            ->contains('user_id', $this->selectedDoctorId);
        if ($stillFree && $this->bookingMinutes() > 15) {
            // the whole consultation (e.g. 45 minutes) must still be free, not only its first 15-minute slot
            $p = DoctorProfile::where('user_id', $this->selectedDoctorId)->first();
            $stillFree = $p && in_array($this->selectedTime, $p->availableStartTimes($this->selectedDate, $this->bookingMinutes()), true);
        }
        if (! $stillFree) {
            $this->addError('pay', 'That slot was just taken. Please pick another time.');
            $this->stage = 'book';
            $this->selectedTime = '';
            return;
        }

        $doctor = User::with('doctorProfile')->findOrFail($this->selectedDoctorId);
        $profile = $doctor->doctorProfile;

        // Bound server-side. A treatment flagged `fixed_consultation` (weight loss: R700, 45 min) sets its
        // own fee and length; every other treatment keeps the doctor-profile fee/duration.
        $data = $this->treatmentData($this->slug);
        if (! empty($data['fixed_consultation'])) {
            $fee = $this->resolveFeeCents($data); // recomputed here: the public $feeCents is client-writable
            $duration = (int) ($data['duration'] ?? 30);
        } else {
            $fee = (int) ($profile->consultation_fee ?: $this->resolveFeeCents($data));
            $duration = (int) ($profile->consultation_duration ?: 30);
        }
        $end = date('H:i', strtotime($this->selectedTime) + $duration * 60);

        $appointment = Appointment::create([
            'patient_id' => Auth::id(),
            'doctor_id' => $doctor->id,
            'type' => $this->slug,
            'status' => 'pending_payment',
            'appointment_date' => $this->selectedDate,
            'start_time' => $this->selectedTime,
            'end_time' => $end,
            'duration_minutes' => $duration,
            'reason' => $this->treatmentName . ' consultation',
            'communication_preference' => $this->mode === 'telephone' ? 'audio' : 'video',
            'notes' => $this->mode === 'telephone' ? 'Doctor to call: +27 ' . $this->contactPhone : null,
            'fee_amount' => $fee,
            'is_paid' => false,
            'reserved_until' => now()->addMinutes((int) config('onboarding.reservation_minutes', 15)),
        ]);

        if ($this->assessmentId) {
            Assessment::where('id', $this->assessmentId)->whereNull('appointment_id')
                ->update(['appointment_id' => $appointment->id]);
        }

        $payment = Payment::create([
            'patient_id' => Auth::id(),
            'appointment_id' => $appointment->id,
            'amount' => $fee,
            'status' => 'pending',
        ]);

        $this->flushConsentLog(Auth::user());

        $this->appointmentId = $appointment->id;
        $this->paymentReference = $payment->reference;

        return redirect()->route('payment.checkout', ['reference' => $payment->reference]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────────────────────────

    private function recordConsent(string $key, string $wording, bool $granted): void
    {
        $this->consentLog = array_values(array_filter($this->consentLog, fn ($c) => $c['key'] !== $key));
        if ($granted) {
            $this->consentLog[] = ['key' => $key, 'wording' => $wording, 'granted_at' => now()->toIso8601String(), 'saved' => false];
        }
    }

    /** Persist consents that have not been saved yet. They are only stored once the user exists. */
    private function flushConsentLog(?User $user): void
    {
        if (! $user) {
            return;
        }
        foreach ($this->consentLog as $i => $c) {
            if (! empty($c['saved'])) {
                continue;
            }
            ConsentRecord::create([
                'user_id' => $user->id,
                'consent_type' => $c['key'],
                'version' => self::CONSENT_VERSION,
                'wording' => $c['wording'],
                'granted' => true,
                'ip_address' => request()->ip(),
                'granted_at' => $c['granted_at'],
            ]);
            $this->consentLog[$i]['saved'] = true;
        }
    }

    private function formatCell(string $value): string
    {
        $d = substr(preg_replace('/\D/', '', $value) ?? '', 0, 9);
        return trim(implode(' ', array_filter([substr($d, 0, 2), substr($d, 2, 3), substr($d, 5)])));
    }

    /** 9 digits after +27 -> local 0XXXXXXXXX (what SmsService + users.phone expect). */
    private function localCell(): string
    {
        $d = preg_replace('/\D/', '', $this->cell) ?? '';
        // tolerate a number typed with its leading 0 (10 digits) or already local
        if (strlen($d) === 10 && str_starts_with($d, '0')) {
            return $d;
        }
        return '0' . $d;
    }

    /** @return array{0:string,1:string} */
    private function splitName(): array
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [''];
        $first = array_shift($parts) ?: '';
        return [$first, trim(implode(' ', $parts))];
    }

    private function isVisible(array $q): bool
    {
        if (! isset($q['show_if'])) {
            return true;
        }
        foreach ($q['show_if'] as $depId => $required) {
            if (! \App\Services\Assessments\AssessmentService::branchMatches($required, $this->answers[$depId] ?? null)) {
                return false;
            }
        }
        return true;
    }

    private function isBlank($v): bool
    {
        return $v === null || $v === '' || $v === [] || $v === [''];
    }

    private function answersWithLabels(): array
    {
        $out = [];
        foreach ($this->visibleQuestions() as $q) {
            $a = $this->answers[$q['id']] ?? null;
            if ($this->isBlank($a)) {
                continue;
            }
            $out[] = ['question' => $q['label'] ?? $q['id'], 'answer' => $a, 'type' => $q['type'] ?? 'text'];
        }
        return $out;
    }

    private function onboardingEnabled(string $slug): bool
    {
        $enabled = config('onboarding.enabled_slugs', []);
        return in_array('*', $enabled, true) || in_array($slug, $enabled, true);
    }

    private function treatmentData(string $slug): array
    {
        foreach (config('treatments', []) as $cat) {
            if (isset($cat['treatments'][$slug])) {
                return $cat['treatments'][$slug];
            }
        }
        return [];
    }

    private function resolveFeeCents(array $data): int
    {
        return (int) ($data['price_once_off'] ?? config('onboarding.default_fee_cents', 45000));
    }

    public function render()
    {
        return view('livewire.patient.booking-journey')->layout('layouts.journey');
    }
}
