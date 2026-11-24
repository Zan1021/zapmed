<?php

namespace App\Livewire\Coach;

use App\Services\Coach\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Log activity (design screen 13): search the workout library (Cardio, Strength, Mind and body, Sports), tap one,
 * choose how long, save. Calories use the workout's MET value and the member's weight.
 */
class LogActivity extends Component
{
    public string $search = '';

    public ?string $selected = null;

    public string $minutes = '30';

    public ?string $error = null;

    public function select(string $key, ActivityLogger $logger): void
    {
        if (array_key_exists($key, $logger->workouts())) {
            $this->selected = $key;
            $this->error = null;
        }
    }

    public function cancel(): void
    {
        $this->selected = null;
        $this->error = null;
    }

    public function setMinutes(int $minutes): void
    {
        if (in_array($minutes, config('coach.activity.minutes.quick', []), true)) {
            $this->minutes = (string) $minutes;
        }
    }

    public function save(ActivityLogger $logger): void
    {
        $value = trim($this->minutes);

        if (! $this->selected || $value === '' || ! ctype_digit($value)) {
            $this->error = 'Pick a workout and enter the minutes as a whole number.';

            return;
        }

        try {
            $logger->logWorkout(Auth::user(), $this->selected, (int) $value);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        session()->flash('coach_toast', 'Workout logged');
        $this->redirect(route('coach.activity'));
    }

    public function render(ActivityLogger $logger)
    {
        $term = mb_strtolower(trim($this->search));
        $groups = [];

        foreach (config('coach.activity.groups') as $key => $label) {
            $items = collect($logger->workouts())
                ->filter(fn ($w) => $w['group'] === $key && ($term === '' || str_contains(mb_strtolower($w['name']), $term)))
                ->all();
            if ($items) {
                $groups[$label] = $items;
            }
        }

        $weight = $logger->weightFor(Auth::user());
        $workout = $this->selected ? ($logger->workouts()[$this->selected] ?? null) : null;
        $mins = ctype_digit(trim($this->minutes)) ? (int) $this->minutes : 0;

        return view('livewire.coach.log-activity', [
            'groups' => $groups,
            'workout' => $workout,
            'estimate' => $workout && $mins > 0 ? $logger->kcalFor((float) $workout['met'], $weight, $mins) : null,
            'quick' => config('coach.activity.minutes.quick', []),
        ])->layout('layouts.coach', ['title' => 'Log activity', 'hideTabs' => true]);
    }
}
