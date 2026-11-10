<?php

namespace App\Livewire\Patient;

use App\Models\Appointment;
use App\Models\Assessment;
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
 * Charge-at-booking onboarding JOURNEY (spec: .kiro/specs/charge-at-booking).
 *
 * NB: distinct from App\Livewire\Patient\Onboarding (that is the patient PROFILE-completion flow at
 * /onboarding). This is the treatment booking journey at /start/{slug}.
 *
 * Stages: assessment -> review -> details -> book -> pay -> confirm.
 * Fee is disclosed up front and CHARGED at confirmation (PayFast). Clinical questions come from the
 * question-bank engine (AssessmentService); payment reuses PayFastService + the existing /payment/notify
 * ITN webhook (which already flips appointment.is_paid + status=confirmed on COMPLETE).
 *
 * Decisions (Captain Zan, 2026-11-05): patient PICKS doctor+slot (auto-assign fallback); OTP + account
 * at "details"; PayFast redirect; fee bound SERVER-SIDE from the doctor consult fee (R450 default).
 */
class BookingJourney extends Component
{
    public string $slug;
    public string $treatmentName = '';
    public int $feeCents = 0;

    public string $stage = 'assessment'; // assessment|review|details|book|pay|confirm

    /** assessment */
    public array $answers = [];
    public int $cursor = 0;

    /** details + OTP */
    public string $firstName = '';
    public string $lastName = '';
    public string $email = '';
    public string $cell = '';
    public bool $consentCare = false;
    public bool $consentMarketing = false;
    public string $otp = '';
    public string $otpExpected = '';
    public bool $otpSent = false;
    public bool $verified = false;

    /** book */
    public string $selectedDate = '';
    public string $selectedTime = '';
    public ?int $selectedDoctorId = null;
    public string $mode = 'video';

    /** persisted ids */
    public ?int $assessmentId = null;
    public ?int $appointmentId = null;
    public ?string $paymentReference = null;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        abort_unless($this->onboardingEnabled($slug), 404);

        $data = $this->treatmentData($slug);
        $this->treatmentName = $data['name'] ?? Str::headline($slug);
        $this->feeCents = $this->resolveFeeCents($data);
        $this->selectedDate = now()->addDay()->format('Y-m-d');

        foreach ($this->questions() as $q) {
            if (($q['type'] ?? null) === 'checkbox' && ! isset($this->answers[$q['id']])) {
                $this->answers[$q['id']] = [];
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

    public function getCurrentQuestionProperty(): ?array
    {
        return $this->visibleQuestions()[$this->cursor] ?? null;
    }

    public function getProgressProperty(): array
    {
        $total = max(count($this->visibleQuestions()), 1);
        $current = min($this->cursor + 1, $total);
        return ['current' => $current, 'total' => $total, 'percent' => (int) round($current / $total * 100)];
    }

    public function nextQuestion(): void
    {
        $q = $this->current_question;

        // A "stop" message has nothing to answer and must not be skippable via a crafted request.
        if ($q && ($q['type'] ?? null) === 'stop') {
            return;
        }

        // Date questions are entered as two selects (answers.{id}_month / _year); compose them into
        // the single answers.{id} value ("Month YYYY") so required-check, review and save all work
        // against the same key every other question uses.
        if ($q && ($q['type'] ?? null) === 'date') {
            $month = trim((string) ($this->answers[$q['id'] . '_month'] ?? ''));
            $year = trim((string) ($this->answers[$q['id'] . '_year'] ?? ''));
            $this->answers[$q['id']] = ($month !== '' && $year !== '') ? "{$month} {$year}" : '';
        }

        if ($q && ($q['required'] ?? false) && $this->isBlank($this->answers[$q['id']] ?? null)) {
            $this->addError('answer', ($q['label'] ?? 'This question') . ' is required.');
            return;
        }
        $this->resetErrorBag('answer');

        $visible = $this->visibleQuestions();
        if ($this->cursor < count($visible) - 1) {
            $this->cursor++;
        } else {
            $this->stage = 'review';
        }
    }

    public function prevQuestion(): void
    {
        $this->resetErrorBag('answer');
        if ($this->cursor > 0) {
            $this->cursor--;
        }
    }

    // ── review ────────────────────────────────────────────────────────────────────────────────────

    public function editAnswer(string $questionId): void
    {
        foreach ($this->visibleQuestions() as $i => $q) {
            if ($q['id'] === $questionId) {
                $this->cursor = $i;
                $this->stage = 'assessment';
                return;
            }
        }
    }

    public function toDetails(): void
    {
        $this->stage = 'details';
    }

    // ── details + OTP ───────────────────────────────────────────────────────────────────────────

    public function sendOtp(): void
    {
        $this->validate([
            'firstName' => 'required|string|max:100',
            'lastName' => 'required|string|max:100',
            'email' => 'required|email|max:190',
            'cell' => 'required|string|max:20',
            'consentCare' => 'accepted',
        ], [], ['consentCare' => 'privacy & care consent']);

        $this->otpExpected = (string) random_int(100000, 999999);
        $this->otpSent = true;

        try {
            app(\App\Services\SmsService::class)->sendOtp($this->cell, $this->otpExpected);
        } catch (\Throwable $e) {
            Log::info('BookingJourney OTP (fallback log)', ['cell' => $this->cell, 'code' => $this->otpExpected]);
        }
    }

    public function verifyOtp(): void
    {
        $this->validate(['otp' => 'required|string']);
        if (! hash_equals($this->otpExpected, trim($this->otp))) {
            $this->addError('otp', 'That code is not correct. Please try again.');
            return;
        }
        $this->verified = true;
        $this->createOrAttachUserAndAssessment();
        $this->stage = 'book';
    }

    private function createOrAttachUserAndAssessment(): void
    {
        $user = Auth::user() ?? User::where('email', $this->email)->first();
        if (! $user) {
            $user = User::create([
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'name' => trim($this->firstName . ' ' . $this->lastName),
                'email' => $this->email,
                'phone' => $this->cell,
                'password' => Hash::make(Str::random(32)),
                'role' => \App\Enums\UserRole::Patient,
                // Identity is proven by the SMS OTP in this journey, so the account is
                // considered verified — otherwise the 'verified' middleware on the payment
                // route would bounce the brand-new patient to /verify-email before PayFast.
                'email_verified_at' => now(),
            ]);
        }
        if (! Auth::check()) {
            Auth::login($user);
        }

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
                if ($this->isBlank($v)) {
                    continue;
                }
                $svc->saveAnswer($assessment, $q['id'], $v);
            }
        }

        $this->assessmentId = $assessment->id;
    }

    // ── book ──────────────────────────────────────────────────────────────────────────────────────

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

    public function toPay(): void
    {
        $this->validate([
            'selectedDate' => 'required|date|after_or_equal:today',
            'selectedTime' => 'required',
            'mode' => 'required|in:video,telephone',
        ]);

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

    // ── pay ─────────────────────────────────────────────────────────────────────────────────────

    public function confirmAndPay(PayFastService $payfast)
    {
        abort_unless($this->verified && $this->selectedDoctorId, 403);

        $stillFree = DoctorProfile::getAvailableDoctorsForSlot($this->selectedDate, $this->selectedTime)
            ->contains('user_id', $this->selectedDoctorId);
        if (! $stillFree) {
            $this->addError('pay', 'That slot was just taken. Please pick another time.');
            $this->stage = 'book';
            $this->selectedTime = '';
            return;
        }

        $doctor = User::with('doctorProfile')->findOrFail($this->selectedDoctorId);
        $profile = $doctor->doctorProfile;

        $fee = (int) ($profile->consultation_fee ?: $this->feeCents); // bound server-side
        $duration = (int) ($profile->consultation_duration ?: 30);
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

        $this->appointmentId = $appointment->id;
        $this->paymentReference = $payment->reference;

        return redirect()->route('payment.checkout', ['reference' => $payment->reference]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────────────────────────

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
        return $v === null || $v === '' || $v === [];
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
        return view('livewire.patient.booking-journey')->layout('layouts.onboarding');
    }
}
