<?php

namespace App\Livewire\Patient;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BookAppointment extends Component
{
    public int $step = 1;

    // Step 1: Treatment type
    public string $appointmentType = '';
    public string $selectedTreatment = '';
    public string $reason = '';

    // Step 2: Assessment questions
    public array $assessmentAnswers = [];

    // Step 3: Communication preference
    public string $communicationPreference = 'video';

    // Step 4: Payment
    public string $paymentOption = 'consultation';
    public bool $isPaid = false;

    // Step 5: Date & time
    public string $selectedDate = '';
    public string $selectedTime = '';

    // Step 6: Confirmation
    public ?int $assignedDoctorId = null;
    public ?Appointment $bookedAppointment = null;

    // Assessment link
    public ?int $assessmentId = null;

    public function mount(): void
    {
        $this->selectedDate = now()->addDay()->format('Y-m-d');
        $this->assessmentId = request()->query('assessment_id') ? (int) request()->query('assessment_id') : null;

        // Pre-fill from URL params (e.g., /book?category=weight-loss&treatment=weight-loss)
        $category = request()->query('category');
        $treatment = request()->query('treatment');

        if ($category && array_key_exists($category, config('treatments', []))) {
            $this->appointmentType = $category;

            if ($treatment && isset(config('treatments')[$category]['treatments'][$treatment])) {
                $this->selectedTreatment = $treatment;
            }
        }

        // Pre-fill reason from assessment if available
        if ($this->assessmentId) {
            $assessment = Assessment::where('id', $this->assessmentId)
                ->where('user_id', Auth::id())
                ->first();

            if ($assessment) {
                $this->reason = $assessment->treatment_name . ' consultation';
            }
        }
    }

    /**
     * Move to assessment questions.
     */
    public function proceedToAssessment(): void
    {
        $this->validate([
            'appointmentType' => 'required',
            'selectedTreatment' => 'required',
        ]);

        $this->step = 2;
    }

    /**
     * Complete assessment and move to communication preference.
     */
    public function proceedToPayment(): void
    {
        // Validate required questions (only those that are visible based on conditions)
        $questions = config("assessment-questions.{$this->selectedTreatment}", []);
        $errors = [];

        foreach ($questions as $question) {
            // Skip validation for hidden conditional questions
            if (isset($question['show_if']) && !$this->isQuestionVisible($question, $questions)) {
                continue;
            }

            if ($question['required'] && empty($this->assessmentAnswers[$question['id']] ?? null)) {
                $errors["assessmentAnswers.{$question['id']}"] = "{$question['label']} is required.";
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $key => $message) {
                $this->addError($key, $message);
            }
            return;
        }

        // Save assessment to database
        $treatmentData = config("treatments.{$this->appointmentType}.treatments.{$this->selectedTreatment}");

        $assessment = Assessment::create([
            'user_id' => Auth::id(),
            'treatment_slug' => $this->selectedTreatment,
            'treatment_name' => $treatmentData['name'] ?? $this->selectedTreatment,
            'answers' => $this->buildAnswersWithQuestions($questions),
            'status' => 'completed',
        ]);

        $this->assessmentId = $assessment->id;
        $this->step = 3;
    }

    /**
     * Check if a conditional question should be visible based on current answers.
     */
    private function isQuestionVisible(array $question, array $allQuestions): bool
    {
        if (!isset($question['show_if'])) {
            return true;
        }

        foreach ($question['show_if'] as $depId => $requiredValue) {
            $currentAnswer = $this->assessmentAnswers[$depId] ?? null;

            if ($currentAnswer === null || $currentAnswer === '') {
                return false;
            }

            // For checkbox answers (arrays), check if value is selected
            if (is_array($currentAnswer)) {
                if (!in_array($requiredValue, $currentAnswer)) {
                    return false;
                }
            } else {
                if ($currentAnswer !== $requiredValue) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Build answers array with question text for doctor display.
     */
    private function buildAnswersWithQuestions(array $questions): array
    {
        $result = [];

        foreach ($questions as $question) {
            $answer = $this->assessmentAnswers[$question['id']] ?? null;

            // Skip empty answers and hidden questions
            if (empty($answer)) continue;
            if (isset($question['show_if']) && !$this->isQuestionVisible($question, $questions)) continue;

            $result[] = [
                'question' => $question['label'],
                'answer' => $answer,
                'type' => $question['type'],
            ];
        }

        return $result;
    }

    /**
     * Move to payment after selecting communication preference.
     */
    public function proceedFromCommunicationPreference(): void
    {
        $this->validate([
            'communicationPreference' => 'required|in:video,audio,text',
        ]);

        $this->step = 4;
    }

    /**
     * Move to date/time selection after payment.
     */
    public function proceedToDateTime(): void
    {
        // In production, this would redirect to PayFast and return here after payment.
        // For now, we mark as paid and unlock the calendar.
        $this->isPaid = true;
        $this->step = 5;
    }

    /**
     * Go back to treatment selection.
     */
    public function backToType(): void
    {
        $this->step = 1;
        $this->selectedTime = '';
    }

    /**
     * Go back to assessment.
     */
    public function backToAssessment(): void
    {
        $this->step = 2;
    }

    /**
     * When category changes, reset treatment selection.
     */
    public function updatedAppointmentType(): void
    {
        $this->selectedTreatment = '';
        $this->assessmentAnswers = [];
    }

    /**
     * Go back to communication preference.
     */
    public function backToCommunicationPreference(): void
    {
        $this->step = 3;
    }

    /**
     * Go back to payment step.
     */
    public function backToPayment(): void
    {
        $this->step = 4;
        $this->selectedTime = '';
    }

    /**
     * Go back to time selection.
     */
    public function backToTimeSelection(): void
    {
        $this->step = 5;
    }

    /**
     * Select a time slot.
     */
    public function selectTime(string $time): void
    {
        $this->selectedTime = $time;
    }

    /**
     * Proceed to confirmation — find the best available doctor.
     */
    public function proceedToConfirmation(): void
    {
        $this->validate([
            'selectedDate' => 'required|date|after_or_equal:today',
            'selectedTime' => 'required',
        ]);

        // Find the best available doctor for this slot
        $this->assignedDoctorId = $this->findBestDoctor();

        if (!$this->assignedDoctorId) {
            $this->addError('selectedTime', 'No doctors available for this time slot. Please try a different time.');
            return;
        }

        $this->step = 6;
    }

    /**
     * Find the best available doctor using load-balanced assignment.
     * Priority: 1) Previous doctor (continuity), 2) Least busy doctor that day.
     */
    private function findBestDoctor(): ?int
    {
        $availableDoctors = $this->getAvailableDoctorsForSlot($this->selectedDate, $this->selectedTime);

        if ($availableDoctors->isEmpty()) {
            return null;
        }

        // Check if patient has a previous doctor (continuity preference)
        $previousDoctorId = Appointment::where('patient_id', Auth::id())
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->value('doctor_id');

        if ($previousDoctorId && $availableDoctors->contains('user_id', $previousDoctorId)) {
            return $previousDoctorId;
        }

        // Load-balance: pick the doctor with fewest appointments that day
        $doctorLoads = Appointment::whereIn('doctor_id', $availableDoctors->pluck('user_id'))
            ->where('appointment_date', $this->selectedDate)
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('doctor_id, count(*) as appointment_count')
            ->groupBy('doctor_id')
            ->pluck('appointment_count', 'doctor_id');

        // Sort by load (least busy first), then pick first
        $sorted = $availableDoctors->sortBy(function ($profile) use ($doctorLoads) {
            return $doctorLoads->get($profile->user_id, 0);
        });

        return $sorted->first()->user_id;
    }

    /**
     * Get all doctors available for a specific date/time slot.
     */
    private function getAvailableDoctorsForSlot(string $date, string $time)
    {
        return DoctorProfile::getAvailableDoctorsForSlot($date, $time);
    }

    /**
     * Confirm and create the booking.
     */
    public function confirmBooking(): void
    {
        $doctor = User::with('doctorProfile')->find($this->assignedDoctorId);
        $profile = $doctor->doctorProfile;

        $startTime = $this->selectedTime;
        $endTime = date('H:i', strtotime($startTime) + ($profile->consultation_duration * 60));

        $fee = $this->appointmentType === 'follow_up'
            ? $profile->followup_fee
            : $profile->consultation_fee;

        $this->bookedAppointment = Appointment::create([
            'patient_id' => Auth::id(),
            'doctor_id' => $doctor->id,
            'type' => $this->appointmentType,
            'status' => 'pending',
            'appointment_date' => $this->selectedDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $profile->consultation_duration,
            'reason' => $this->reason ?: null,
            'communication_preference' => $this->communicationPreference,
            'fee_amount' => $fee,
            'is_paid' => false,
        ]);

        // Link assessment to appointment if applicable
        if ($this->assessmentId) {
            Assessment::where('id', $this->assessmentId)
                ->where('user_id', Auth::id())
                ->whereNull('appointment_id')
                ->update(['appointment_id' => $this->bookedAppointment->id]);
        }

        $this->step = 7;
    }

    /**
     * Get all available time slots across ALL doctors for the selected date.
     * A slot is available if at least one doctor can take it.
     */
    public function getAvailableSlotsProperty(): array
    {
        if (!$this->selectedDate) {
            return [];
        }

        // Limit booking window to 14 days in advance
        $maxDate = now()->addDays(14)->format('Y-m-d');
        if ($this->selectedDate > $maxDate) {
            return [];
        }

        $allDoctors = DoctorProfile::verified()
            ->acceptingPatients()
            ->get();

        $allSlots = [];

        foreach ($allDoctors as $profile) {
            $doctorSlots = $profile->getAvailableSlotsForDate($this->selectedDate);
            $allSlots = array_merge($allSlots, $doctorSlots);
        }

        // Unique and sorted
        $allSlots = array_unique($allSlots);
        sort($allSlots);

        // Remove past times if selected date is today
        if ($this->selectedDate === now()->format('Y-m-d')) {
            $now = now()->format('H:i');
            $allSlots = array_filter($allSlots, fn ($slot) => $slot > $now);
        }

        return array_values($allSlots);
    }

    /**
     * Get the assigned doctor for confirmation.
     */
    public function getAssignedDoctorProperty(): ?User
    {
        if (!$this->assignedDoctorId) {
            return null;
        }

        return User::with('doctorProfile')->find($this->assignedDoctorId);
    }

    /**
     * Get the selected treatment's config data.
     */
    public function getSelectedTreatmentDataProperty(): ?array
    {
        if (!$this->appointmentType || !$this->selectedTreatment) {
            return null;
        }

        return config("treatments.{$this->appointmentType}.treatments.{$this->selectedTreatment}");
    }

    /**
     * Get the fee amount based on payment option selected.
     */
    public function getSelectedFeeProperty(): int
    {
        $treatment = $this->selectedTreatmentData;
        if (!$treatment) {
            return 0;
        }

        return $this->paymentOption === 'monthly'
            ? ($treatment['price_monthly'] ?? 0)
            : ($treatment['price_once_off'] ?? 0);
    }

    public function render()
    {
        return view('livewire.patient.book-appointment')
            ->layout('layouts.app');
    }
}
