<?php

namespace App\Livewire\Coach;

use App\Services\Coach\CoachEscalation;
use App\Services\Coach\CoachMessaging;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The coach menu (design screen 23) opened from the Coach tab: quick question (later), message the coach,
 * book a call (later), and "Something feels wrong", which goes straight to a doctor with no queue.
 */
class CoachSheet extends Component
{
    public bool $doctorReached = false;

    public function feelsWrong(CoachEscalation $escalation): void
    {
        $escalation->selfReport(Auth::user());
        $this->doctorReached = true;
    }

    public function render(CoachMessaging $messaging)
    {
        $user = Auth::user();

        return view('livewire.coach.coach-sheet', [
            'coachName' => $messaging->coachName($user),
            'hasCoach' => (bool) $messaging->coachFor($user),
            'online' => $messaging->isOnline(),
            'unread' => $messaging->unreadForPatient($user),
            'emergency' => config('coach.side_effect_copy.emergency'),
            'onlineText' => config('coach.messaging.online_text'),
            'offlineText' => config('coach.messaging.offline_text'),
        ]);
    }
}