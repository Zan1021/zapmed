<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\CoachRecipes;
use App\Livewire\Coach\LogMeal;
use App\Livewire\Coach\Nutrition;
use App\Livewire\Coach\Plan;
use App\Livewire\Coach\RecipeShow;
use App\Models\CoachingAssignment;
use App\Models\CoachProfile;
use App\Models\MealEntry;
use App\Models\PatientProfile;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Coach\MealLogger;
use App\Services\Coach\MealPlanner;
use Carbon\CarbonImmutable;
use Database\Seeders\CoachDemoRecipeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach App 2.3: the meal log (quick add, recipes, ticking planned meals), the nutrition screen, and recipe photos.
 */
class CoachNutritionTest extends TestCase
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

    private function logger(): MealLogger
    {
        return app(MealLogger::class);
    }

    private function recipe(string $name): Recipe
    {
        return Recipe::where('name', $name)->firstOrFail();
    }

    // --- The log ---------------------------------------------------------------------------------------------------

    public function test_a_quick_add_is_saved_and_added_to_todays_totals(): void
    {
        $m = $this->member();

        $this->logger()->logQuick($m, 'lunch', ' Toasted sandwich ', 380, 21.5, 4, 40, 12);
        $this->logger()->logQuick($m, 'snack', 'Apple', 95, 0.5);

        $t = $this->logger()->todayTotals($m);
        $this->assertSame(475, $t['kcal']);
        $this->assertSame(22.0, $t['protein_g']);
        $this->assertSame(4.0, $t['fibre_g']);
        $this->assertSame(40.0, $t['carbs_g']);
        $this->assertSame(12.0, $t['fat_g']);
        $this->assertSame(2, $t['count']);
        $this->assertSame('Toasted sandwich', MealEntry::first()->name);
        $this->assertSame('quick', MealEntry::first()->source);
    }

    public function test_quick_add_rules(): void
    {
        $m = $this->member();

        foreach ([
            fn () => $this->logger()->logQuick($m, 'brunch', 'X', 100, 5),         // unknown slot
            fn () => $this->logger()->logQuick($m, 'lunch', '  ', 100, 5),          // no name
            fn () => $this->logger()->logQuick($m, 'lunch', str_repeat('a', 121), 100, 5),
            fn () => $this->logger()->logQuick($m, 'lunch', 'X', 0, 5),             // calories 1 to 3000
            fn () => $this->logger()->logQuick($m, 'lunch', 'X', 3001, 5),
            fn () => $this->logger()->logQuick($m, 'lunch', 'X', 100, -1),          // negative protein
            fn () => $this->logger()->logQuick($m, 'lunch', 'X', 100, 5, 0, 501),   // carbs too high
        ] as $bad) {
            try {
                $bad();
                $this->fail('Expected the quick add to be refused.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, MealEntry::count());
    }

    public function test_only_todays_entries_count_and_other_members_are_separate(): void
    {
        $m = $this->member();
        $other = $this->member();
        $this->logger()->logQuick($m, 'lunch', 'Today meal', 400, 20);
        $this->logger()->logQuick($other, 'lunch', 'Someone else', 900, 50);
        MealEntry::create(['user_id' => $m->id, 'logged_at' => now()->subDay(), 'slot' => 'lunch', 'name' => 'Yesterday', 'kcal' => 700, 'protein_g' => 30]);

        $this->assertSame(400, $this->logger()->todayTotals($m)['kcal']);
        $this->assertSame(['Today meal'], $this->logger()->todayEntries($m)->pluck('name')->all());

        // The SA day rolls over at local midnight.
        $this->travelTo(CarbonImmutable::parse('2026-11-26 00:30', 'Africa/Johannesburg'));
        $this->assertSame(0, $this->logger()->todayTotals($m)['kcal']);
    }

    public function test_entries_are_ordered_by_slot(): void
    {
        $m = $this->member();
        $this->logger()->logQuick($m, 'snack', 'S', 100, 1);
        $this->logger()->logQuick($m, 'breakfast', 'B', 100, 1);
        $this->logger()->logQuick($m, 'dinner', 'D', 100, 1);
        $this->logger()->logQuick($m, 'lunch', 'L', 100, 1);

        $this->assertSame(['B', 'L', 'D', 'S'], $this->logger()->todayEntries($m)->pluck('name')->all());
    }

    public function test_a_recipe_is_logged_with_its_nutrition_and_servings_and_keeps_it_if_the_recipe_changes(): void
    {
        $m = $this->member();
        $r = $this->recipe('Chicken and quinoa salad');

        $e = $this->logger()->logRecipe($m, $r, null, 2);

        $this->assertSame('lunch', $e->slot);
        $this->assertSame(840, $e->kcal);
        $this->assertSame(76.0, $e->protein_g);
        $this->assertSame(2, $e->servings);
        $this->assertSame('recipe', $e->source);

        $r->update(['kcal' => 100]);
        $this->assertSame(840, $e->fresh()->kcal, 'Editing a recipe never rewrites what was logged.');

        $this->assertSame(4, $this->logger()->logRecipe($m, $r, 'dinner', 99)->servings, 'Servings are capped at 4.');
    }

    public function test_a_recipe_the_member_may_not_have_cannot_be_logged(): void
    {
        $m = $this->member(['allergies' => ['nuts']]);

        $this->expectException(InvalidArgumentException::class);
        $this->logger()->logRecipe($m, $this->recipe('Apple with almond butter'));
    }

    public function test_remove_only_touches_the_members_own_entries(): void
    {
        $m = $this->member();
        $other = $this->member();
        $mine = $this->logger()->logQuick($m, 'lunch', 'Mine', 300, 10);
        $theirs = $this->logger()->logQuick($other, 'lunch', 'Theirs', 300, 10);

        $this->assertFalse($this->logger()->remove($m, $theirs->id));
        $this->assertTrue(MealEntry::whereKey($theirs->id)->exists());
        $this->assertTrue($this->logger()->remove($m, $mine->id));
        $this->assertFalse(MealEntry::whereKey($mine->id)->exists());
    }

    public function test_recent_shows_each_food_once_and_relog_copies_it_into_the_chosen_slot(): void
    {
        $m = $this->member();
        $this->logger()->logQuick($m, 'lunch', 'Toasted sandwich', 380, 21);
        $this->travel(1)->minutes();
        $this->logger()->logQuick($m, 'dinner', 'toasted sandwich', 380, 21);
        $this->travel(1)->minutes();
        $latest = $this->logger()->logQuick($m, 'snack', 'Apple', 95, 1);

        $recent = $this->logger()->recent($m);
        $this->assertSame(['Apple', 'toasted sandwich'], $recent->pluck('name')->all());

        $copy = $this->logger()->relog($m, $latest->id, 'breakfast');
        $this->assertSame('breakfast', $copy->slot);
        $this->assertSame(95, $copy->kcal);
        $this->assertNotSame($latest->id, $copy->id);

        $other = $this->member();
        $this->assertNull($this->logger()->relog($other, $latest->id, 'lunch'), 'Not someone else\'s entry.');
    }

    // --- Ticking planned meals ---------------------------------------------------------------------------------------

    public function test_ticking_a_planned_meal_logs_it_once_and_unticking_removes_it(): void
    {
        $m = $this->member();
        $planner = app(MealPlanner::class);
        $planner->planFor($m);
        $recipe = $planner->dayItems($planner->planFor($m), 0)->get('lunch')->recipe;

        $this->assertTrue($planner->toggleEaten($m, 0, 'lunch'));
        $entry = MealEntry::where('user_id', $m->id)->firstOrFail();
        $this->assertSame('plan', $entry->source);
        $this->assertSame($recipe->name, $entry->name);
        $this->assertSame($recipe->kcal, $entry->kcal);
        $this->assertNotNull($entry->meal_plan_item_id);

        $this->assertFalse($planner->toggleEaten($m, 0, 'lunch'));
        $this->assertSame(0, MealEntry::count());
        $this->assertTrue($planner->toggleEaten($m, 0, 'lunch'));
        $this->assertSame(1, MealEntry::count(), 'Never two entries for one planned meal.');
    }

    public function test_removing_the_logged_entry_unticks_the_plan_meal(): void
    {
        $m = $this->member();
        $planner = app(MealPlanner::class);
        $plan = $planner->planFor($m);
        $planner->toggleEaten($m, 0, 'dinner');
        $entry = MealEntry::firstOrFail();

        $this->logger()->remove($m, $entry->id);

        $this->assertNull($plan->items()->where('day_index', 0)->where('slot', 'dinner')->first()->eaten_at);
    }

    public function test_swapping_or_trading_an_eaten_meal_clears_its_tick_and_entry(): void
    {
        $m = $this->member();
        $planner = app(MealPlanner::class);
        $planner->planFor($m);

        $planner->toggleEaten($m, 0, 'lunch');
        $planner->setMeal($m, 0, 'lunch', $this->recipe('Tuna avo bowl'));
        $this->assertSame(0, MealEntry::count());
        $this->assertNull($planner->planFor($m)->items()->where('day_index', 0)->where('slot', 'lunch')->first()->eaten_at);

        $planner->toggleEaten($m, 0, 'dinner');
        $planner->trade($m, 0, 'dinner', 3);
        $this->assertSame(0, MealEntry::count());
    }

    public function test_the_plan_screen_tick_feeds_the_nutrition_totals(): void
    {
        $m = $this->member();
        $c = Livewire::actingAs($m)->test(Plan::class)->call('toggleEaten', 'breakfast');

        $c->assertSeeHtml('data-eat="breakfast"');
        $this->assertSame(1, $this->logger()->todayTotals($m)['count']);
        $this->actingAs($m)->get(route('coach.nutrition'))->assertOk()->assertSee('data-entry', false);
    }

    // --- Screens ---------------------------------------------------------------------------------------------------

    public function test_the_nutrition_screens_need_the_coach_addon(): void
    {
        $this->get(route('coach.nutrition'))->assertRedirect();
        $plain = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $plain->id, 'onboarding_complete' => true]);

        $this->actingAs($plain)->get(route('coach.nutrition'))->assertRedirect('/weight-loss');
        $this->actingAs($plain)->get(route('coach.nutrition.log'))->assertRedirect('/weight-loss');
    }

    public function test_the_nutrition_screen_shows_totals_goals_and_each_slot(): void
    {
        $m = $this->member();
        $this->logger()->logQuick($m, 'lunch', 'Toasted sandwich', 380, 21, 4, 40, 12);

        $this->actingAs($m)->get(route('coach.nutrition'))
            ->assertOk()
            ->assertSee('Toasted sandwich')
            ->assertSee('of 1,450 kcal today')
            ->assertSee('data-total="protein"', false)
            ->assertSee('21 / 92 g')
            ->assertSee('data-add="breakfast"', false)
            ->assertSee('data-add="snack"', false)
            ->assertSee('Nothing logged yet');

        Livewire::actingAs($m)->test(Nutrition::class)->call('remove', MealEntry::first()->id)->assertDontSee('Toasted sandwich');
        $this->assertSame(0, MealEntry::count());
    }

    public function test_the_log_screen_quick_add(): void
    {
        $m = $this->member();

        $c = Livewire::actingAs($m)->test(LogMeal::class, ['slot' => 'dinner'])->assertSet('slot', 'dinner');
        $c->call('quickAdd')->assertSee('Enter a name, calories and protein');
        $c->set('name', 'Pasta')->set('kcal', 'lots')->set('protein', '12')->call('quickAdd')->assertSee('Enter a name, calories and protein');
        $c->set('kcal', '3500')->call('quickAdd')->assertSee('between 1 and 3000');
        $c->set('kcal', '520')->set('protein', '18,5')->set('fibre', 'x')->call('quickAdd')->assertSee('must be numbers');
        $this->assertSame(0, MealEntry::count());

        $c->set('fibre', '5')->call('quickAdd')->assertRedirect(route('coach.nutrition'));
        $e = MealEntry::firstOrFail();
        $this->assertSame(['dinner', 'Pasta', 520, 18.5, 5.0], [$e->slot, $e->name, $e->kcal, $e->protein_g, $e->fibre_g]);
    }

    public function test_the_log_screen_picks_a_slot_from_the_time_of_day_and_ignores_bad_slots(): void
    {
        $m = $this->member();   // 09:00

        Livewire::actingAs($m)->test(LogMeal::class)->assertSet('slot', 'breakfast');
        Livewire::actingAs($m)->test(LogMeal::class, ['slot' => 'nonsense'])->assertSet('slot', 'breakfast')
            ->call('setSlot', 'lunch')->assertSet('slot', 'lunch')->call('setSlot', 'brunch')->assertSet('slot', 'lunch');

        $this->travelTo(CarbonImmutable::parse('2026-11-25 21:00', 'Africa/Johannesburg'));
        Livewire::actingAs($m)->test(LogMeal::class)->assertSet('slot', 'snack');
    }

    public function test_the_log_screen_logs_the_planned_meal_in_one_tap_and_offers_recent(): void
    {
        $m = $this->member();
        $planner = app(MealPlanner::class);
        $name = $planner->dayItems($planner->planFor($m), 0)->get('lunch')->recipe->name;

        $c = Livewire::actingAs($m)->test(LogMeal::class, ['slot' => 'lunch'])->assertSee('Planned for today')->assertSee($name);
        $c->call('logPlanned')->assertRedirect(route('coach.nutrition'));
        $this->assertSame($name, MealEntry::firstOrFail()->name);

        // Already eaten: the planned card is gone and the food shows under Recent.
        Livewire::actingAs($m)->test(LogMeal::class, ['slot' => 'lunch'])->assertDontSee('Planned for today')->assertSee('data-recent', false);
    }

    public function test_relogging_from_recent(): void
    {
        $m = $this->member();
        $e = $this->logger()->logQuick($m, 'lunch', 'Toasted sandwich', 380, 21);

        Livewire::actingAs($m)->test(LogMeal::class, ['slot' => 'snack'])->call('relog', $e->id)->assertRedirect(route('coach.nutrition'));

        $this->assertSame(['lunch', 'snack'], MealEntry::orderBy('id')->pluck('slot')->all());
    }

    public function test_log_as_eaten_now_from_the_recipe_page_uses_the_servings(): void
    {
        $m = $this->member();
        $r = $this->recipe('Tuna avo bowl');

        Livewire::actingAs($m)->test(RecipeShow::class, ['recipe' => $r])->assertSee('data-log-now', false)
            ->call('more')->call('logNow')->assertRedirect(route('coach.nutrition'));

        $e = MealEntry::firstOrFail();
        $this->assertSame(['Tuna avo bowl', 'lunch', 2, 820], [$e->name, $e->slot, $e->servings, $e->kcal]);
    }

    public function test_the_track_home_shows_the_meals_card(): void
    {
        $m = $this->member(['onboarded_at' => now()]);
        $this->logger()->logQuick($m, 'lunch', 'Sandwich', 380, 21);

        $this->actingAs($m)->get(route('coach.today'))->assertOk()
            ->assertSee('data-card="meals"', false)->assertSee('1 logged')->assertSee('380');
    }

    // --- Recipe photos ---------------------------------------------------------------------------------------------

    private function editor(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs(User::factory()->create(['role' => UserRole::HealthCoach]))->test(CoachRecipes::class);
    }

    private function newRecipe($c, $photo = null)
    {
        foreach ([
            'slot' => 'dinner', 'name' => 'Photo stew', 'minutes' => '30', 'kcal' => '400', 'protein_g' => '30', 'fibre_g' => '5',
            'carbs_g' => '20', 'fat_g' => '15', 'sugars_g' => '3', 'ingredients' => 'Beef | 100', 'steps' => 'Cook it.',
        ] as $k => $v) {
            $c->set($k, $v);
        }
        if ($photo) {
            $c->set('photo', $photo);
        }

        return $c;
    }

    public function test_a_recipe_photo_is_stored_and_shown(): void
    {
        Storage::fake('public');
        $c = $this->editor()->call('create');
        $this->newRecipe($c, UploadedFile::fake()->image('stew.jpg', 600, 400))->call('save')->assertHasNoErrors();

        $r = Recipe::where('name', 'Photo stew')->firstOrFail();
        $this->assertNotNull($r->image_path);
        Storage::disk('public')->assertExists($r->image_path);
        $this->assertStringContainsString($r->image_path, $r->photoUrl());

        $r->update(['approved_at' => now()]);
        $m = $this->member();
        Livewire::actingAs($m)->test(\App\Livewire\Coach\Recipes::class)->assertSeeHtml('data-photo');
        Livewire::actingAs($m)->test(RecipeShow::class, ['recipe' => $r])->assertSeeHtml($r->photoUrl());
    }

    public function test_replacing_or_removing_a_photo_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $c = $this->editor()->call('create');
        $this->newRecipe($c, UploadedFile::fake()->image('a.jpg'))->call('save');
        $r = Recipe::where('name', 'Photo stew')->firstOrFail();
        $first = $r->image_path;

        $this->editor()->call('edit', $r->id)->set('photo', UploadedFile::fake()->image('b.png'))->call('save')->assertHasNoErrors();
        $second = $r->fresh()->image_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->editor()->call('edit', $r->id)->set('removePhoto', true)->call('save')->assertHasNoErrors();
        $this->assertNull($r->fresh()->image_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_editing_without_touching_the_photo_keeps_it_and_deleting_the_recipe_removes_the_file(): void
    {
        Storage::fake('public');
        $c = $this->editor()->call('create');
        $this->newRecipe($c, UploadedFile::fake()->image('a.jpg'))->call('save');
        $r = Recipe::where('name', 'Photo stew')->firstOrFail();
        $path = $r->image_path;

        $this->editor()->call('edit', $r->id)->set('kcal', '410')->call('save')->assertHasNoErrors();
        $this->assertSame($path, $r->fresh()->image_path);
        Storage::disk('public')->assertExists($path);

        $this->editor()->call('delete', $r->id);
        $this->assertFalse(Recipe::whereKey($r->id)->exists());
        Storage::disk('public')->assertMissing($path);
    }

    public function test_only_small_images_are_accepted(): void
    {
        Storage::fake('public');
        $c = $this->editor()->call('create');

        $this->newRecipe($c, UploadedFile::fake()->create('notes.txt', 10, 'text/plain'))->call('save')->assertHasErrors(['photo']);
        $this->newRecipe($c, UploadedFile::fake()->create('script.php', 10, 'application/x-php'))->call('save')->assertHasErrors(['photo']);
        $this->newRecipe($c, UploadedFile::fake()->image('huge.jpg')->size(3000))->call('save')->assertHasErrors(['photo']);

        $this->assertSame(0, Recipe::where('name', 'Photo stew')->count());
    }
}
