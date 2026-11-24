<?php

namespace App\Livewire\Doctor;

use App\Models\PatientNote;
use App\Services\Doctor\PatientNoteService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** "Notes for me": staff notes addressed to this person or their role. Doctors, Operations and coaches. */
class StaffNotes extends Component
{
    public string $filter = 'open';      // open (needs an action) | all

    public function done(int $id, PatientNoteService $service): void
    {
        $this->resetErrorBag();
        $note = PatientNote::with('audiences', 'patient')->findOrFail($id);
        $service->completeAction(Auth::user(), $note);
    }

    public function read(int $id, PatientNoteService $service): void
    {
        $service->markRead(Auth::user(), PatientNote::with('audiences', 'patient')->findOrFail($id));
    }

    public function render(PatientNoteService $service)
    {
        return view('livewire.doctor.staff-notes', [
            'notes' => $service->inbox(Auth::user(), $this->filter === 'open'),
        ])->layout('layouts.app');
    }
}
