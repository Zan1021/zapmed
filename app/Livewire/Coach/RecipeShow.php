<?php

namespace App\Livewire\Coach;

use App\Models\CoachProfile;
use App\Models\Recipe;
use App\Services\Coach\CoachClock;
use App\Services\Coach\MealPlanner;
use App\Services\Coach\RecipeCatalog;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * One recipe: Recipe and Nutrition tabs, a 1 to 4 serving stepper that scales the amounts, a heart, and "Add to plan".
 * A recipe the member may not see (not approved, or it breaks their diet, allergies or avoids) is a 404.
 */
class RecipeShow extends Component
{
    public Recipe $recipe;

    public string $tab = 'recipe';     // recipe | nutrition

    public int $servings = 1;

    public int $day = 0;

    public ?string $error = null;

    public function mount(Recipe $recipe, RecipeCatalog $catalog, MealPlanner $planner): void
    {
        $profile = CoachProfile::where('user_id', Auth::id())->first();
        abort_unless($catalog->visibleTo($recipe, $profile), 404);

        $this->recipe = $recipe;
        $this->day = min($planner->dayIndexToday($planner->planFor(Auth::user())), (int) config('coach.meal_plan.days') - 1);
        $this->day = max(0, $this->day);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'nutrition' ? 'nutrition' : 'recipe';
    }

    public function more(): void
    {
        $this->servings = min((int) config('coach.recipes.servings_max', 4), $this->servings + 1);
    }

    public function less(): void
    {
        $this->servings = max((int) config('coach.recipes.servings_min', 1), $this->servings - 1);
    }

    public function toggleFavourite(RecipeCatalog $catalog): void
    {
        $catalog->toggleFavourite(Auth::user(), $this->recipe);
    }

    public function addToPlan(MealPlanner $planner): void
    {
        $this->error = null;

        try {
            $planner->setMeal(Auth::user(), $this->day, $this->recipe->slot, $this->recipe, $this->servings);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        session()->flash('coach_toast', 'Added to your plan');
        $this->redirect(route('coach.plan', ['day' => $this->day]));
    }

    public function render(RecipeCatalog $catalog, MealPlanner $planner)
    {
        $plan = $planner->planFor(Auth::user());
        $start = CoachClock::today()->setDateFrom($plan->starts_on);
        $days = [];
        for ($i = 0; $i < (int) config('coach.meal_plan.days'); $i++) {
            $days[$i] = $start->addDays($i)->format('D j M');
        }

        return view('livewire.coach.recipe-show', [
            'favourite' => in_array($this->recipe->id, $catalog->favouriteIds(Auth::user()), true),
            'nutrition' => $this->recipe->nutrition($this->servings),
            'ingredients' => $this->recipe->scaledIngredients($this->servings),
            'days' => $days,
            'min' => (int) config('coach.recipes.servings_min', 1),
            'max' => (int) config('coach.recipes.servings_max', 4),
        ])->layout('layouts.coach', ['title' => $this->recipe->name, 'hideTabs' => true]);
    }
}
