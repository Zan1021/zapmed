<?php

namespace App\Livewire\Doctor;

use App\Models\Appointment;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Doctor\DoctorRequestService;
use App\Services\Doctor\TimeTracker;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** A doctor's time sheet: running timer, manual entries, edits with a reason, and the monthly invoice summary. */
class TimeSheet extends Component
{
    public string $month = '';

    // manual entry form
    public string $target = '';          // "patient:12" | "request:5"
    public string $date = '';
    public ?int $minutes = null;
    public string $note = '';

    // edit form (one entry at a time)
    public ?int $editing = null;
    public ?int $newMinutes = null;
    public string $reason = '';

    public function mount(): void
    {
        $tz = config('doctor.time.timezone');
        $this->month = now($tz)->format('Y-m');
        $this->date = now($tz)->format('Y-m-d');
    }

    public function stop(TimeTracker $tracker): void
    {
        if ($entry = $tracker->running(Auth::user())) {
            $tracker->stop(Auth::user(), $entry);
        }
    }

    public function addManual(TimeTracker $tracker): void
    {
        $this->resetErrorBag();
        [$patientId, $requestId] = $this->parseTarget();

        $tracker->addManual(Auth::user(), (int) $this->minutes, $this->date, $patientId, $requestId, $this->note);

        $this->reset(['minutes', 'note', 'target']);
    }

    public function edit(int $id): void
    {
        $entry = TimeEntry::where('user_id', Auth::id())->findOrFail($id);
        $this->editing = $entry->id;
        $this->newMinutes = $entry->minutes;
        $this->reason = '';
    }

    public function saveEdit(TimeTracker $tracker): void
    {
        $this->resetErrorBag();
        $entry = TimeEntry::findOrFail($this->editing);
        $tracker->adjust(Auth::user(), $entry, (int) $this->newMinutes, $this->reason);
        $this->reset(['editing', 'newMinutes', 'reason']);
    }

    public function cancelEdit(): void
    {
        $this->reset(['editing', 'newMinutes', 'reason']);
    }

    /** @return array{0:?int,1:?int} */
    private function parseTarget(): array
    {
        [$kind, $id] = array_pad(explode(':', $this->target, 2), 2, null);

        return [
            $kind === 'patient' ? (int) $id : null,
            $kind === 'request' ? (int) $id : null,
        ];
    }

    public function render(TimeTracker $tracker, DoctorRequestService $requests)
    {
        $me = Auth::user();

        return view('livewire.doctor.time-sheet', [
            'summary' => $tracker->monthlySummary($me, $this->month),
            'running' => $tracker->running($me)?->load(['patient', 'request']),
            'patients' => User::whereIn('id', app(\App\Services\Coach\CoachAccess::class)->doctorPatientIds($me))
                ->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'openRequests' => $requests->visibleTo($me)->where('status', '!=', 'completed')->limit(100)->get(),
            'tz' => config('doctor.time.timezone'),
        ])->layout('layouts.app');
    }
}
