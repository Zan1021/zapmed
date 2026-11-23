<?php

namespace App\Livewire\Coach;

use App\Services\Coach\CoachMessaging;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Staff: the coach's threads with their assigned members, unread first. */
class Inbox extends Component
{
    public function render(CoachMessaging $messaging)
    {
        $threads = $messaging->inboxFor(Auth::user())->sortByDesc(fn ($t) => [$t['unread'] > 0, $t['last']->id])->values();

        $calls = app(\App\Services\Coach\CoachCalls::class);

        return view('livewire.coach.inbox', ['threads' => $threads, 'tz' => config('coach.timezone'), 'calls' => $calls->upcomingForCoach(Auth::user()), 'callsSvc' => $calls])
            ->layout('layouts.coach', ['title' => 'Coach inbox', 'hideTabs' => true]);
    }
}