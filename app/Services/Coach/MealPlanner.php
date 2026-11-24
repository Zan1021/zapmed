<?php

namespace App\Services\Coach;

use App\Models\CoachProfile;
use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The 7-day meal plan (build guide 5.12).
 *
 * Rules that are code, not settings:
 *  - Only approved recipes that suit the member's diet, allergies and avoids are ever used (RecipeCatalog).
 *  - Favourites first. 1 or 2 non-favourite meals a day (breakfast, lunch or dinner) are tagged "new for you".
 *  - Never the same meal as yesterday in the same slot (unless it is the only suitable meal for that slot).
 *  - If a day's protein is below target, the snack becomes the highest-protein snack, tagged "protein boost".
 *  - Locked meals survive a regenerate. Changing diet, allergies or avoids removes every meal that is now banned,
 *    locked or not, because that is a safety rule.
 * Template limits (config coach.meal_plan.templates) narrow the choice but are dropped for a slot when they would leave
 * nothing to choose from.
 */
class MealPlanner
{
    public function __construct(private readonly RecipeCatalog $catalog, private readonly MealLogger $logger)
    {
    }

    // --- Reading ---------------------------------------------------------------------------------------------------

    /** The member's current plan, built on first use and rolled to a fresh week once the 7 days are over. */
    public function planFor(User $user): MealPlan
    {
        $plan = MealPlan::where('user_id', $user->id)->first();

        if (! $plan) {
            $plan = MealPlan::create([
                'user_id' => $user->id,
                'template' => config('coach.meal_plan.default_template'),
                'starts_on' => CoachClock::today()->toDateString(),
            ]);
            $this->fill($plan, $user);

            return $plan;
        }

        if ($this->dayIndexToday($plan) >= $this->days()) {
            $plan->items()->delete();
            $plan->update(['starts_on' => CoachClock::today()->toDateString()]);
            $this->fill($plan, $user);
        }

        return $plan;
    }

    /** 0-based day of the plan that is today (may be 7 or more when the plan is out of date). */
    public function dayIndexToday(MealPlan $plan): int
    {
        return CoachClock::daysBetween($plan->starts_on, CoachClock::today());
    }

    /** @return Collection<int,MealPlanItem> the items of one day keyed by slot, in slot order */
    public function dayItems(MealPlan $plan, int $day): Collection
    {
        $items = $plan->items()->with('recipe')->where('day_index', $day)->get()->keyBy('slot');

        return collect(Recipe::SLOTS)->mapWithKeys(fn ($slot) => [$slot => $items->get($slot)])->filter();
    }

    /**
     * Totals for one day and the tag shown on the targets box.
     *
     * @return array{kcal:int,protein_g:float,fibre_g:float,tag:?string,count:int}
     */
    public function dayTotals(MealPlan $plan, int $day): array
    {
        $items = $this->dayItems($plan, $day);
        $kcal = 0;
        $protein = 0.0;
        $fibre = 0.0;
        foreach ($items as $item) {
            $n = $item->recipe->nutrition($item->servings);
            $kcal += $n['kcal'];
            $protein += $n['protein_g'];
            $fibre += $n['fibre_g'];
        }

        $band = config('coach.meal_plan.on_target');
        $kcalTarget = (float) config('coach.targets.kcal');
        $proteinTarget = (float) config('coach.targets.protein_g');
        $tag = null;
        if ($items->isNotEmpty()) {
            $onTarget = $kcal >= $kcalTarget * $band['kcal_min_pct'] / 100
                && $kcal <= $kcalTarget * $band['kcal_max_pct'] / 100
                && $protein >= $proteinTarget * $band['protein_min_pct'] / 100;
            $tag = $onTarget ? 'On target' : 'Close, tweak a meal';
        }

        return ['kcal' => $kcal, 'protein_g' => round($protein, 1), 'fibre_g' => round($fibre, 1), 'tag' => $tag, 'count' => $items->count()];
    }

    /** Recipes the member could put in this slot: suitable, template limits applied unless that leaves nothing. */
    public function options(User $user, string $slot, ?MealPlan $plan = null): Collection
    {
        $this->assertSlot($slot);
        $plan ??= $this->planFor($user);
        $profile = CoachProfile::where('user_id', $user->id)->first();

        return $this->pool($plan->template, $slot, $this->catalog->suitable($profile, $slot));
    }

    // --- Changing --------------------------------------------------------------------------------------------------

    /** Rebuild the plan. Locked meals stay (unless now banned). Optionally switch template first. */
    public function regenerate(User $user, ?string $template = null, ?int $seed = null): MealPlan
    {
        $plan = $this->planFor($user);

        return DB::transaction(function () use ($plan, $user, $template, $seed) {
            if ($template !== null) {
                $this->assertTemplate($template);
                $plan->template = $template;
            }
            $plan->generated_at = now();
            $plan->save();

            $plan->items()->where('locked', false)->delete();
            $this->purgeBanned($plan, $user);
            $this->fill($plan, $user, $seed);

            return $plan->refresh();
        });
    }

    public function setTemplate(User $user, string $template, ?int $seed = null): MealPlan
    {
        return $this->regenerate($user, $template, $seed);
    }

    /**
     * The member changed diet, allergies or avoids. Every meal that is now banned is removed (locked ones too) and
     * the emptied slots are refilled. Meals that are still fine are left alone.
     */
    public function afterProfileChange(User $user): void
    {
        $plan = MealPlan::where('user_id', $user->id)->first();
        if (! $plan || $this->dayIndexToday($plan) >= $this->days()) {
            return;
        }

        DB::transaction(function () use ($plan, $user) {
            $this->purgeBanned($plan, $user);
            $this->fill($plan, $user);
        });
    }

    /** Put a chosen recipe in a slot (swap, or fill an empty slot). Keeps the lock; clears the tags and eaten mark. */
    public function setMeal(User $user, int $day, string $slot, Recipe $recipe, int $servings = 1): MealPlanItem
    {
        $plan = $this->planFor($user);
        $this->assertDaySlot($day, $slot);

        $profile = CoachProfile::where('user_id', $user->id)->first();
        if ($recipe->slot !== $slot || ! $this->catalog->visibleTo($recipe, $profile)) {
            throw new InvalidArgumentException('That meal is not available for this slot.');
        }

        $existing = $plan->items()->where('day_index', $day)->where('slot', $slot)->first();
        if ($existing?->eaten_at) {
            $this->logger->untickPlanItem($existing);   // the meal changed, so the old tick no longer counts
        }

        return $plan->items()->updateOrCreate(
            ['day_index' => $day, 'slot' => $slot],
            [
                'recipe_id' => $recipe->id,
                'servings' => $this->clampServings($servings),
                'locked' => $existing?->locked ?? false,
                'is_new' => false,
                'protein_boost' => false,
                'eaten_at' => null,
            ],
        );
    }

    /** Swap this slot's meal between two days. If the other day has no meal, the meal moves there. */
    public function trade(User $user, int $day, string $slot, int $otherDay): void
    {
        $plan = $this->planFor($user);
        $this->assertDaySlot($day, $slot);
        $this->assertDaySlot($otherDay, $slot);
        if ($day === $otherDay) {
            return;
        }

        DB::transaction(function () use ($plan, $day, $slot, $otherDay) {
            $a = $plan->items()->where('day_index', $day)->where('slot', $slot)->first();
            $b = $plan->items()->where('day_index', $otherDay)->where('slot', $slot)->first();
            if (! $a && ! $b) {
                return;
            }

            $carry = fn (?MealPlanItem $i) => $i ? $i->only(['recipe_id', 'servings', 'locked', 'is_new', 'protein_boost']) + ['eaten_at' => null] : null;
            $fromA = $carry($a);
            $fromB = $carry($b);

            foreach ([$a, $b] as $moved) {
                if ($moved?->eaten_at) {
                    $this->logger->untickPlanItem($moved);
                }
            }

            $a?->delete();
            $b?->delete();
            if ($fromB) {
                $plan->items()->create(['day_index' => $day, 'slot' => $slot] + $fromB);
            }
            if ($fromA) {
                $plan->items()->create(['day_index' => $otherDay, 'slot' => $slot] + $fromA);
            }
        });
    }

    public function toggleLock(User $user, int $day, string $slot): ?bool
    {
        $item = $this->item($user, $day, $slot);

        if (! $item) {
            return null;
        }
        $item->update(['locked' => ! $item->locked]);

        return $item->locked;
    }

    public function remove(User $user, int $day, string $slot): bool
    {
        return (bool) $this->item($user, $day, $slot)?->delete();
    }

    /** Tick or untick a meal as eaten. Only today's meals can be ticked. @return bool|null new state, null when not allowed */
    public function toggleEaten(User $user, int $day, string $slot): ?bool
    {
        $plan = $this->planFor($user);
        $item = $plan->items()->where('day_index', $day)->where('slot', $slot)->first();

        if (! $item || $day !== $this->dayIndexToday($plan)) {
            return null;
        }

        if ($item->eaten_at) {
            $this->logger->untickPlanItem($item);
            $item->update(['eaten_at' => null]);

            return false;
        }

        $this->logger->tickPlanItem($user, $item->load('recipe'));
        $item->update(['eaten_at' => now()]);

        return true;
    }

    // --- The engine ------------------------------------------------------------------------------------------------

    /** Fill every empty slot of the plan, day by day. Existing items (locked or kept) are never changed here. */
    private function fill(MealPlan $plan, User $user, ?int $seed = null): void
    {
        $profile = CoachProfile::where('user_id', $user->id)->first();
        $favourites = $this->catalog->favouriteIds($user, $profile);
        $hasFavourites = $favourites !== [];
        $suitable = $this->catalog->suitable($profile);

        $pools = [];
        foreach (Recipe::SLOTS as $slot) {
            $pools[$slot] = $this->pool($plan->template, $slot, $suitable->where('slot', $slot)->values());
        }

        $items = $plan->items()->get();
        $used = $items->groupBy('recipe_id')->map->count()->all();   // how often each recipe is already in the plan
        $proteinTarget = (float) config('coach.targets.protein_g');
        [$minNew, $maxNew] = config('coach.meal_plan.new_meals_per_day', [1, 2]);

        for ($day = 0; $day < $this->days(); $day++) {
            $daySeed = $seed === null ? null : $seed + $day * 101;
            $existing = $items->where('day_index', $day)->keyBy('slot');
            $yesterday = $day > 0 ? $items->where('day_index', $day - 1)->keyBy('slot') : collect();

            $emptyNewSlots = collect(config('coach.meal_plan.new_meal_slots', []))
                ->filter(fn ($s) => ! $existing->has($s) && $pools[$s]->isNotEmpty())->values();
            $newCount = $hasFavourites ? min($emptyNewSlots->count(), $this->between($minNew, $maxNew, $daySeed)) : 0;
            $newSlots = $this->shuffled($emptyNewSlots, $daySeed)->take($newCount)->all();

            $created = [];
            foreach (Recipe::SLOTS as $i => $slot) {
                if ($existing->has($slot)) {
                    continue;
                }

                $pool = $pools[$slot];
                $avoidId = $yesterday->get($slot)?->recipe_id;
                $candidates = $pool->reject(fn (Recipe $r) => $r->id === $avoidId);
                $candidates = $candidates->isNotEmpty() ? $candidates : $pool;   // only one suitable meal: repeating is better than an empty slot
                if ($candidates->isEmpty()) {
                    continue;
                }

                $wantNew = in_array($slot, $newSlots, true);
                $favs = $candidates->filter(fn (Recipe $r) => in_array($r->id, $favourites, true));
                $others = $candidates->reject(fn (Recipe $r) => in_array($r->id, $favourites, true));
                $pick = ($wantNew ? ($others->isNotEmpty() ? $others : $favs) : ($favs->isNotEmpty() ? $favs : $others));

                $recipe = $this->shuffled($pick, $daySeed === null ? null : $daySeed + $i)
                    ->sortBy(fn (Recipe $r) => $used[$r->id] ?? 0)->first();   // least used so far; sortBy is stable
                $isNew = $hasFavourites && ! in_array($recipe->id, $favourites, true);

                $item = $plan->items()->create([
                    'day_index' => $day, 'slot' => $slot, 'recipe_id' => $recipe->id, 'servings' => 1,
                    'locked' => false, 'is_new' => $isNew && in_array($slot, config('coach.meal_plan.new_meal_slots', []), true),
                    'protein_boost' => false,
                ]);
                $item->setRelation('recipe', $recipe);
                $existing->put($slot, $item);
                $created[] = $slot;
                $items->push($item);
                $used[$recipe->id] = ($used[$recipe->id] ?? 0) + 1;
            }

            // Only a snack this run added is swapped: a snack the member chose or locked is never replaced.
            if (in_array('snack', $created, true)) {
                $this->boostProtein($existing, $pools['snack'], $proteinTarget, $yesterday->get('snack')?->recipe_id);
            }
        }
    }

    /**
     * Below the protein target for the day: swap the snack for the highest-protein snack. Yesterday's snack is skipped,
     * so the no-repeat rule still holds (the next best snack is used instead).
     */
    private function boostProtein(Collection $dayItems, Collection $snackPool, float $target, ?int $yesterdaySnackId): void
    {
        $snack = $dayItems->get('snack');
        if (! $snack || $snack->locked || $snackPool->isEmpty()) {
            return;
        }

        $protein = 0.0;
        foreach ($dayItems as $item) {
            $recipe = $item->relationLoaded('recipe') ? $item->recipe : $item->load('recipe')->recipe;
            $protein += $recipe->nutrition($item->servings)['protein_g'];
        }
        if ($protein >= $target) {
            return;
        }

        $best = $snackPool->reject(fn (Recipe $r) => $r->id === $yesterdaySnackId)->sortByDesc('protein_g')->first();
        if (! $best) {
            return;
        }
        $current = $snack->relationLoaded('recipe') ? $snack->recipe : $snack->load('recipe')->recipe;
        if ($best->id === $current->id || $best->protein_g <= $current->protein_g) {
            // The snack already chosen is as good as any: it is the boost snack, so tag it.
            $snack->update(['protein_boost' => true, 'is_new' => false]);

            return;
        }

        $snack->update(['recipe_id' => $best->id, 'servings' => 1, 'is_new' => false, 'protein_boost' => true]);
        $snack->setRelation('recipe', $best);
    }

    /** Remove every item whose recipe the member may no longer have (banned, switched off or un-approved). */
    private function purgeBanned(MealPlan $plan, User $user): void
    {
        $profile = CoachProfile::where('user_id', $user->id)->first();

        foreach ($plan->items()->with('recipe')->get() as $item) {
            if (! $item->recipe || ! $this->catalog->visibleTo($item->recipe, $profile)) {
                $item->delete();
            }
        }
    }

    /** Apply the template limits for a slot; if nothing is left, use every suitable meal. */
    private function pool(string $template, string $slot, Collection $suitable): Collection
    {
        $limits = config("coach.meal_plan.templates.{$template}.limits.{$slot}", []);
        if (! $limits) {
            return $suitable->values();
        }

        $limited = $suitable->filter(function (Recipe $r) use ($limits) {
            return (! isset($limits['max_kcal']) || $r->kcal <= $limits['max_kcal'])
                && (! isset($limits['min_protein']) || $r->protein_g >= $limits['min_protein'])
                && (! isset($limits['max_carbs']) || $r->carbs_g <= $limits['max_carbs']);
        })->values();

        return $limited->isNotEmpty() ? $limited : $suitable->values();
    }

    // --- Small helpers -----------------------------------------------------------------------------------------------

    private function item(User $user, int $day, string $slot): ?MealPlanItem
    {
        return $this->planFor($user)->items()->where('day_index', $day)->where('slot', $slot)->first();
    }

    private function days(): int
    {
        return (int) config('coach.meal_plan.days', 7);
    }

    /** Shuffle, repeatably when a seed is given (tests), randomly otherwise. Laravel's own shuffle takes no seed. */
    private function shuffled(Collection $items, ?int $seed): Collection
    {
        if ($seed === null) {
            return $items->shuffle();
        }

        return new Collection((new \Random\Randomizer(new \Random\Engine\Mt19937($seed)))->shuffleArray($items->values()->all()));
    }

    private function between(int $min, int $max, ?int $seed): int
    {
        if ($seed === null) {
            return random_int($min, $max);
        }

        return $min + (abs($seed) % ($max - $min + 1));
    }

    private function clampServings(int $n): int
    {
        return max((int) config('coach.recipes.servings_min', 1), min((int) config('coach.recipes.servings_max', 4), $n));
    }

    private function assertSlot(string $slot): void
    {
        if (! in_array($slot, Recipe::SLOTS, true)) {
            throw new InvalidArgumentException('Unknown meal slot.');
        }
    }

    private function assertDaySlot(int $day, string $slot): void
    {
        $this->assertSlot($slot);
        if ($day < 0 || $day >= $this->days()) {
            throw new InvalidArgumentException('That day is not in your plan.');
        }
    }

    private function assertTemplate(string $template): void
    {
        if (! array_key_exists($template, config('coach.meal_plan.templates', []))) {
            throw new InvalidArgumentException('Unknown plan template.');
        }
    }
}
