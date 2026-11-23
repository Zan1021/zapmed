<?php

namespace App\Livewire\Coach;

use App\Services\Coach\CoachOnboarding;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Coach App onboarding: screen 1 (worry question) and screen 2 (plan reveal + cycle switch + welcome call).
 */
class Onboarding extends Component
{
    public int $step = 1;

    public ?string $worry = null;

    public bool $trackCycle = false;

    public function mount(CoachOnboarding $onboarding): void
    {
        $user = Auth::user();

        if ($onboarding->isOnboarded($user)) {
            $this->redirectRoute('coach.today');

            return;
        }

        $this->trackCycle = $onboarding->defaultTrackCycle($user);
    }

    public function chooseWorry(string $key, CoachOnboarding $onboarding): void
    {
        if (array_key_exists($key, $onboarding->worries())) {
            $this->worry = $key;
        }
    }

    public function next(): void
    {
        if ($this->step === 1 && $this->worry) {
            $this->step = 2;
        }
    }

    public function back(): void
    {
        $this->step = 1;
    }

    public function toggleCycle(): void
    {
        $this->trackCycle = ! $this->trackCycle;
    }

    public function finish(CoachOnboarding $onboarding)
    {
        if (! $this->worry) {
            $this->step = 1;

            return;
        }

        $onboarding->complete(Auth::user(), $this->worry, $this->trackCycle);

        return $this->redirectRoute('coach.today');
    }

    /** Finish set-up and go straight to booking the welcome call. */
    public function finishAndBook(CoachOnboarding $onboarding)
    {
        if (! $this->worry) {
            $this->step = 1;

            return;
        }

        $onboarding->complete(Auth::user(), $this->worry, $this->trackCycle);

        return $this->redirectRoute('coach.book', ['for' => 'welcome']);
    }

    public function render(CoachOnboarding $onboarding)
    {
        return view('livewire.coach.onboarding', [
            'worries' => $onboarding->worries(),
            'reply' => $this->worry ? ($onboarding->worries()[$this->worry]['reply'] ?? null) : null,
            'showCycleSwitch' => $onboarding->showsCycleSwitch(Auth::user()),
            'targets' => config('coach.targets'),
            'intro' => config('coach.onboarding.amy_intro'),
            'planNote' => config('coach.onboarding.plan_note'),
            'hasCoach' => (bool) app(\App\Services\Coach\CoachMessaging::class)->coachFor(Auth::user()),
        ])->layout('layouts.coach', ['title' => 'Welcome', 'hideTabs' => true]);
    }
}
