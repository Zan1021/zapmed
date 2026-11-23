<?php

namespace App\Livewire\Coach;

use App\Services\Coach\CoachMessaging;
use App\Services\Coach\CoachUnits;
use App\Services\Coach\MilestoneStats;
use App\Models\CoachProfile;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Milestone (design screen 25): percent of starting weight lost, three stats, a note from the coach, maintenance call. */
class Milestone extends Component
{
    public function render(CoachMessaging $messaging)
    {
        $user = Auth::user();
        $stats = MilestoneStats::forUser($user);
        $units = CoachUnits::normalise(CoachProfile::where('user_id', $user->id)->value('units'));

        return view('livewire.coach.milestone', [
            's' => $stats,
            'lostText' => $stats->kgLost !== null ? number_format(CoachUnits::fromKg($stats->kgLost, $units), 1).' '.$units : '-',
            'coachName' => $messaging->coachName($user),
            'hasCoach' => (bool) $messaging->coachFor($user),
            'note' => config('coach.milestone.note'),
            'empty' => config('coach.milestone.empty'),
        ])->layout('layouts.coach', ['title' => 'Milestone', 'hideTabs' => true]);
    }
}
