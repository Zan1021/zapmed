<?php

namespace App\Livewire\Coach;

use App\Services\Coach\CoachMessaging;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/** The member's thread with their coach (design: "Message Amy"). New replies appear by themselves every 15 seconds. */
class Messages extends Component
{
    public string $draft = '';

    public ?string $error = null;

    public function send(CoachMessaging $messaging): void
    {
        try {
            $messaging->sendFromPatient(Auth::user(), $this->draft);
            $this->draft = '';
            $this->error = null;
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(CoachMessaging $messaging)
    {
        $user = Auth::user();
        $messaging->markReadByPatient($user);

        return view('livewire.coach.messages', [
            'coachName' => $messaging->coachName($user),
            'hasCoach' => (bool) $messaging->coachFor($user),
            'messages' => $messaging->thread($user),
            'online' => $messaging->isOnline(),
            'tz' => config('coach.timezone'),
            'max' => (int) config('coach.messaging.max_length'),
            'urgent' => config('coach.messaging.urgent_text'),
            'unassigned' => config('coach.messaging.unassigned_text'),
        ])->layout('layouts.coach', ['title' => 'Messages', 'hideTabs' => true]);
    }
}