<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\Personalise;
use App\Livewire\Coach\Plan;
use App\Livewire\Coach\RecipeShow;
use App\Livewire\Coach\Recipes;
use App\Models\CoachingAssignment;
use App\Models\CoachProfile;
use App\Models\FavouriteRecipe;
use App\Models\MealPlan;
use App\Models\PatientProfile;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Coach\MealPlanner;
use App\Services\Coach\ProfileSettings;
use App\Services\Coach\RecipeCatalog;
use Carbon\CarbonImmutable;
use Database\Seeders\CoachDemoRecipeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach App v2: recipes, favourites and the 7-day meal plan (build guide 5.11 and 5.12).
 */
class CoachMealPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 09:00', 'Africa/Johannesburg'));
        $this->seed(CoachDemoRecipeSeeder::class);
    }

    private function member(array $profile = []): User
    {
        $user = User::factory()->create(['role' => UserRole::Patient, 'gender' => 'female']);
        PatientProfile::create(['user_id' => $user->id, 'onboarding_complete' => true]);
        CoachingAssignment::create([
            'patient_id' => $user->id,
            'coach_id' => User::factory()->create(['role' => UserRole::HealthCoach])->id,
            'started_at' => now(),
        ]);
        CoachProfile::create(array_merge(['user_id' => $user->id, 'onboarded_at' => now()], $profile));

        return $user;
    }

    private function planner(): MealPlanner
    {
        return app(MealPlanner::class);
    }

    private function recipe(string $name): Recipe
    {
        return Recipe::where('name', $name)->firstOrFail();
    }

    /** @return \Illuminate\Support\Collection<int,\App\Models\MealPlanItem> */
    private function items(User $user)
    {
        return MealPlan::where('user_id', $user->id)->firstOrFail()->items()->with('recipe')->get();
    }

    // --- Who may see which recipe ----------------------------------------------------------------------------------

    public function test_the_demo_set_is_20_recipes_five_per_slot(): void
    {
        $this->assertSame(20, Recipe::count());
        foreach (['breakfast', 'lunch', 'dinner', 'snack'] as $slot) {
            $this->assertSame(5, Recipe::where('slot', $slot)->count(), $slot);
        }

        $this->seed(CoachDemoRecipeSeeder::class);
        $this->assertSame(20, Recipe::count(), 'Seeding again must not duplicate.');
    }

    public function test_unapproved_and_switched_off_recipes_are_never_offered(): void
    {
        $member = $this->member();
        $catalog = app(RecipeCatalog::class);
        $this->assertCount(20, $catalog->suitable(null));

        $this->recipe('Veggie omelette')->update(['approved_at' => null]);
        $this->recipe('Tuna avo bowl')->update(['is_active' => false]);

        $names = $catalog->suitable(CoachProfile::where('user_id', $member->id)->first())->pluck('name');
        $this->assertCount(18, $names);
        $this->assertNotContains('Veggie omelette', $names);
        $this->assertNotContains('Tuna avo bowl', $names);
    }

    public function test_diet_allergies_and_avoids_each_remove_meals(): void
    {
        $catalog = app(RecipeCatalog::class);
        $profile = fn (array $a) => new CoachProfile($a);

        $vegan = $catalog->suitable($profile(['diet' => 'vegan']));
        $this->assertGreaterThan(0, $vegan->count());
        $this->assertTrue($vegan->every(fn ($r) => in_array('vegan', $r->diet_tags, true)));

        $noNuts = $catalog->suitable($profile(['allergies' => ['nuts']]));
        $this->assertTrue($noNuts->every(fn ($r) => ! in_array('nuts', $r->allergens, true)));
        $this->assertNotContains('Apple with almond butter', $noNuts->pluck('name'));

        $noRedMeat = $catalog->suitable($profile(['avoids' => ['red_meat']]));
        $this->assertNotContains('Biltong and cucumber', $noRedMeat->pluck('name'));

        // Avoiding dairy also removes a recipe that lists dairy as an allergen.
        $noDairy = $catalog->suitable($profile(['avoids' => ['dairy']]));
        $this->assertNotContains('Whey protein shake', $noDairy->pluck('name'));

        $lowCarb = $catalog->suitable($profile(['diet' => 'low_carb']));
        $this->assertTrue($lowCarb->every(fn ($r) => $r->carbs_g <= 28));
    }

    // --- Building the plan ---------------------------------------------------------------------------------------

    public function test_a_plan_has_seven_days_of_four_slots_starting_today(): void
    {
        $member = $this->member();

        $plan = $this->planner()->planFor($member);

        $this->assertSame('2026-11-25', $plan->starts_on->toDateString());
        $this->assertSame('treatment_friendly', $plan->template);
        $this->assertSame(28, $plan->items()->count());
        $this->assertSame(7, $plan->items()->distinct('day_index')->count('day_index'));

        // Asking again returns the same plan and does not rebuild it.
        $ids = $plan->items()->pluck('id')->all();
        $this->assertSame($ids, $this->planner()->planFor($member)->items()->pluck('id')->all());
    }

    public function test_no_meal_repeats_yesterdays_meal_in_the_same_slot(): void
    {
        $member = $this->member();
        $this->planner()->setTemplate($member, 'balanced', 11);

        $items = $this->items($member)->groupBy('slot');
        foreach ($items as $slot => $rows) {
            $byDay = $rows->keyBy('day_index');
            for ($d = 1; $d < 7; $d++) {
                $this->assertNotSame($byDay[$d - 1]->recipe_id, $byDay[$d]->recipe_id, "{$slot} repeats on day {$d}");
            }
        }
    }

    public function test_the_templates_apply_their_limits(): void
    {
        $member = $this->member();

        $this->planner()->setTemplate($member, 'low_carb', 5);
        $this->assertTrue($this->items($member)->every(fn ($i) => $i->recipe->carbs_g <= 28), 'Low Carb: 28 g or less');

        $this->planner()->setTemplate($member, 'high_protein', 5);
        $mains = $this->items($member)->whereIn('slot', ['lunch', 'dinner']);
        $this->assertTrue($mains->every(fn ($i) => $i->recipe->protein_g >= 30), 'High Protein: mains 30 g or more');

        $this->planner()->setTemplate($member, 'treatment_friendly', 5);
        $this->assertTrue($this->items($member)->where('slot', 'dinner')->every(fn ($i) => $i->recipe->kcal <= 450));
    }

    public function test_a_template_limit_is_dropped_when_it_would_leave_a_slot_empty(): void
    {
        $member = $this->member();
        // No snack meets the 28 g carb limit.
        Recipe::where('slot', 'snack')->update(['carbs_g' => 40]);

        $this->planner()->setTemplate($member, 'low_carb', 3);

        $snacks = $this->items($member)->where('slot', 'snack');
        $this->assertCount(7, $snacks, 'The snack slot is still filled even though no snack meets the carb limit.');
    }

    public function test_diet_allergies_and_avoids_are_never_broken_by_the_plan(): void
    {
        $member = $this->member(['diet' => 'vegetarian', 'allergies' => ['nuts', 'eggs'], 'avoids' => ['dairy']]);

        foreach ([null, 'balanced', 'high_protein', 'low_carb'] as $template) {
            $this->planner()->regenerate($member, $template, 21);
            foreach ($this->items($member) as $i) {
                $r = $i->recipe;
                $this->assertContains('vegetarian', $r->diet_tags, $r->name);
                $this->assertEmpty(array_intersect($r->allergens, ['nuts', 'eggs', 'dairy']), $r->name);
                $this->assertNotContains('dairy', $r->avoid_tags, $r->name);
            }
        }
    }

    public function test_favourites_come_first_and_other_meals_are_tagged_new_for_you(): void
    {
        $member = $this->member();
        $favs = ['Greek yoghurt berry bowl', 'Veggie omelette', 'Chicken and quinoa salad', 'Tuna avo bowl', 'Grilled hake and veg', 'Lemon chicken with roasted vegetables'];
        $ids = [];
        foreach ($favs as $name) {
            $ids[] = $r = $this->recipe($name);
            FavouriteRecipe::create(['user_id' => $member->id, 'recipe_id' => $r->id]);
        }
        $ids = collect($ids)->pluck('id')->all();

        $this->planner()->setTemplate($member, 'balanced', 8);

        $items = $this->items($member);
        foreach ($items->whereIn('slot', ['breakfast', 'lunch', 'dinner'])->groupBy('day_index') as $day => $rows) {
            $new = $rows->where('is_new', true);
            $this->assertGreaterThanOrEqual(1, $new->count(), "day {$day} has no new meal");
            $this->assertLessThanOrEqual(2, $new->count(), "day {$day} has too many new meals");
            foreach ($rows->where('is_new', false) as $row) {
                $this->assertContains($row->recipe_id, $ids, 'A meal that is not "new for you" must be a favourite.');
            }
            foreach ($new as $row) {
                $this->assertNotContains($row->recipe_id, $ids);
            }
        }
    }

    public function test_without_favourites_nothing_is_tagged_new(): void
    {
        $member = $this->member();
        $this->planner()->planFor($member);

        $this->assertSame(0, $this->items($member)->where('is_new', true)->count());
    }

    public function test_a_low_protein_day_gets_the_highest_protein_snack(): void
    {
        $member = $this->member();
        config(['coach.targets.protein_g' => 500]);

        $this->planner()->setTemplate($member, 'balanced', 4);

        $snacks = $this->items($member)->where('slot', 'snack')->sortBy('day_index')->values();
        $this->assertCount(7, $snacks);
        $this->assertTrue($snacks->every(fn ($s) => $s->protein_boost), 'Every day is under the target, so every snack is boosted.');
        // The shake (24 g) is best, biltong (18 g) next. Yesterday's snack is skipped, so they alternate.
        $this->assertSame(['Biltong and cucumber', 'Whey protein shake'], $snacks->pluck('recipe.name')->unique()->sort()->values()->all());
    }

    public function test_no_boost_when_the_day_already_meets_the_protein_target(): void
    {
        $member = $this->member();
        config(['coach.targets.protein_g' => 5]);

        $this->planner()->setTemplate($member, 'balanced', 4);

        $this->assertSame(0, $this->items($member)->where('protein_boost', true)->count());
    }

    public function test_a_locked_meal_survives_regenerate_and_the_rest_is_rebuilt(): void
    {
        $member = $this->member();
        $planner = $this->planner();
        $planner->planFor($member);

        $planner->setMeal($member, 2, 'dinner', $this->recipe('Chickpea curry'));
        $this->assertTrue($planner->toggleLock($member, 2, 'dinner'));
        $planner->setMeal($member, 3, 'lunch', $this->recipe('Lentil and roasted veg soup'));
        $before = $this->items($member)->pluck('id', 'id')->all();

        $planner->regenerate($member, null, 99);

        $items = $this->items($member);
        $locked = $items->where('day_index', 2)->where('slot', 'dinner')->first();
        $this->assertSame('Chickpea curry', $locked->recipe->name);
        $this->assertTrue($locked->locked);
        $this->assertSame(28, $items->count());
        $this->assertNotSame(
            collect($before)->keys()->sort()->values()->all(),
            $items->pluck('id')->sort()->values()->all(),
            'Unlocked meals were rebuilt (new rows).',
        );
        $this->assertSame(1, $items->where('locked', true)->count());
    }

    public function test_regenerate_with_a_new_template_keeps_locked_meals(): void
    {
        $member = $this->member();
        $planner = $this->planner();
        $planner->planFor($member);
        $planner->setMeal($member, 0, 'breakfast', $this->recipe('Overnight oats with chia'));
        $planner->toggleLock($member, 0, 'breakfast');

        $planner->setTemplate($member, 'low_carb', 1);   // oats have 42 g carbs: breaks the template limit but the member locked it

        $this->assertSame('low_carb', MealPlan::where('user_id', $member->id)->value('template'));
        $this->assertSame('Overnight oats with chia', $this->items($member)->where('day_index', 0)->where('slot', 'breakfast')->first()->recipe->name);
    }

    // --- Changing diet, allergies and avoids ---------------------------------------------------------------------

    public function test_changing_an_allergy_removes_banned_meals_even_locked_ones_and_refills(): void
    {
        $member = $this->member();
        $planner = $this->planner();
        $planner->planFor($member);
        $planner->setMeal($member, 1, 'snack', $this->recipe('Apple with almond butter'));
        $planner->toggleLock($member, 1, 'snack');

        app(ProfileSettings::class)->toggleInList($member, 'allergies', 'nuts');

        $items = $this->items($member);
        $this->assertTrue($items->every(fn ($i) => ! in_array('nuts', $i->recipe->allergens, true)));
        $this->assertNotSame('Apple with almond butter', $items->where('day_index', 1)->where('slot', 'snack')->first()?->recipe->name);
        $this->assertSame(28, $items->count(), 'The emptied slots were refilled.');
    }

    public function test_changing_the_diet_removes_banned_meals_and_leaves_the_rest_alone(): void
    {
        $member = $this->member();
        $planner = $this->planner();
        $planner->planFor($member);
        $untouched = $this->items($member)->filter(fn ($i) => in_array('vegetarian', $i->recipe->diet_tags, true))->pluck('id')->all();
        $this->assertNotEmpty($untouched);

        app(ProfileSettings::class)->setDiet($member, 'vegetarian');

        $items = $this->items($member);
        $this->assertTrue($items->every(fn ($i) => in_array('vegetarian', $i->recipe->diet_tags, true)));
        foreach ($untouched as $id) {
            $this->assertTrue($items->contains('id', $id), 'A meal that is still fine is not touched.');
        }
    }

    public function test_the_personalise_screen_saves_once_and_rebuilds(): void
    {
        $member = $this->member();
        $this->planner()->planFor($member);

        Livewire::actingAs($member)->test(Personalise::class)
            ->call('setDiet', 'vegan')
            ->call('toggle', 'allergies', 'nuts')
            ->call('toggle', 'avoids', 'spicy')
            ->call('save')
            ->assertRedirect(route('coach.plan'));

        $profile = CoachProfile::where('user_id', $member->id)->first();
        $this->assertSame('vegan', $profile->diet);
        $this->assertSame(['nuts'], $profile->allergies);
        $this->assertSame(['spicy'], $profile->avoids);
        foreach ($this->items($member) as $i) {
            $this->assertContains('vegan', $i->recipe->diet_tags);
            $this->assertNotContains('nuts', $i->recipe->allergens);
            $this->assertNotContains('spicy', $i->recipe->avoid_tags);
        }
    }

    public function test_the_personalise_screen_ignores_unknown_choices(): void
    {
        $member = $this->member();

        Livewire::actingAs($member)->test(Personalise::class)
            ->call('setDiet', 'carnivore')
            ->call('toggle', 'allergies', 'pollen')
            ->call('toggle', 'secrets', 'x')
            ->assertSet('diet', 'everything')
            ->assertSet('allergies', []);
    }

    // --- Editing the plan ----------------------------------------------------------------------------------------

    public function test_swap_rules(): void
    {
        $member = $this->member(['allergies' => ['nuts']]);
        $planner = $this->planner();
        $planner->planFor($member);

        $item = $planner->setMeal($member, 0, 'lunch', $this->recipe('Tuna avo bowl'), 2);
        $this->assertSame(2, $item->servings);

        foreach ([
            fn () => $planner->setMeal($member, 0, 'lunch', $this->recipe('Veggie omelette')),            // wrong slot
            fn () => $planner->setMeal($member, 0, 'snack', $this->recipe('Apple with almond butter')),  // nut allergy
            fn () => $planner->setMeal($member, 9, 'lunch', $this->recipe('Tuna avo bowl')),             // not a plan day
            fn () => $planner->setMeal($member, 0, 'brunch', $this->recipe('Tuna avo bowl')),            // unknown slot
        ] as $bad) {
            try {
                $bad();
                $this->fail('Expected the swap to be refused.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(28, MealPlan::where('user_id', $member->id)->first()->items()->count());
    }

    public function test_servings_are_kept_between_1_and_4(): void
    {
        $member = $this->member();
        $this->planner()->planFor($member);

        $this->assertSame(4, $this->planner()->setMeal($member, 0, 'lunch', $this->recipe('Tuna avo bowl'), 9)->servings);
        $this->assertSame(1, $this->planner()->setMeal($member, 0, 'lunch', $this->recipe('Tuna avo bowl'), 0)->servings);
    }

    public function test_trading_swaps_the_slot_between_two_days_and_moves_into_an_empty_one(): void
    {
        $member = $this->member();
        $planner = $this->planner();
        $planner->planFor($member);
        $a = $planner->setMeal($member, 0, 'dinner', $this->recipe('Chickpea curry'));
        $planner->setMeal($member, 4, 'dinner', $this->recipe('Tofu and broccoli stir-fry'));
        $planner->toggleLock($member, 0, 'dinner');

        $planner->trade($member, 0, 'dinner', 4);

        $items = $this->items($member);
        $d0 = $items->where('day_index', 0)->where('slot', 'dinner')->first();
        $d4 = $items->where('day_index', 4)->where('slot', 'dinner')->first();
        $this->assertSame('Tofu and broccoli stir-fry', $d0->recipe->name);
        $this->assertSame('Chickpea curry', $d4->recipe->name);
        $this->assertTrue($d4->locked, 'The lock travels with the meal.');
        $this->assertFalse($d0->locked);

        $planner->remove($member, 6, 'dinner');
        $planner->trade($member, 4, 'dinner', 6);
        $this->assertNull($this->items($member)->where('day_index', 4)->where('slot', 'dinner')->first());
        $this->assertSame('Chickpea curry', $this->items($member)->where('day_index', 6)->where('slot', 'dinner')->first()->recipe->name);
    }

    public function test_remove_and_the_eaten_tick(): void
    {
        $member = $this->member();
        $planner = $this->planner();
        $plan = $planner->planFor($member);

        $this->assertTrue($planner->remove($member, 0, 'snack'));
        $this->assertFalse($planner->remove($member, 0, 'snack'));
        $this->assertNull($planner->toggleLock($member, 0, 'snack'));

        $this->assertTrue($planner->toggleEaten($member, 0, 'lunch'), 'Day 0 is today.');
        $this->assertFalse($planner->toggleEaten($member, 0, 'lunch'));
        $this->assertNull($planner->toggleEaten($member, 3, 'lunch'), 'Only today can be ticked.');
        $this->assertNull($planner->toggleEaten($member, 0, 'snack'), 'A removed meal cannot be ticked.');
    }

    public function test_the_day_totals_and_tag(): void
    {
        $member = $this->member();
        $planner = $this->planner();
        $plan = $planner->planFor($member);
        $plan->items()->where('day_index', 0)->delete();

        $this->assertNull($planner->dayTotals($plan, 0)['tag'], 'An empty day has no tag.');

        $planner->setMeal($member, 0, 'breakfast', $this->recipe('Greek yoghurt berry bowl'));
        $t = $planner->dayTotals($plan, 0);
        $this->assertSame(280, $t['kcal']);
        $this->assertSame(24.0, $t['protein_g']);
        $this->assertSame('Close, tweak a meal', $t['tag']);

        config(['coach.targets.kcal' => 280, 'coach.targets.protein_g' => 24]);
        $this->assertSame('On target', $planner->dayTotals($plan, 0)['tag']);
    }

    public function test_an_old_plan_rolls_over_to_a_fresh_week_and_plans_are_private(): void
    {
        $member = $this->member();
        $other = $this->member();
        $planner = $this->planner();
        $plan = $planner->planFor($member);
        $planner->toggleLock($member, 0, 'lunch');
        $otherIds = $planner->planFor($other)->items()->pluck('id')->all();
        $plan->update(['starts_on' => '2026-11-10']);   // 15 days ago

        $fresh = $planner->planFor($member);

        $this->assertSame('2026-11-25', $fresh->starts_on->toDateString());
        $this->assertSame(28, $fresh->items()->count());
        $this->assertSame(0, $fresh->items()->where('locked', true)->count(), 'Last week\'s locks are cleared.');
        $this->assertSame($otherIds, $planner->planFor($other)->items()->pluck('id')->all());
        $this->assertSame(2, MealPlan::count());
    }

    // --- Screens ---------------------------------------------------------------------------------------------------

    public function test_the_screens_need_the_coach_addon(): void
    {
        $this->get(route('coach.plan'))->assertRedirect();

        $plain = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $plain->id, 'onboarding_complete' => true]);
        foreach (['coach.plan', 'coach.plan.personalise', 'coach.recipes'] as $name) {
            $this->actingAs($plain)->get(route($name))->assertRedirect('/weight-loss');
        }
        $this->actingAs($plain)->get(route('coach.recipe', $this->recipe('Tuna avo bowl')))->assertRedirect('/weight-loss');
    }

    public function test_the_plan_screen_shows_the_week_and_the_plans_tab_is_on(): void
    {
        $member = $this->member();

        $this->actingAs($member)->get(route('coach.plan'))
            ->assertOk()
            ->assertSee('Treatment Friendly')
            ->assertSee('data-template-chip', false)
            ->assertSee('data-day="6"', false)
            ->assertSee('data-slot="breakfast"', false)
            ->assertSee('data-slot="snack"', false)
            ->assertSee('Regenerate')
            ->assertSee('Nutrition values are estimates');

        $this->actingAs($member)->get(route('coach.plan', ['day' => 3]))->assertOk()->assertSee('aria-selected="true"', false);
    }

    public function test_the_plan_screen_menu_actions(): void
    {
        $member = $this->member();
        $c = Livewire::actingAs($member)->test(Plan::class);

        $c->call('openTemplate')->assertSee('data-template="high_protein"', false)
            ->call('chooseTemplate', 'high_protein')->assertSet('sheet', null)->assertSee('High Protein');
        $this->assertSame('high_protein', MealPlan::where('user_id', $member->id)->value('template'));

        $c->call('chooseTemplate', 'nonsense')->assertSee('Unknown plan template');

        $c->call('openMeal', 'dinner')->assertSeeInOrder(['Dinner', 'Wed 25 Nov'])->assertSee('data-act="lock"', false)
            ->call('toggleLock')->assertSet('sheet', null);
        $this->assertTrue($this->items($member)->where('day_index', 0)->where('slot', 'dinner')->first()->locked);

        $c->call('openMeal', 'dinner')->call('openSwap')->assertSee('data-choose', false);
        $pick = $this->recipe('Turkey meatballs with courgetti');
        $c->call('chooseMeal', $pick->id);
        $this->assertSame($pick->id, $this->items($member)->where('day_index', 0)->where('slot', 'dinner')->first()->recipe_id);

        $c->call('openMeal', 'dinner')->call('openTrade')->assertSee('data-trade="1"', false)->assertDontSee('data-trade="0"', false)
            ->call('tradeWith', 1);

        $c->call('openMeal', 'snack')->call('removeMeal');
        $this->assertNull($this->items($member)->where('day_index', 0)->where('slot', 'snack')->first());
        $c->assertSee('data-add="snack"', false)->call('openMeal', 'snack')->assertSee('data-choose', false);

        $c->call('selectDay', 3)->assertSet('day', 3)->call('toggleEaten', 'lunch')->assertSee('only tick off today');
    }

    public function test_hearting_from_the_plan_menu_changes_favourites(): void
    {
        $member = $this->member();
        $c = Livewire::actingAs($member)->test(Plan::class)->call('openMeal', 'lunch');
        $recipeId = $this->items($member)->where('day_index', 0)->where('slot', 'lunch')->first()->recipe_id;

        $c->call('toggleFavourite');
        $this->assertTrue(FavouriteRecipe::where('user_id', $member->id)->where('recipe_id', $recipeId)->exists());

        $c->call('openMeal', 'lunch')->call('toggleFavourite');
        $this->assertFalse(FavouriteRecipe::where('user_id', $member->id)->where('recipe_id', $recipeId)->exists());
    }

    public function test_the_recipes_screen_lists_by_slot_searches_and_hearts(): void
    {
        $member = $this->member(['allergies' => ['nuts']]);
        $tuna = $this->recipe('Tuna avo bowl');

        $c = Livewire::actingAs($member)->test(Recipes::class)
            ->assertSee('Greek yoghurt berry bowl')
            ->assertDontSee('Apple with almond butter')       // nut allergy: hidden
            ->assertSee('Heart the meals you love')
            ->assertDontSee('data-build-plan', false);

        $c->set('search', 'tuna')->assertSee('Tuna avo bowl')->assertDontSee('Greek yoghurt berry bowl');
        $c->set('search', '')->call('toggleFavourite', $tuna->id)->assertSee('Build my plan around my 1 favourite');
        $this->assertTrue(FavouriteRecipe::where('user_id', $member->id)->where('recipe_id', $tuna->id)->exists());

        $c->call('setTab', 'favourites')->assertSee('Tuna avo bowl')->assertDontSee('Greek yoghurt berry bowl');
        $c->call('toggleFavourite', $tuna->id)->assertSee('No favourites yet');

        // A member cannot heart a recipe they may not see.
        $nuts = $this->recipe('Apple with almond butter');
        $c->call('toggleFavourite', $nuts->id);
        $this->assertFalse(FavouriteRecipe::where('user_id', $member->id)->where('recipe_id', $nuts->id)->exists());
    }

    public function test_build_my_plan_around_favourites_uses_them(): void
    {
        $member = $this->member();
        $fav = $this->recipe('Tuna avo bowl');
        FavouriteRecipe::create(['user_id' => $member->id, 'recipe_id' => $fav->id]);
        $this->planner()->setTemplate($member, 'balanced', 1);

        Livewire::actingAs($member)->test(Recipes::class)->call('buildPlan')->assertRedirect(route('coach.plan'));

        $this->assertGreaterThanOrEqual(3, $this->items($member)->where('recipe_id', $fav->id)->count(), 'Hearted recipe shows up in the next plan.');
    }

    public function test_the_recipe_screen_scales_servings_and_adds_to_the_plan(): void
    {
        $member = $this->member();
        $r = $this->recipe('Chicken and quinoa salad');

        $c = Livewire::actingAs($member)->test(RecipeShow::class, ['recipe' => $r])
            ->assertSee('120 g')
            ->assertSee('Sample recipe')
            ->call('more')->call('more')
            ->assertSet('servings', 3)->assertSee('360 g')
            ->call('more')->call('more')->assertSet('servings', 4)
            ->call('less')->call('less')->call('less')->call('less')->assertSet('servings', 1)
            ->call('setTab', 'nutrition')->assertSee('420 kcal')->call('more')->assertSee('840 kcal');

        $c->set('day', 2)->call('addToPlan')->assertRedirect(route('coach.plan', ['day' => 2]));
        $item = $this->items($member)->where('day_index', 2)->where('slot', 'lunch')->first();
        $this->assertSame($r->id, $item->recipe_id);
        $this->assertSame(2, $item->servings);
    }

    public function test_a_recipe_the_member_may_not_see_is_not_found(): void
    {
        $member = $this->member(['allergies' => ['nuts']]);

        $this->actingAs($member)->get(route('coach.recipe', $this->recipe('Apple with almond butter')))->assertNotFound();

        $this->recipe('Tuna avo bowl')->update(['approved_at' => null]);
        $this->actingAs($member)->get(route('coach.recipe', $this->recipe('Tuna avo bowl')))->assertNotFound();

        $this->actingAs($member)->get(route('coach.recipe', $this->recipe('Veggie omelette')))->assertOk()->assertSee('Veggie omelette');
    }

    public function test_the_screens_render_at_all_for_every_template_and_tab(): void
    {
        $member = $this->member();

        foreach (array_keys(config('coach.meal_plan.templates')) as $template) {
            $this->planner()->setTemplate($member, $template, 2);
            for ($day = 0; $day < 7; $day++) {
                $this->actingAs($member)->get(route('coach.plan', ['day' => $day]))->assertOk();
            }
        }
        $this->actingAs($member)->get(route('coach.recipes'))->assertOk()->assertSee('data-row="Breakfast"', false);
        $this->actingAs($member)->get(route('coach.plan.personalise'))->assertOk()->assertSee('data-diet="vegan"', false);
    }
}
