<?php

namespace App\Livewire\Coach;

use App\Models\Recipe;
use App\Services\Coach\CoachClock;
use App\Services\Coach\MealPlanner;
use App\Services\Coach\RecipeCatalog;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Meal plan (design screen 17): 7 days, four slots a day, a template chip, a targets box, and per-meal actions
 * (swap, trade with another day, lock, remove, favourite). Regenerate keeps locked meals.
 */
class Plan extends Component
{
    #[Url(as: 'day')]
    public ?int $day = null;

    /** null | template | meal | swap | trade */
    public ?string $sheet = null;

    public ?string $slot = null;

    public ?string $error = null;

    public function mount(MealPlanner $planner): void
    {
        $plan = $planner->planFor(Auth::user());
        $last = (int) config('coach.meal_plan.days') - 1;
        $this->day = max(0, min($last, $this->day ?? $planner->dayIndexToday($plan)));
    }

    public function selectDay(int $day): void
    {
        $this->day = max(0, min((int) config('coach.meal_plan.days') - 1, $day));
        $this->closeSheet();
    }

    public function closeSheet(): void
    {
        $this->sheet = null;
        $this->slot = null;
    }

    public function openTemplate(): void
    {
        $this->sheet = 'template';
    }

    public function chooseTemplate(string $key, MealPlanner $planner): void
    {
        $this->attempt(fn () => $planner->setTemplate(Auth::user(), $key), 'Plan updated');
        $this->closeSheet();
    }

    public function regenerate(MealPlanner $planner): void
    {
        $this->attempt(fn () => $planner->regenerate(Auth::user()), 'Plan rebuilt. Locked meals kept.');
    }

    /** Tap a meal (or an empty slot): open its menu. */
    public function openMeal(string $slot): void
    {
        $this->slot = $slot;
        $this->sheet = 'meal';
    }

    public function openSwap(): void
    {
        $this->sheet = 'swap';
    }

    public function openTrade(): void
    {
        $this->sheet = 'trade';
    }

    /** Pick a recipe for the open slot (swap, or fill an empty slot). */
    public function chooseMeal(int $recipeId, MealPlanner $planner): void
    {
        $recipe = Recipe::find($recipeId);
        if (! $recipe || ! $this->slot) {
            return;
        }

        $this->attempt(fn () => $planner->setMeal(Auth::user(), (int) $this->day, $this->slot, $recipe), 'Meal updated');
        $this->closeSheet();
    }

    public function tradeWith(int $otherDay, MealPlanner $planner): void
    {
        if (! $this->slot) {
            return;
        }

        $this->attempt(fn () => $planner->trade(Auth::user(), (int) $this->day, $this->slot, $otherDay), 'Meals traded');
        $this->closeSheet();
    }

    public function toggleLock(MealPlanner $planner): void
    {
        if (! $this->slot) {
            return;
        }

        $locked = $planner->toggleLock(Auth::user(), (int) $this->day, $this->slot);
        $this->toast($locked ? 'Meal locked. Regenerate will keep it.' : 'Meal unlocked');
        $this->closeSheet();
    }

    public function removeMeal(MealPlanner $planner): void
    {
        if ($this->slot) {
            $planner->remove(Auth::user(), (int) $this->day, $this->slot);
            $this->toast('Meal removed');
        }
        $this->closeSheet();
    }

    public function toggleFavourite(RecipeCatalog $catalog, MealPlanner $planner): void
    {
        $item = $this->slot ? $planner->dayItems($planner->planFor(Auth::user()), (int) $this->day)->get($this->slot) : null;

        if ($item) {
            $on = $catalog->toggleFavourite(Auth::user(), $item->recipe);
            $this->toast($on ? 'Added to favourites. Your next plan uses it.' : 'Removed from favourites');
        }
        $this->closeSheet();
    }

    /** Tick a meal as eaten (today only). */
    public function toggleEaten(string $slot, MealPlanner $planner): void
    {
        if ($planner->toggleEaten(Auth::user(), (int) $this->day, $slot) === null) {
            $this->error = 'You can only tick off today\'s meals.';

            return;
        }

        $this->error = null;
    }

    public function render(MealPlanner $planner, RecipeCatalog $catalog)
    {
        $user = Auth::user();
        $plan = $planner->planFor($user);
        $day = (int) $this->day;
        $templates = config('coach.meal_plan.templates');
        $start = CoachClock::today()->setDateFrom($plan->starts_on);
        $today = $planner->dayIndexToday($plan);

        $days = [];
        for ($i = 0; $i < (int) config('coach.meal_plan.days'); $i++) {
            $d = $start->addDays($i);
            $days[$i] = ['dow' => $d->format('D'), 'num' => $d->format('j'), 'label' => $d->format('D j M'), 'today' => $i === $today];
        }

        $items = $planner->dayItems($plan, $day);
        $open = $this->slot ? $items->get($this->slot) : null;

        return view('livewire.coach.plan', [
            'plan' => $plan,
            'templates' => $templates,
            'templateLabel' => $templates[$plan->template]['label'] ?? 'Plan',
            'days' => $days,
            'today' => $today,
            'totals' => $planner->dayTotals($plan, $day),
            'items' => $items,
            'slots' => config('coach.recipes.slots'),
            'targets' => config('coach.targets'),
            'open' => $open,
            'openIsFavourite' => $open && in_array($open->recipe_id, $catalog->favouriteIds($user), true),
            'options' => $this->sheet === 'swap' && $this->slot ? $planner->options($user, $this->slot, $plan)->reject(fn ($r) => $open && $r->id === $open->recipe_id) : collect(),
            'addOptions' => $this->sheet === 'meal' && $this->slot && ! $open ? $planner->options($user, $this->slot, $plan) : collect(),
        ])->layout('layouts.coach', ['title' => 'Meal plan']);
    }

    private function attempt(callable $fn, string $toast): void
    {
        try {
            $fn();
            $this->error = null;
            $this->toast($toast);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    private function toast(string $message): void
    {
        $this->dispatch('coach-toast', message: $message);
    }
}
