<?php

namespace App\Livewire\Patient;

use App\Models\Appointment;
use App\Models\VideoSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class VideoCall extends Component
{
    public Appointment $appointment;
    public ?VideoSession $videoSession = null;

    public function mount(Appointment $appointment): void
    {
        // Ensure this patient owns this appointment
        if ($appointment->patient_id !== Auth::id()) {
            abort(403);
        }

        $this->appointment = $appointment;

        // Find active video session
        $this->videoSession = VideoSession::where('appointment_id', $appointment->id)
            ->where('patient_id', Auth::id())
            ->whereIn('status', ['waiting', 'in_progress'])
            ->first();
    }

    /**
     * Mark the session as in progress when patient joins.
     */
    public function markJoined(): void
    {
        if ($this->videoSession && $this->videoSession->status === 'waiting') {
            $this->videoSession->update(['status' => 'in_progress']);
        }
    }

    /**
     * Patient leaves the call.
     */
    public function leaveCall(): void
    {
        if ($this->videoSession && $this->videoSession->isActive()) {
            $this->videoSession->endSession('patient');
        }

        $this->redirect(route('patient.appointments'), navigate: true);
    }

    /**
     * Get the patient's video URL with token.
     */
    public function getPatientVideoUrlProperty(): ?string
    {
        if (!$this->videoSession) {
            return null;
        }

        return $this->videoSession->room_url . '?t=' . $this->videoSession->patient_token;
    }

    public function render()
    {
        return view('livewire.patient.video-call')
            ->layout('layouts.app');
    }
}
