<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\Activity;
use App\Livewire\Coach\LogActivity;
use App\Models\ActivityEntry;
use App\Models\CoachingAssignment;
use App\Models\CoachProfile;
use App\Models\PatientProfile;
use App\Models\User;
use App\Models\WeightEntry;
use App\Services\Coach\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach App 2.4: activity (workouts from a MET library, daily steps, 7-day bars).
 */
class CoachActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 09:00', 'Africa/Johannesburg'));
    }

    private function member(): User
    {
        $user = User::factory()->create(['role' => UserRole::Patient, 'gender' => 'female']);
        PatientProfile::create(['user_id' => $user->id, 'onboarding_complete' => true]);
        CoachingAssignment::create([
            'patient_id' => $user->id,
            'coach_id' => User::factory()->create(['role' => UserRole::HealthCoach])->id,
            'started_at' => now(),
        ]);
        CoachProfile::create(['user_id' => $user->id, 'onboarded_at' => now()]);

        return $user;
    }

    private function logger(): ActivityLogger
    {
        return app(ActivityLogger::class);
    }

    private function weigh(User $u, float $kg, string $date = '2026-11-25'): void
    {
        WeightEntry::create(['user_id' => $u->id, 'logged_on' => $date, 'weight_kg' => $kg]);
    }

    public function test_the_library_has_the_four_groups_with_8_4_3_and_4_workouts(): void
    {
        $counts = collect($this->logger()->workouts())->groupBy('group')->map->count()->all();

        $this->assertSame(['cardio' => 8, 'strength' => 4, 'mind_body' => 3, 'sports' => 4], $counts);
        $this->assertSame(['cardio', 'strength', 'mind_body', 'sports'], array_keys(config('coach.activity.groups')));
    }

    public function test_workout_calories_use_met_weight_and_time(): void
    {
        $m = $this->member();
        $this->weigh($m, 80);

        $e = $this->logger()->logWorkout($m, 'run', 30);   // 9.8 x 80 x 0.5

        $this->assertSame(392, $e->kcal);
        $this->assertSame(30, $e->minutes);
        $this->assertSame('Run', $e->name);
        $this->assertSame(9.8, $e->met);
        $this->assertSame(80.0, $e->weight_kg);
        $this->assertSame('workout', $e->kind);
    }

    public function test_the_default_weight_is_used_until_one_is_logged_and_old_entries_keep_their_calories(): void
    {
        $m = $this->member();

        $first = $this->logger()->logWorkout($m, 'walk', 60);   // 3.5 x 75 x 1
        $this->assertSame(263, $first->kcal);
        $this->assertSame(75.0, $first->weight_kg);

        $this->weigh($m, 60);
        $second = $this->logger()->logWorkout($m, 'walk', 60);  // 3.5 x 60 x 1
        $this->assertSame(210, $second->kcal);
        $this->assertSame(263, $first->fresh()->kcal, 'A later weight never rewrites old entries.');
    }

    public function test_workout_rules(): void
    {
        $m = $this->member();

        foreach ([
            fn () => $this->logger()->logWorkout($m, 'quidditch', 30),
            fn () => $this->logger()->logWorkout($m, 'run', 4),
            fn () => $this->logger()->logWorkout($m, 'run', 301),
        ] as $bad) {
            try {
                $bad();
                $this->fail('Expected the workout to be refused.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, ActivityEntry::count());
    }

    public function test_steps_are_one_entry_a_day_and_typing_again_corrects_it(): void
    {
        $m = $this->member();

        $a = $this->logger()->setSteps($m, 6420);
        $this->assertSame(218, $a->kcal);                    // 6420 x 0.034
        $b = $this->logger()->setSteps($m, 8000);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, ActivityEntry::where('kind', 'steps')->count());
        $this->assertSame(8000, $this->logger()->todayTotals($m)['steps']);
        $this->assertSame(272, $this->logger()->todayTotals($m)['kcal']);

        $this->expectException(InvalidArgumentException::class);
        $this->logger()->setSteps($m, 100001);
    }

    public function test_totals_combine_workouts_and_steps_for_today_only(): void
    {
        $m = $this->member();
        $other = $this->member();
        $this->weigh($m, 80);
        $this->logger()->logWorkout($m, 'run', 30);        // 392
        $this->logger()->logWorkout($m, 'yoga', 60);       // 2.5 x 80 = 200
        $this->logger()->setSteps($m, 1000);               // 34
        $this->logger()->logWorkout($other, 'run', 60);
        ActivityEntry::create(['user_id' => $m->id, 'logged_at' => now()->subDay(), 'kind' => 'workout', 'name' => 'Old', 'minutes' => 30, 'kcal' => 999]);

        $t = $this->logger()->todayTotals($m);

        $this->assertSame(['kcal' => 626, 'steps' => 1000, 'minutes' => 90, 'workouts' => 2], $t);
    }

    public function test_the_last_seven_days_run_oldest_to_today_and_use_the_local_day(): void
    {
        $m = $this->member();
        $this->weigh($m, 80);
        $this->logger()->logWorkout($m, 'run', 30);   // today 392
        foreach ([1 => 100, 3 => 250, 6 => 40, 7 => 999] as $ago => $kcal) {
            ActivityEntry::create(['user_id' => $m->id, 'logged_at' => now()->subDays($ago), 'kind' => 'workout', 'name' => 'X', 'minutes' => 10, 'kcal' => $kcal]);
        }

        $week = $this->logger()->lastSevenDays($m);

        $this->assertCount(7, $week);
        $this->assertSame('2026-11-19', $week[0]['date']);
        $this->assertSame(40, $week[0]['kcal']);
        $this->assertSame([0, 0, 250, 0, 100, 392], array_slice(array_column($week, 'kcal'), 1));
        $this->assertTrue($week[6]['today']);
        $this->assertFalse($week[5]['today']);
        $this->assertNotContains(999, array_column($week, 'kcal'), '7 days ago is outside the window.');

        // 00:30 local the next day: yesterday's session is now "yesterday".
        $this->travelTo(CarbonImmutable::parse('2026-11-26 00:30', 'Africa/Johannesburg'));
        $this->assertSame(0, $this->logger()->todayTotals($m)['kcal']);
        $this->assertSame(392, $this->logger()->lastSevenDays($m)[5]['kcal']);
    }

    public function test_remove_only_touches_the_members_own_entries(): void
    {
        $m = $this->member();
        $other = $this->member();
        $mine = $this->logger()->logWorkout($m, 'walk', 30);
        $theirs = $this->logger()->logWorkout($other, 'walk', 30);

        $this->assertFalse($this->logger()->remove($m, $theirs->id));
        $this->assertTrue(ActivityEntry::whereKey($theirs->id)->exists());
        $this->assertTrue($this->logger()->remove($m, $mine->id));
        $this->assertFalse(ActivityEntry::whereKey($mine->id)->exists());
    }

    // --- Screens ---------------------------------------------------------------------------------------------------

    public function test_the_screens_need_the_coach_addon(): void
    {
        $this->get(route('coach.activity'))->assertRedirect();
        $plain = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $plain->id, 'onboarding_complete' => true]);

        $this->actingAs($plain)->get(route('coach.activity'))->assertRedirect('/weight-loss');
        $this->actingAs($plain)->get(route('coach.activity.log'))->assertRedirect('/weight-loss');
    }

    public function test_the_activity_screen_shows_totals_bars_sync_and_the_list(): void
    {
        $m = $this->member();
        $this->weigh($m, 80);
        $this->logger()->logWorkout($m, 'run', 30);
        $this->logger()->setSteps($m, 6420);

        $this->actingAs($m)->get(route('coach.activity'))->assertOk()
            ->assertSee('Burned today')
            ->assertSee('610 kcal')
            ->assertSee('6,420')
            ->assertSee('data-week', false)
            ->assertSeeText('Run, 30 min')
            ->assertSeeText('6,420 steps')
            ->assertSee('Sync with Apple Health or Google Fit')
            ->assertSee('Coming soon');
    }

    public function test_steps_can_be_typed_and_deleted_on_the_screen(): void
    {
        $m = $this->member();

        $c = Livewire::actingAs($m)->test(Activity::class);
        $c->set('steps', 'lots')->call('saveSteps')->assertSee('whole number');
        $c->set('steps', '999999')->call('saveSteps')->assertSee('between 0 and 100,000');
        $c->set('steps', '6,420')->call('saveSteps')->assertSet('steps', '')->assertSee('6,420');
        $this->assertSame(6420, $this->logger()->todayTotals($m)['steps']);

        $c->call('remove', ActivityEntry::first()->id);
        $this->assertSame(0, ActivityEntry::count());
    }

    public function test_the_log_screen_lists_the_groups_and_searches(): void
    {
        $m = $this->member();

        Livewire::actingAs($m)->test(LogActivity::class)
            ->assertSee('Cardio')->assertSee('Strength')->assertSee('Mind and body')->assertSee('Sports')
            ->assertSee('data-workout="yoga"', false)
            ->set('search', 'yo')->assertSee('Yoga')->assertDontSee('Tennis')
            ->set('search', 'zzz')->assertSee('No workouts match');
    }

    public function test_choosing_a_workout_shows_the_estimate_and_saves_it(): void
    {
        $m = $this->member();
        $this->weigh($m, 80);

        $c = Livewire::actingAs($m)->test(LogActivity::class)->call('select', 'run')
            ->assertSee('Run')->assertSee('392 kcal');
        $c->call('setMinutes', 60)->assertSet('minutes', '60')->assertSee('784 kcal');
        $c->call('setMinutes', 33)->assertSet('minutes', '60');   // only the offered quick values
        $c->call('save')->assertRedirect(route('coach.activity'));

        $e = ActivityEntry::firstOrFail();
        $this->assertSame(['run', 60, 784], [$e->workout_key, $e->minutes, $e->kcal]);
    }

    public function test_the_log_screen_checks_its_input(): void
    {
        $m = $this->member();

        $c = Livewire::actingAs($m)->test(LogActivity::class);
        $c->call('save')->assertSee('Pick a workout');
        $c->call('select', 'quidditch')->assertSet('selected', null);
        $c->call('select', 'walk')->set('minutes', 'abc')->call('save')->assertSee('whole number');
        $c->set('minutes', '1')->call('save')->assertSee('between 5 and 300');
        $c->call('cancel')->assertSet('selected', null);
        $this->assertSame(0, ActivityEntry::count());
    }

    public function test_the_track_home_shows_the_activity_card(): void
    {
        $m = $this->member();
        $this->logger()->setSteps($m, 5000);

        $this->actingAs($m)->get(route('coach.today'))->assertOk()
            ->assertSee('data-card="activity"', false)
            ->assertSee('5,000 steps')
            ->assertSee('170');
    }
}
