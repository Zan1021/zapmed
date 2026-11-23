<?php

namespace App\Livewire\Coach;

use App\Models\CoachProfile;
use App\Services\Coach\CoachEscalation;
use App\Services\Coach\CycleCalendar;
use App\Services\Coach\SideEffectLogger;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Log side effect (design screens 8 and 9). Scores live in the browser until Save, then one request saves them,
 * raises the doctor flag when a level is reached, and switches to the saved screen (normal or red).
 */
class LogSideEffect extends Component
{
    public string $newName = '';

    public ?string $error = null;

    public bool $saved = false;

    /** @var array<string,int> flagged symptom => score from the save just made */
    public array $flagged = [];

    public bool $doctorReached = false;

    public function add(SideEffectLogger $logger): void
    {
        try {
            $logger->addCustom(Auth::user(), $this->newName);
            $this->newName = '';
            $this->error = null;
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function hide(string $name, SideEffectLogger $logger): void
    {
        $logger->hide(Auth::user(), $name);
    }

    public function restore(SideEffectLogger $logger): void
    {
        $logger->restoreHidden(Auth::user());
    }

    /** @param array<string,mixed> $scores */
    public function save(array $scores, SideEffectLogger $logger): void
    {
        // A symptom hidden in this same visit may still be in the browser's scores. Only the visible list counts.
        $scores = array_intersect_key($scores, array_flip($logger->visible(Auth::user())));

        try {
            $result = $logger->save(Auth::user(), $scores);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->error = null;
        $this->flagged = $result['flagged'];
        $this->saved = true;
    }

    /** "Reach Dr Anke now": an immediate request on top of any symptom flag already raised. */
    public function reachDoctor(CoachEscalation $escalation): void
    {
        $escalation->selfReport(Auth::user());
        $this->doctorReached = true;
    }

    public function render(SideEffectLogger $logger)
    {
        $user = Auth::user();
        $profile = CoachProfile::where('user_id', $user->id)->first();
        $cycle = CycleCalendar::status($profile);

        return view('livewire.coach.log-side-effect', [
            'symptoms' => $logger->visible($user),
            'hiddenCount' => $logger->hiddenCount($user),
            'copy' => config('coach.side_effect_copy'),
            'luteal' => ($cycle['phase'] ?? null) === 'luteal',
            'abdominal' => config('coach.flags.abdominal_symptom'),
            'abdominalLevel' => (int) config('coach.flags.abdominal_level'),
            'anyLevel' => (int) config('coach.flags.any_level'),
        ])->layout('layouts.coach', ['title' => 'Side effects', 'hideTabs' => true]);
    }
}