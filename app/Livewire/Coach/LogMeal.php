<?php

namespace App\Livewire\Coach;

use App\Models\MealPlanItem;
use App\Services\Coach\CoachClock;
use App\Services\Coach\MealLogger;
use App\Services\Coach\MealPlanner;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Log a meal (design screen 19): pick the slot, tick today's planned meal in one tap, re-log something recent, or quick
 * add a name with calories and protein. Food scan, menu scan and voice log are later (they need real services).
 */
class LogMeal extends Component
{
    #[Url]
    public string $slot = '';

    public string $name = '';

    public string $kcal = '';

    public string $protein = '';

    public string $fibre = '';

    public string $carbs = '';

    public string $fat = '';

    public ?string $error = null;

    public function mount(): void
    {
        $slots = array_keys(config('coach.recipes.slots'));

        if (! in_array($this->slot, $slots, true)) {
            $hour = (int) CoachClock::now()->format('G');
            $this->slot = match (true) {
                $hour < 10 => 'breakfast',
                $hour < 15 => 'lunch',
                $hour < 20 => 'dinner',
                default => 'snack',
            };
        }
    }

    public function setSlot(string $slot): void
    {
        if (array_key_exists($slot, config('coach.recipes.slots'))) {
            $this->slot = $slot;
            $this->error = null;
        }
    }

    public function quickAdd(MealLogger $logger): void
    {
        $num = fn (string $v) => (float) str_replace(',', '.', trim($v) === '' ? '0' : trim($v));

        foreach (['kcal' => $this->kcal, 'protein' => $this->protein] as $label => $v) {
            if (trim($v) === '' || ! is_numeric(str_replace(',', '.', trim($v)))) {
                $this->error = 'Enter a name, calories and protein.';

                return;
            }
        }
        foreach ([$this->fibre, $this->carbs, $this->fat] as $v) {
            if (trim($v) !== '' && ! is_numeric(str_replace(',', '.', trim($v)))) {
                $this->error = 'Fibre, carbs and fat must be numbers.';

                return;
            }
        }

        try {
            $logger->logQuick(Auth::user(), $this->slot, $this->name, (int) round($num($this->kcal)), $num($this->protein), $num($this->fibre), $num($this->carbs), $num($this->fat));
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        session()->flash('coach_toast', 'Meal logged');
        $this->redirect(route('coach.nutrition'));
    }

    /** One tap: tick today's planned meal for this slot. */
    public function logPlanned(MealPlanner $planner): void
    {
        $plan = $planner->planFor(Auth::user());
        $day = $planner->dayIndexToday($plan);

        try {
            $planner->toggleEaten(Auth::user(), $day, $this->slot) === true
                ? session()->flash('coach_toast', 'Meal logged')
                : session()->flash('coach_toast', 'Meal unticked');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->redirect(route('coach.nutrition'));
    }

    public function relog(int $entryId, MealLogger $logger): void
    {
        if ($logger->relog(Auth::user(), $entryId, $this->slot)) {
            session()->flash('coach_toast', 'Meal logged');
            $this->redirect(route('coach.nutrition'));
        }
    }

    public function render(MealPlanner $planner, MealLogger $logger)
    {
        $user = Auth::user();
        $plan = $planner->planFor($user);
        $day = $planner->dayIndexToday($plan);
        $planned = $day >= 0 && $day < (int) config('coach.meal_plan.days')
            ? $planner->dayItems($plan, $day)->get($this->slot)
            : null;

        return view('livewire.coach.log-meal', [
            'slots' => config('coach.recipes.slots'),
            'planned' => $planned instanceof MealPlanItem && ! $planned->eaten_at ? $planned : null,
            'recent' => $logger->recent($user),
        ])->layout('layouts.coach', ['title' => 'Log a meal', 'hideTabs' => true]);
    }
}
