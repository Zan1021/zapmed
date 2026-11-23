<?php

namespace App\Livewire\Coach;

use App\Enums\UserRole;
use App\Models\CoachNote;
use App\Models\User;
use App\Services\Coach\CoachAccess;
use App\Services\Coach\CoachCalls;
use App\Services\Coach\CoachPatientBoard;
use App\Services\Coach\CoachUnits;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Staff: one member's tracking summary. The assigned coach and a doctor who has seen the member may open it.
 * Only the coach can write notes. Private notes are visible to their author alone; shared notes to coach + doctor.
 */
class PatientView extends Component
{
    public User $patient;

    public string $visibility = CoachNote::PRIVATE;

    public string $draft = '';

    public ?string $error = null;

    public function mount(User $patient, CoachAccess $access): void
    {
        abort_unless($access->canView(Auth::user(), $patient), 403);
        $this->patient = $patient;
    }

    public function addNote(CoachPatientBoard $board): void
    {
        try {
            $board->addNote(Auth::user(), $this->patient, $this->visibility, $this->draft);
            $this->draft = '';
            $this->error = null;
            $this->dispatch('coach-toast', message: 'Note saved');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function deleteNote(int $id, CoachPatientBoard $board): void
    {
        $board->deleteNote(Auth::user(), $id);
        $this->dispatch('coach-toast', message: 'Note deleted');
    }

    public function render(CoachPatientBoard $board, CoachAccess $access)
    {
        // Access is re-checked on every render: an assignment that ended mid-session closes the page.
        abort_unless($access->canView(Auth::user(), $this->patient), 403);
        $me = Auth::user();
        $data = $board->summary($this->patient);
        $units = CoachUnits::normalise($data['profile']?->units);

        return view('livewire.coach.patient-view', $data + [
            'notes' => $board->notesFor($me, $this->patient),
            'canWrite' => $me->role === UserRole::HealthCoach,
            'me' => $me,
            'units' => $units,
            'tz' => config('coach.timezone'),
            'calls_svc' => app(CoachCalls::class),
            'max' => (int) config('coach.board.note_max_length', 2000),
            'profileOptions' => config('coach.profile'),
        ])->layout('layouts.coach', ['title' => 'Member', 'hideTabs' => true]);
    }
}
