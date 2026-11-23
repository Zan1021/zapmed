<?php

namespace App\Livewire\Coach;

use App\Models\User;
use App\Services\Coach\CoachMessaging;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/** Staff: one thread. Only the member's assigned coach may open it or reply. */
class Thread extends Component
{
    public User $patient;

    public string $draft = '';

    public ?string $error = null;

    public function mount(User $patient, CoachMessaging $messaging): void
    {
        abort_unless($messaging->coachFor($patient)?->is(Auth::user()), 403);
        $this->patient = $patient;
    }

    public function reply(CoachMessaging $messaging): void
    {
        try {
            $messaging->sendFromCoach(Auth::user(), $this->patient, $this->draft);
            $this->draft = '';
            $this->error = null;
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(CoachMessaging $messaging)
    {
        abort_unless($messaging->coachFor($this->patient)?->is(Auth::user()), 403);
        $messaging->markReadByCoach(Auth::user(), $this->patient);

        return view('livewire.coach.thread', [
            'messages' => $messaging->thread($this->patient),
            'tz' => config('coach.timezone'),
            'max' => (int) config('coach.messaging.max_length'),
        ])->layout('layouts.coach', ['title' => 'Message thread', 'hideTabs' => true]);
    }
}