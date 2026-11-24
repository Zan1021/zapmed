<?php

namespace App\Livewire\Coach;

use App\Models\CoachProfile;
use App\Models\Recipe;
use App\Services\Coach\MealPlanner;
use App\Services\Coach\RecipeCatalog;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Recipes and favourites (design screen 16): rows by meal slot, search, All / Favourites tabs, a heart on every card.
 * Only approved recipes that suit the member's diet, allergies and avoids are listed.
 */
class Recipes extends Component
{
    public string $tab = 'all';       // all | favourites

    public string $search = '';

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'favourites' ? 'favourites' : 'all';
    }

    public function toggleFavourite(int $recipeId, RecipeCatalog $catalog): void
    {
        $recipe = Recipe::find($recipeId);

        if ($recipe) {
            $catalog->toggleFavourite(Auth::user(), $recipe);
        }
    }

    /** "Build my plan around my N favourites": rebuild the plan now. Locked meals stay. */
    public function buildPlan(MealPlanner $planner): void
    {
        $planner->regenerate(Auth::user());
        session()->flash('coach_toast', 'Your plan is built around your favourites');

        $this->redirect(route('coach.plan'));
    }

    public function render(RecipeCatalog $catalog)
    {
        $user = Auth::user();
        $profile = CoachProfile::where('user_id', $user->id)->first();
        $favouriteIds = $catalog->favouriteIds($user, $profile);
        $term = mb_strtolower(trim($this->search));

        $rows = [];
        foreach (config('coach.recipes.slots') as $slot => $label) {
            $items = $catalog->suitable($profile, $slot)
                ->when($this->tab === 'favourites', fn ($c) => $c->filter(fn (Recipe $r) => in_array($r->id, $favouriteIds, true)))
                ->when($term !== '', fn ($c) => $c->filter(fn (Recipe $r) => str_contains(mb_strtolower($r->name), $term)))
                ->values();

            if ($items->isNotEmpty()) {
                $rows[$label] = $items;
            }
        }

        return view('livewire.coach.recipes', [
            'rows' => $rows,
            'favouriteIds' => $favouriteIds,
            'favouriteCount' => count($favouriteIds),
        ])->layout('layouts.coach', ['title' => 'Recipes']);
    }
}
