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

    // Step 1: Doctor selection
    public ?int $selectedDoctorId = null;
    public string $searchQuery = '';
    public string $specialityFilter = '';

    // Step 2: Date & time
    public string $selectedDate = '';
    public string $selectedTime = '';
    public string $appointmentType = 'general';
    public string $reason = '';

    // Step 3: Confirmation
    public ?Appointment $bookedAppointment = null;

    // Assessment link
    public ?int $assessmentId = null;

    public function mount(): void
    {
        $this->selectedDate = now()->addDay()->format('Y-m-d');
        $this->assessmentId = request()->query('assessment_id') ? (int) request()->query('assessment_id') : null;

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
     * Select a doctor and move to step 2.
     */
    public function selectDoctor(int $doctorId): void
    {
        $this->selectedDoctorId = $doctorId;
        $this->step = 2;
    }

    /**
     * Go back to doctor selection.
     */
    public function backToDoctors(): void
    {
        $this->step = 1;
        $this->selectedTime = '';
    }

    /**
     * Go back to time selection.
     */
    public function backToTimeSelection(): void
    {
        $this->step = 2;
    }

    /**
     * Select a time slot.
     */
    public function selectTime(string $time): void
    {
        $this->selectedTime = $time;
    }

    /**
     * Proceed to confirmation.
     */
    public function proceedToConfirmation(): void
    {
        $this->validate([
            'selectedDoctorId' => 'required|exists:users,id',
            'selectedDate' => 'required|date|after_or_equal:today',
            'selectedTime' => 'required',
            'appointmentType' => 'required|in:general,follow_up,chronic_renewal,new_patient',
        ]);

        $this->step = 3;
    }

    /**
     * Confirm and create the booking.
     */
    public function confirmBooking(): void
    {
        $doctor = User::with('doctorProfile')->find($this->selectedDoctorId);
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

        // Create payment record
        $payment = \App\Models\Payment::create([
            'patient_id' => Auth::id(),
            'appointment_id' => $this->bookedAppointment->id,
            'provider' => 'payfast',
            'amount' => $fee,
            'currency' => 'ZAR',
            'status' => 'pending',
            'description' => "Consultation with Dr. {$doctor->last_name} - {$this->bookedAppointment->reference}",
        ]);

        // Redirect to payment checkout
        $this->redirect(route('payment.checkout', $payment->reference));
    }

    /**
     * Get available doctors based on filters.
     */
    public function getDoctorsProperty()
    {
        return DoctorProfile::verified()
            ->acceptingPatients()
            ->with('user')
            ->when($this->searchQuery, function ($query) {
                $query->whereHas('user', function ($q) {
                    $q->where('first_name', 'like', "%{$this->searchQuery}%")
                      ->orWhere('last_name', 'like', "%{$this->searchQuery}%");
                });
            })
            ->when($this->specialityFilter, function ($query) {
                $query->where('speciality', $this->specialityFilter);
            })
            ->get();
    }

    /**
     * Get the selected doctor.
     */
    public function getSelectedDoctorProperty(): ?User
    {
        if (!$this->selectedDoctorId) {
            return null;
        }

        return User::with('doctorProfile')->find($this->selectedDoctorId);
    }

    /**
     * Get available time slots for selected date.
     */
    public function getAvailableSlotsProperty(): array
    {
        if (!$this->selectedDoctorId || !$this->selectedDate) {
            return [];
        }

        $doctor = $this->selectedDoctor;
        if (!$doctor || !$doctor->doctorProfile) {
            return [];
        }

        $allSlots = $doctor->doctorProfile->getTimeSlotsForDate($this->selectedDate);

        // Remove already-booked slots
        $bookedSlots = Appointment::where('doctor_id', $this->selectedDoctorId)
            ->where('appointment_date', $this->selectedDate)
            ->whereNotIn('status', ['cancelled'])
            ->pluck('start_time')
            ->map(fn ($time) => substr($time, 0, 5))
            ->toArray();

        return array_values(array_diff($allSlots, $bookedSlots));
    }

    /**
     * Get unique specialities for filter.
     */
    public function getSpecialitiesProperty(): array
    {
        return DoctorProfile::verified()
            ->distinct()
            ->pluck('speciality')
            ->sort()
            ->values()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.patient.book-appointment')
            ->layout('layouts.app');
    }
}
