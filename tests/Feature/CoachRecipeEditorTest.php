<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\CoachRecipes;
use App\Models\PatientProfile;
use App\Models\Recipe;
use App\Models\User;
use Database\Seeders\CoachDemoRecipeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin recipe editor: Amy supplies the real recipes, any change needs her approval before members see it.
 */
class CoachRecipeEditorTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function fill($component, array $over = [])
    {
        $d = array_merge([
            'slot' => 'dinner', 'name' => 'Sunday lamb stew', 'summary' => '', 'minutes' => '45', 'kcal' => '460',
            'protein_g' => '36', 'fibre_g' => '7', 'carbs_g' => '22', 'fat_g' => '24', 'sugars_g' => '6',
            'ingredients' => "Lean lamb | 140\nCarrots | 100", 'steps' => "Brown the lamb.\nSimmer for 40 minutes.",
            'diet_tags' => ['halal'], 'allergens' => [], 'avoid_tags' => ['red_meat'], 'is_active' => true,
        ], $over);
        foreach ($d as $k => $v) {
            $component->set($k, $v);
        }

        return $component;
    }

    public function test_only_admin_and_health_coaches_can_open_the_editor(): void
    {
        $this->get(route('admin.coach-recipes'))->assertRedirect();
        foreach ([UserRole::Patient, UserRole::Doctor] as $role) {
            $this->actingAs($this->user($role))->get(route('admin.coach-recipes'))->assertForbidden();
        }
        foreach ([UserRole::Admin, UserRole::HealthCoach] as $role) {
            $this->actingAs($this->user($role))->get(route('admin.coach-recipes'))->assertOk()->assertSee('Coach recipes');
        }
    }

    public function test_a_new_recipe_is_saved_unapproved_and_members_cannot_see_it(): void
    {
        $admin = $this->user(UserRole::Admin);

        $c = Livewire::actingAs($admin)->test(CoachRecipes::class)->call('create');
        $this->fill($c)->call('save')->assertHasNoErrors()->assertSee('needs approval');

        $r = Recipe::where('name', 'Sunday lamb stew')->firstOrFail();
        $this->assertSame('amy', $r->source);
        $this->assertNull($r->approved_at);
        $this->assertEquals([['name' => 'Lean lamb', 'grams' => 140.0], ['name' => 'Carrots', 'grams' => 100.0]], $r->ingredients);
        $this->assertSame(['Brown the lamb.', 'Simmer for 40 minutes.'], $r->steps);
        $this->assertSame(0, Recipe::usable()->count());
    }

    public function test_only_a_health_coach_can_approve_and_then_members_see_it(): void
    {
        $this->seed(CoachDemoRecipeSeeder::class);
        $r = Recipe::first();
        $r->update(['approved_at' => null]);
        $admin = $this->user(UserRole::Admin);
        $coach = $this->user(UserRole::HealthCoach);

        Livewire::actingAs($admin)->test(CoachRecipes::class)->assertDontSee('data-approve="'.$r->id.'"', false)->call('approve', $r->id)->assertForbidden();
        $this->assertNull($r->fresh()->approved_at);

        Livewire::actingAs($coach)->test(CoachRecipes::class)->assertSee('data-approve="'.$r->id.'"', false)->call('approve', $r->id);
        $this->assertNotNull($r->fresh()->approved_at);
        $this->assertSame($coach->id, $r->fresh()->approved_by);
        $this->assertTrue(Recipe::usable()->whereKey($r->id)->exists());
    }

    public function test_editing_clears_the_approval_and_turns_a_sample_into_a_real_recipe(): void
    {
        $this->seed(CoachDemoRecipeSeeder::class);
        $r = Recipe::where('name', 'Tuna avo bowl')->first();
        $this->assertTrue($r->isDemo());
        $this->assertNotNull($r->approved_at);

        $c = Livewire::actingAs($this->user(UserRole::HealthCoach))->test(CoachRecipes::class)->call('edit', $r->id)
            ->assertSet('name', 'Tuna avo bowl')->assertSet('ingredients', "Tuna in water, drained | 110\nAvocado | 70\nMixed leaves | 50\nCucumber | 60\nLemon juice | 10");
        $c->set('kcal', '400')->call('save')->assertHasNoErrors();

        $r->refresh();
        $this->assertSame(400, $r->kcal);
        $this->assertNull($r->approved_at, 'Any change needs approving again.');
        $this->assertNull($r->approved_by);
        $this->assertSame('amy', $r->source);
        $this->assertSame(0, Recipe::where('source', 'demo')->where('name', 'Tuna avo bowl')->count());
    }

    public function test_bad_input_is_refused(): void
    {
        $c = Livewire::actingAs($this->user(UserRole::Admin))->test(CoachRecipes::class)->call('create');

        $this->fill($c, ['name' => '', 'kcal' => 'lots'])->call('save')->assertHasErrors(['name', 'kcal']);
        $this->fill($c, ['name' => 'X', 'kcal' => '300', 'slot' => 'brunch'])->call('save')->assertHasErrors(['slot']);
        $this->fill($c, ['slot' => 'lunch', 'ingredients' => 'Chicken, lots'])->call('save')->assertHasErrors(['ingredients']);
        $this->fill($c, ['ingredients' => 'Chicken | 100', 'steps' => '  '])->call('save')->assertHasErrors(['steps']);
        $this->fill($c, ['steps' => 'Cook.', 'allergens' => ['pollen']])->call('save')->assertHasErrors(['allergens.0']);
        $this->fill($c, ['allergens' => [], 'diet_tags' => ['carnivore']])->call('save')->assertHasErrors(['diet_tags.0']);

        $this->assertSame(0, Recipe::count());
    }

    public function test_switching_off_and_deleting(): void
    {
        $this->seed(CoachDemoRecipeSeeder::class);
        $coach = $this->user(UserRole::HealthCoach);
        $real = Recipe::where('name', 'Veggie omelette')->first();
        $real->update(['source' => 'amy']);   // an approved real recipe
        $waiting = Recipe::where('name', 'Tuna avo bowl')->first();
        $waiting->update(['source' => 'amy', 'approved_at' => null]);

        $c = Livewire::actingAs($coach)->test(CoachRecipes::class);
        $c->call('toggleActive', $real->id);
        $this->assertFalse($real->fresh()->is_active);
        $c->call('toggleActive', $real->id);
        $this->assertTrue($real->fresh()->is_active);

        $c->call('delete', $real->id)->assertSee('switched off, not deleted');
        $this->assertTrue(Recipe::whereKey($real->id)->exists());

        $c->call('delete', $waiting->id);
        $this->assertFalse(Recipe::whereKey($waiting->id)->exists());

        // A sample recipe may be deleted even though it is approved.
        $sample = Recipe::where('source', 'demo')->first();
        $c->call('delete', $sample->id);
        $this->assertFalse(Recipe::whereKey($sample->id)->exists());
    }

    public function test_the_list_filters(): void
    {
        $this->seed(CoachDemoRecipeSeeder::class);
        Recipe::where('name', 'Tuna avo bowl')->update(['approved_at' => null]);
        Recipe::where('name', 'Veggie omelette')->update(['is_active' => false, 'source' => 'amy']);

        $c = Livewire::actingAs($this->user(UserRole::Admin))->test(CoachRecipes::class);
        $c->call('setFilter', 'waiting')->assertSee('Tuna avo bowl')->assertDontSee('Greek yoghurt berry bowl');
        $c->call('setFilter', 'off')->assertSee('Veggie omelette')->assertDontSee('Tuna avo bowl');
        $c->call('setFilter', 'demo')->assertSee('Tuna avo bowl')->assertDontSee('Veggie omelette');
        $c->call('setFilter', 'all')->set('search', 'omelette')->assertSee('Veggie omelette')->assertDontSee('Tuna avo bowl');
    }
}
