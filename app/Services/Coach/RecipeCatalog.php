<?php

namespace App\Services\Coach;

use App\Models\CoachProfile;
use App\Models\FavouriteRecipe;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What recipes a member may see, and their favourites.
 *
 * A recipe is offered only when a dietitian has approved it and it is switched on. It is hidden from a member when it
 * breaks their diet, an allergy or an avoid (build guide 5.12: "Remove any meal that breaks the patient's diet,
 * allergies or avoids"). These checks are a safety rule, not a setting.
 */
class RecipeCatalog
{
    public function isSuitable(Recipe $recipe, ?CoachProfile $profile): bool
    {
        if (! $profile) {
            return true;
        }

        $diet = $profile->diet;
        if ($diet === 'low_carb') {
            if ($recipe->carbs_g > (float) config('coach.meal_plan.low_carb_diet_max_carbs_g', 28)) {
                return false;
            }
        } elseif ($diet && $diet !== 'everything' && ! in_array($diet, (array) $recipe->diet_tags, true)) {
            return false;
        }

        $allergies = (array) ($profile->allergies ?? []);
        if (array_intersect($allergies, (array) $recipe->allergens)) {
            return false;
        }

        // An avoid also rules out a recipe that lists it as an allergen (for example "Dairy" or "Eggs").
        $contains = array_merge((array) $recipe->avoid_tags, (array) $recipe->allergens);
        if (array_intersect((array) ($profile->avoids ?? []), $contains)) {
            return false;
        }

        return true;
    }

    /** @return Collection<int,Recipe> approved, switched on and suitable for this member, by slot then name */
    public function suitable(?CoachProfile $profile, ?string $slot = null): Collection
    {
        return Recipe::usable()
            ->when($slot, fn ($q) => $q->where('slot', $slot))
            ->orderBy('slot')->orderBy('name')
            ->get()
            ->filter(fn (Recipe $r) => $this->isSuitable($r, $profile))
            ->values();
    }

    /** True when this member may see the recipe at all. */
    public function visibleTo(Recipe $recipe, ?CoachProfile $profile): bool
    {
        return $recipe->is_active && $recipe->isApproved() && $this->isSuitable($recipe, $profile);
    }

    /** @return array<int,int> recipe ids this member has hearted, limited to recipes they can still see */
    public function favouriteIds(User $user, ?CoachProfile $profile = null): array
    {
        $profile ??= CoachProfile::where('user_id', $user->id)->first();
        $ids = FavouriteRecipe::where('user_id', $user->id)->pluck('recipe_id');

        if ($ids->isEmpty()) {
            return [];
        }

        return Recipe::usable()->whereIn('id', $ids)->get()
            ->filter(fn (Recipe $r) => $this->isSuitable($r, $profile))
            ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /** Heart or un-heart a recipe. @return bool true when it is now a favourite */
    public function toggleFavourite(User $user, Recipe $recipe): bool
    {
        $existing = FavouriteRecipe::where('user_id', $user->id)->where('recipe_id', $recipe->id)->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        $profile = CoachProfile::where('user_id', $user->id)->first();
        if (! $this->visibleTo($recipe, $profile)) {
            return false;
        }

        FavouriteRecipe::create(['user_id' => $user->id, 'recipe_id' => $recipe->id]);

        return true;
    }
}
