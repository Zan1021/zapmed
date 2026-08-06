<?php

namespace App\Livewire\Doctor;

use App\Models\Appointment;
use App\Models\Consultation;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ConsultationScreen extends Component
{
    public Appointment $appointment;
    public ?Consultation $consultation = null;

    // Clinical notes
    public string $presenting_complaint = '';
    public string $history_of_presenting_illness = '';
    public string $examination_findings = '';
    public string $diagnosis = '';
    public string $icd10_code = '';
    public string $treatment_plan = '';
    public string $doctor_notes = '';
    public bool $follow_up_required = false;
    public string $follow_up_date = '';
    public string $follow_up_notes = '';

    public function mount(Appointment $appointment): void
    {
        // Ensure this doctor owns this appointment
        if ($appointment->doctor_id !== Auth::id()) {
            abort(403);
        }

        $this->appointment = $appointment->load(['patient.patientProfile.allergies', 'patient.patientProfile.chronicConditions']);

        // Load existing consultation or create one
        $this->consultation = Consultation::firstOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'patient_id' => $appointment->patient_id,
                'doctor_id' => Auth::id(),
                'status' => 'in_progress',
                'started_at' => now(),
            ]
        );

        // Mark appointment as in progress
        if ($appointment->status !== 'in_progress' && $appointment->status !== 'completed') {
            $appointment->update(['status' => 'in_progress']);
        }

        // Pre-fill from existing consultation
        $this->presenting_complaint = $this->consultation->presenting_complaint ?? '';
        $this->history_of_presenting_illness = $this->consultation->history_of_presenting_illness ?? '';
        $this->examination_findings = $this->consultation->examination_findings ?? '';
        $this->diagnosis = $this->consultation->diagnosis ?? '';
        $this->icd10_code = $this->consultation->icd10_code ?? '';
        $this->treatment_plan = $this->consultation->treatment_plan ?? '';
        $this->doctor_notes = $this->consultation->doctor_notes ?? '';
        $this->follow_up_required = $this->consultation->follow_up_required ?? false;
        $this->follow_up_date = $this->consultation->follow_up_date?->format('Y-m-d') ?? '';
        $this->follow_up_notes = $this->consultation->follow_up_notes ?? '';
    }

    /**
     * Auto-save notes as doctor types.
     */
    public function saveNotes(): void
    {
        $this->consultation->update([
            'presenting_complaint' => $this->presenting_complaint ?: null,
            'history_of_presenting_illness' => $this->history_of_presenting_illness ?: null,
            'examination_findings' => $this->examination_findings ?: null,
            'diagnosis' => $this->diagnosis ?: null,
            'icd10_code' => $this->icd10_code ?: null,
            'treatment_plan' => $this->treatment_plan ?: null,
            'doctor_notes' => $this->doctor_notes ?: null,
            'follow_up_required' => $this->follow_up_required,
            'follow_up_date' => $this->follow_up_date ?: null,
            'follow_up_notes' => $this->follow_up_notes ?: null,
        ]);

        $this->dispatch('notes-saved');
    }

    /**
     * Complete the consultation.
     */
    public function completeConsultation(): void
    {
        $this->validate([
            'presenting_complaint' => 'required|string',
            'diagnosis' => 'required|string',
            'treatment_plan' => 'required|string',
        ]);

        $this->saveNotes();

        $this->consultation->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->appointment->update([
            'status' => 'completed',
        ]);

        $this->redirect(route('doctor.dashboard'), navigate: true);
    }

    /**
     * Get patient age.
     */
    public function getPatientAgeProperty(): ?int
    {
        $dob = $this->appointment->patient->date_of_birth;
        return $dob ? $dob->age : null;
    }

    public function render()
    {
        return view('livewire.doctor.consultation-screen')
            ->layout('layouts.app');
    }
}
