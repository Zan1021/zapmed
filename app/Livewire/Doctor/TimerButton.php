<?php

namespace App\Livewire\Doctor;

use App\Services\Doctor\TimeTracker;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Start/stop the doctor's timer for one patient record or one request. Shown inside the consultation and request pages. */
class TimerButton extends Component
{
    public ?int $patientId = null;
    public ?int $requestId = null;

    public function toggle(TimeTracker $tracker): void
    {
        $me = Auth::user();
        $running = $tracker->running($me);

        if ($running && $running->patient_id === $this->patientId && $running->doctor_request_id === $this->requestId) {
            $tracker->stop($me, $running);

            return;
        }

        $tracker->start($me, $this->patientId, $this->requestId);
    }

    public function render(TimeTracker $tracker)
    {
        $running = $tracker->running(Auth::user());

        return view('livewire.doctor.timer-button', [
            'isRunning' => $running && $running->patient_id === $this->patientId && $running->doctor_request_id === $this->requestId,
            'since' => $running?->started_at,
        ]);
    }
}
