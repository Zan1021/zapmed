<?php

namespace App\Livewire\Doctor;

use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Consultation;
use App\Models\VideoSession;
use App\Services\DailyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ConsultationScreen extends Component
{
    public Appointment $appointment;
    public ?Consultation $consultation = null;
    public ?Assessment $assessment = null;
    public ?VideoSession $videoSession = null;

    // Video call state
    public bool $showVideoPanel = false;

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

        // Load linked assessment if any
        $this->assessment = Assessment::where('appointment_id', $appointment->id)->first();

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

        // Load active video session if one exists
        $this->videoSession = VideoSession::where('appointment_id', $appointment->id)
            ->whereIn('status', ['waiting', 'in_progress'])
            ->first();

        if ($this->videoSession) {
            $this->showVideoPanel = true;
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
     * Start a video call — creates Daily.co room and tokens.
     */
    public function startVideoCall(): void
    {
        $daily = app(DailyService::class);

        if (!$daily->isConfigured()) {
            session()->flash('error', 'Video calling is not configured. Please set DAILY_API_KEY in environment.');
            return;
        }

        // Don't create a new room if one already exists
        if ($this->videoSession && $this->videoSession->isActive()) {
            $this->showVideoPanel = true;
            return;
        }

        try {
            // Create the room
            $room = $daily->createRoom($this->appointment->reference);

            // Generate tokens
            $doctorName = 'Dr. ' . Auth::user()->last_name;
            $patientName = $this->appointment->patient->first_name . ' ' . $this->appointment->patient->last_name;

            $doctorToken = $daily->createMeetingToken($room['name'], $doctorName, isOwner: true);
            $patientToken = $daily->createMeetingToken($room['name'], $patientName, isOwner: false);

            // Store the session
            $this->videoSession = VideoSession::create([
                'appointment_id' => $this->appointment->id,
                'consultation_id' => $this->consultation->id,
                'doctor_id' => Auth::id(),
                'patient_id' => $this->appointment->patient_id,
                'room_name' => $room['name'],
                'room_url' => $room['url'],
                'doctor_token' => $doctorToken,
                'patient_token' => $patientToken,
                'status' => 'waiting',
                'started_at' => now(),
            ]);

            $this->showVideoPanel = true;

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to start video call: ' . $e->getMessage());
        }
    }

    /**
     * End the video call.
     */
    public function endVideoCall(): void
    {
        if (!$this->videoSession) {
            return;
        }

        $this->videoSession->endSession('doctor');

        // Optionally delete the room from Daily.co
        try {
            $daily = app(DailyService::class);
            $daily->deleteRoom($this->videoSession->room_name);
        } catch (\Exception $e) {
            // Room deletion failure is non-critical — room expires anyway
        }

        $this->showVideoPanel = false;
        $this->videoSession = null;
    }

    /**
     * Get the doctor's video call URL with token.
     */
    public function getDoctorVideoUrlProperty(): ?string
    {
        if (!$this->videoSession) {
            return null;
        }

        return $this->videoSession->room_url . '?t=' . $this->videoSession->doctor_token;
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

        // End video call if still active
        if ($this->videoSession && $this->videoSession->isActive()) {
            $this->endVideoCall();
        }

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
