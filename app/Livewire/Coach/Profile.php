<?php

namespace App\Livewire\Coach;

use App\Services\Coach\CoachMessaging;
use App\Services\Coach\CoachOnboarding;
use App\Services\Coach\CoachUnits;
use App\Services\Coach\ProfileSettings;
use App\Services\Coach\WeightStats;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Profile (design screen 22): programme card, goals, personal details, lifestyle, cycle switch, settings.
 * Every choice saves at once, like Reminders.
 */
class Profile extends Component
{
    public ?string $error = null;

    public ?string $goal = null;

    public ?string $height = null;

    public function mount(ProfileSettings $settings): void
    {
        $profile = $settings->profile(Auth::user());
        $this->goal = $profile->goal_weight_kg !== null ? (string) round((float) $profile->goal_weight_kg, 1) : null;
        $this->height = $profile->height_cm ? (string) $profile->height_cm : null;
    }

    public function saveGoal(ProfileSettings $settings): void
    {
        $this->attempt(fn () => $settings->setGoal(Auth::user(), (float) str_replace(',', '.', (string) $this->goal)), 'Goal saved');
    }

    public function saveHeight(ProfileSettings $settings): void
    {
        $this->attempt(fn () => $settings->setHeight(Auth::user(), (int) $this->height), 'Height saved');
    }

    public function setMonths(int $months, ProfileSettings $settings): void
    {
        $this->attempt(fn () => $settings->setTargetMonths(Auth::user(), $months));
    }

    public function setDiet(string $diet, ProfileSettings $settings): void
    {
        $this->attempt(fn () => $settings->setDiet(Auth::user(), $diet));
    }

    public function setActivity(string $level, ProfileSettings $settings): void
    {
        $this->attempt(fn () => $settings->setActivity(Auth::user(), $level));
    }

    public function toggle(string $field, string $key, ProfileSettings $settings): void
    {
        $this->attempt(fn () => $settings->toggleInList(Auth::user(), $field, $key));
    }

    public function toggleCycle(ProfileSettings $settings): void
    {
        $this->attempt(function () use ($settings) {
            $on = ! $settings->profile(Auth::user())->track_cycle;
            $settings->setTrackCycle(Auth::user(), $on);
        });
    }

    public function setUnits(string $unit, ProfileSettings $settings): void
    {
        $this->attempt(fn () => $settings->setUnits(Auth::user(), $unit));
    }

    private function attempt(callable $do, ?string $toast = null): void
    {
        try {
            $do();
            $this->error = null;
            if ($toast) {
                $this->dispatch('coach-toast', message: $toast);
            }
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(ProfileSettings $settings, CoachOnboarding $onboarding, CoachMessaging $messaging)
    {
        $user = Auth::user();
        $profile = $settings->profile($user);
        $weight = WeightStats::forUser($user);
        $units = CoachUnits::normalise($profile->units);

        return view('livewire.coach.profile', [
            'user' => $user,
            'p' => $profile,
            'units' => $units,
            'allowLb' => (bool) config('coach.units.allow_lb'),
            'startText' => CoachUnits::format($weight->start, $units),
            'currentText' => CoachUnits::format($weight->current, $units),
            'o' => config('coach.profile'),
            'targets' => config('coach.targets'),
            'coachName' => $messaging->coachName($user),
            'showCycleSwitch' => $onboarding->showsCycleSwitch($user),
            'birthYear' => $user->date_of_birth?->format('Y'),
            'minGoalKg' => config('coach.weight.min_goal_kg'),
        ])->layout('layouts.coach', ['title' => 'Profile']);
    }
}
