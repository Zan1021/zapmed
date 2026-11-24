<?php

namespace App\Services\Coach;

use App\Models\CoachProfile;
use App\Models\MealEntry;
use App\Models\MealPlanItem;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * What the member ate today (build guide 5.10). "Today" is the South African day.
 *
 * Entries come from a recipe, from ticking a meal in the plan, or from a quick add (name, calories, protein).
 * Nutrition is copied onto the entry, so editing a recipe later never rewrites what was logged.
 * Ticking a plan meal creates one entry linked to that meal; unticking removes it again.
 */
class MealLogger
{
    public function __construct(private readonly RecipeCatalog $catalog)
    {
    }

    public function logRecipe(User $user, Recipe $recipe, ?string $slot = null, int $servings = 1, string $source = 'recipe', ?int $planItemId = null): MealEntry
    {
        $profile = CoachProfile::where('user_id', $user->id)->first();
        if (! $this->catalog->visibleTo($recipe, $profile)) {
            throw new InvalidArgumentException('That recipe is not available.');
        }

        $slot ??= $recipe->slot;
        $this->assertSlot($slot);
        $servings = max((int) config('coach.recipes.servings_min', 1), min((int) config('coach.recipes.servings_max', 4), $servings));
        $n = $recipe->nutrition($servings);

        return MealEntry::create([
            'user_id' => $user->id, 'logged_at' => now(), 'slot' => $slot, 'name' => $recipe->name, 'servings' => $servings,
            'kcal' => $n['kcal'], 'protein_g' => $n['protein_g'], 'fibre_g' => $n['fibre_g'], 'carbs_g' => $n['carbs_g'], 'fat_g' => $n['fat_g'],
            'source' => $source, 'recipe_id' => $recipe->id, 'meal_plan_item_id' => $planItemId,
        ]);
    }

    /** Quick add: name, calories and protein are required; the rest default to 0. */
    public function logQuick(User $user, string $slot, string $name, int $kcal, float $protein, float $fibre = 0, float $carbs = 0, float $fat = 0): MealEntry
    {
        $this->assertSlot($slot);
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Give the meal a name of up to 120 characters.');
        }
        if ($kcal < 1 || $kcal > 3000) {
            throw new InvalidArgumentException('Calories must be between 1 and 3000.');
        }
        foreach (['Protein' => $protein, 'Fibre' => $fibre, 'Carbs' => $carbs, 'Fat' => $fat] as $label => $g) {
            if ($g < 0 || $g > 500) {
                throw new InvalidArgumentException("{$label} must be between 0 and 500 g.");
            }
        }

        return MealEntry::create([
            'user_id' => $user->id, 'logged_at' => now(), 'slot' => $slot, 'name' => $name, 'servings' => 1,
            'kcal' => $kcal, 'protein_g' => round($protein, 1), 'fibre_g' => round($fibre, 1), 'carbs_g' => round($carbs, 1), 'fat_g' => round($fat, 1),
            'source' => 'quick',
        ]);
    }

    /** Tick a planned meal: one entry per meal, however many times it is called. */
    public function tickPlanItem(User $user, MealPlanItem $item): MealEntry
    {
        $existing = MealEntry::where('meal_plan_item_id', $item->id)->first();
        if ($existing) {
            return $existing;
        }

        return $this->logRecipe($user, $item->recipe, $item->slot, $item->servings, 'plan', $item->id);
    }

    public function untickPlanItem(MealPlanItem $item): void
    {
        MealEntry::where('meal_plan_item_id', $item->id)->delete();
    }

    /** Remove one of the member's own entries. Unticks the plan meal it came from. */
    public function remove(User $user, int $entryId): bool
    {
        $entry = MealEntry::where('user_id', $user->id)->find($entryId);
        if (! $entry) {
            return false;
        }

        if ($entry->meal_plan_item_id) {
            MealPlanItem::whereKey($entry->meal_plan_item_id)->update(['eaten_at' => null]);
        }

        return (bool) $entry->delete();
    }

    /** Log the same food again (from Recent), in a chosen slot, as a quick entry. */
    public function relog(User $user, int $entryId, string $slot): ?MealEntry
    {
        $old = MealEntry::where('user_id', $user->id)->find($entryId);
        if (! $old) {
            return null;
        }
        $this->assertSlot($slot);

        return MealEntry::create([
            'user_id' => $user->id, 'logged_at' => now(), 'slot' => $slot, 'name' => $old->name, 'servings' => $old->servings,
            'kcal' => $old->kcal, 'protein_g' => $old->protein_g, 'fibre_g' => $old->fibre_g, 'carbs_g' => $old->carbs_g, 'fat_g' => $old->fat_g,
            'source' => $old->source === 'plan' ? 'recipe' : $old->source, 'recipe_id' => $old->recipe_id,
        ]);
    }

    /** @return Collection<int,MealEntry> today's entries in slot order, then time */
    public function todayEntries(User $user): Collection
    {
        [$from, $to] = CoachClock::todayRangeUtc();
        $order = array_flip(Recipe::SLOTS);

        return MealEntry::where('user_id', $user->id)->where('logged_at', '>=', $from)->where('logged_at', '<', $to)
            ->orderBy('logged_at')->orderBy('id')->get()
            ->sortBy(fn (MealEntry $e) => $order[$e->slot] ?? 9)->values();
    }

    /** @return array{kcal:int,protein_g:float,fibre_g:float,carbs_g:float,fat_g:float,count:int} */
    public function todayTotals(User $user): array
    {
        $e = $this->todayEntries($user);

        return [
            'kcal' => (int) $e->sum('kcal'),
            'protein_g' => round((float) $e->sum('protein_g'), 1),
            'fibre_g' => round((float) $e->sum('fibre_g'), 1),
            'carbs_g' => round((float) $e->sum('carbs_g'), 1),
            'fat_g' => round((float) $e->sum('fat_g'), 1),
            'count' => $e->count(),
        ];
    }

    /** @return Collection<int,MealEntry> the member's latest different foods (newest first) for the Recent tab */
    public function recent(User $user, int $limit = 8): Collection
    {
        return MealEntry::where('user_id', $user->id)->latest('logged_at')->latest('id')->limit(80)->get()
            ->unique(fn (MealEntry $e) => mb_strtolower($e->name).'|'.$e->kcal)
            ->take($limit)->values();
    }

    private function assertSlot(string $slot): void
    {
        if (! in_array($slot, Recipe::SLOTS, true)) {
            throw new InvalidArgumentException('Unknown meal slot.');
        }
    }
}
