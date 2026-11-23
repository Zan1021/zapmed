<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\LogWeight;
use App\Livewire\Coach\Weight;
use App\Models\CoachingAssignment;
use App\Models\CoachProfile;
use App\Models\PatientProfile;
use App\Models\User;
use App\Models\WeightEntry;
use App\Services\Coach\CoachUnits;
use App\Services\Coach\WeightChart;
use App\Services\Coach\WeightLogger;
use App\Services\Coach\WeightStats;
use App\Services\Coach\WeightTip;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach App 1.3: weight tracker, log weight, goal rules, tips, chart, units.
 */
class CoachWeightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-24 09:00', 'Africa/Johannesburg'));
    }

    private function member(array $coach = []): User
    {
        $user = User::factory()->create(['role' => UserRole::Patient, 'gender' => 'female']);
        PatientProfile::create(['user_id' => $user->id, 'onboarding_complete' => true]);
        CoachingAssignment::create([
            'patient_id' => $user->id,
            'coach_id' => User::factory()->create(['role' => UserRole::HealthCoach])->id,
            'started_at' => now(),
        ]);
        CoachProfile::create(array_merge(['user_id' => $user->id, 'onboarded_at' => now()], $coach));

        return $user;
    }

    private function weigh(User $u, string $date, float $kg): void
    {
        WeightEntry::create(['user_id' => $u->id, 'logged_on' => $date, 'weight_kg' => $kg]);
    }

    // ---- units --------------------------------------------------------------------------------

    public function test_kg_and_lb_convert_both_ways_to_one_decimal(): void
    {
        config(['coach.units.allow_lb' => true]); // lb is OFF by default (South Africa uses kg)
        $this->assertSame(207.7, CoachUnits::fromKg(94.2, 'lb'));
        $this->assertSame(94.2, CoachUnits::toKg(207.7, 'lb'));
        $this->assertSame(94.2, CoachUnits::toKg(94.2, 'kg'));
        $this->assertSame('207.7 lb', CoachUnits::format(94.2, 'lb'));
        $this->assertSame('94.2 kg', CoachUnits::format(94.2, 'anything-else'));
        $this->assertNull(CoachUnits::format(null, 'kg'));
    }

    // ---- WeightLogger -------------------------------------------------------------------------

    public function test_saving_twice_in_a_day_replaces_todays_value(): void
    {
        $u = $this->member();
        $logger = app(WeightLogger::class);

        $logger->log($u, 95.0);
        $logger->log($u, 94.6);

        $this->assertSame(1, WeightEntry::where('user_id', $u->id)->count());
        $this->assertEquals(94.6, (float) WeightEntry::where('user_id', $u->id)->value('weight_kg'));
        $this->assertSame('2026-11-24', WeightEntry::where('user_id', $u->id)->first()->logged_on->toDateString());
    }

    public function test_first_weigh_in_becomes_the_start_weight_but_never_overwrites_one(): void
    {
        $fresh = $this->member();
        app(WeightLogger::class)->log($fresh, 101.5);
        $this->assertEquals(101.5, (float) CoachProfile::where('user_id', $fresh->id)->value('start_weight_kg'));

        $set = $this->member(['start_weight_kg' => 102.0]);
        app(WeightLogger::class)->log($set, 98.0);
        $this->assertEquals(102.0, (float) CoachProfile::where('user_id', $set->id)->value('start_weight_kg'));
    }

    public function test_implausible_weights_are_rejected(): void
    {
        $u = $this->member();

        foreach ([5.0, 19.9, 400.1, 900.0] as $bad) {
            try {
                app(WeightLogger::class)->log($u, $bad);
                $this->fail("{$bad} kg should be rejected");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, WeightEntry::count());
    }

    public function test_entry_carries_the_cycle_phase_only_when_tracking(): void
    {
        $tracking = $this->member(['track_cycle' => true, 'last_period_start' => '2026-11-04']); // day 21 = luteal
        app(WeightLogger::class)->log($tracking, 94.0);
        $this->assertSame('luteal', WeightEntry::where('user_id', $tracking->id)->value('cycle_phase'));

        $off = $this->member(['track_cycle' => false, 'last_period_start' => '2026-11-04']);
        app(WeightLogger::class)->log($off, 94.0);
        $this->assertNull(WeightEntry::where('user_id', $off->id)->value('cycle_phase'));
    }

    // ---- goal rules ---------------------------------------------------------------------------

    public function test_goal_rules(): void
    {
        $u = $this->member();
        $logger = app(WeightLogger::class);

        try {
            $logger->setGoal($u, 80.0);
            $this->fail('Goal needs a weigh-in first');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Log your weight first', $e->getMessage());
        }

        $logger->log($u, 94.0);

        foreach ([44.0 => 'below 45', 94.0 => 'below your current', 99.0 => 'below your current'] as $goal => $msg) {
            try {
                $logger->setGoal($u, (float) $goal);
                $this->fail("Goal {$goal} should be rejected");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($msg, $e->getMessage());
            }
        }

        $this->assertNull(CoachProfile::where('user_id', $u->id)->value('goal_weight_kg'));

        $logger->setGoal($u, 45.0); // exactly the floor is allowed
        $this->assertEquals(45.0, (float) CoachProfile::where('user_id', $u->id)->value('goal_weight_kg'));
        $logger->setGoal($u, 80.0);
        $this->assertEquals(80.0, (float) CoachProfile::where('user_id', $u->id)->value('goal_weight_kg'));
    }

    // ---- week change + tips -------------------------------------------------------------------

    private function stats(User $u): WeightStats
    {
        return WeightStats::forUser($u);
    }

    public function test_week_loss_needs_a_weigh_in_at_least_seven_days_back(): void
    {
        $u = $this->member();
        $this->weigh($u, '2026-11-20', 100.0);
        $this->weigh($u, '2026-11-24', 99.0);
        $this->assertNull($this->stats($u)->weekLossKg, 'Only 4 days of data');

        $this->weigh($u, '2026-11-17', 100.4);
        $this->assertSame(1.4, $this->stats($u)->weekLossKg);
    }

    public function test_tip_order_luteal_then_plateau_then_loss_then_gain(): void
    {
        config(['coach.units.allow_lb' => true]); // lb is OFF by default (South Africa uses kg)
        $u = $this->member();
        $this->weigh($u, '2026-11-17', 100.0);

        $this->weigh($u, '2026-11-24', 99.8); // 0.2 kg: plateau
        $this->assertStringContainsString('plateau is normal', WeightTip::for($this->stats($u), null));
        $this->assertStringContainsString('luteal', WeightTip::for($this->stats($u), 'luteal'), 'Luteal beats plateau');

        WeightEntry::where('user_id', $u->id)->whereDate('logged_on', '2026-11-24')->update(['weight_kg' => 98.8]);
        $this->assertSame('You lost 1.2 kg this week. Keep your protein up.', WeightTip::for($this->stats($u), 'follicular'));
        $this->assertStringContainsString('2.6 lb', WeightTip::for($this->stats($u), null, 'lb'));

        WeightEntry::where('user_id', $u->id)->whereDate('logged_on', '2026-11-24')->update(['weight_kg' => 100.9]);
        $this->assertStringContainsString('up 0.9 kg this week', WeightTip::for($this->stats($u), null));
    }

    public function test_tip_asks_for_more_weigh_ins_when_there_is_no_week_of_data(): void
    {
        $u = $this->member();
        $this->weigh($u, '2026-11-24', 99.0);

        $this->assertStringContainsString('Keep logging', WeightTip::for($this->stats($u), null));
    }

    // ---- chart --------------------------------------------------------------------------------

    public function test_chart_is_empty_without_weigh_ins(): void
    {
        $c = WeightChart::build($this->member(), 30);

        $this->assertFalse($c['hasData']);
        $this->assertSame([], $c['points']);
    }

    public function test_chart_window_places_points_by_calendar_day_and_drops_older_entries(): void
    {
        $u = $this->member(['goal_weight_kg' => 90]);
        $this->weigh($u, '2026-11-24', 99.0);      // today = right edge
        $this->weigh($u, '2026-11-11', 100.0);     // first day of a 14-day window = left edge
        $this->weigh($u, '2026-11-10', 105.0);     // outside the window

        $c = WeightChart::build($u, 14);

        $this->assertTrue($c['hasData']);
        $this->assertCount(2, $c['points']);
        $this->assertSame(18.0, (float) $c['points'][0]['x']);
        $this->assertSame(312.0, (float) $c['points'][1]['x']);
        $this->assertNotNull($c['goalY']);
        $this->assertNotNull($c['line']);
    }

    public function test_unknown_ranges_fall_back_to_30_days(): void
    {
        $this->assertSame(30, WeightChart::build($this->member(), 7)['days']);
        $this->assertSame(90, WeightChart::build($this->member(), 90)['days']);
    }

    public function test_luteal_days_are_shaded_only_when_tracking(): void
    {
        $on = $this->member(['track_cycle' => true, 'last_period_start' => '2026-11-04']);
        $this->weigh($on, '2026-11-24', 99.0);
        $this->assertNotEmpty(WeightChart::build($on, 14)['luteal']);

        $off = $this->member(['track_cycle' => false, 'last_period_start' => '2026-11-04']);
        $this->weigh($off, '2026-11-24', 99.0);
        $this->assertSame([], WeightChart::build($off, 14)['luteal']);
    }

    // ---- Weight screen ------------------------------------------------------------------------

    public function test_weight_screen_empty_state_invites_the_first_weigh_in(): void
    {
        Livewire::actingAs($this->member())->test(Weight::class)
            ->assertSee('Start your chart')
            ->assertSee('Log my weight')
            ->assertDontSee('Total change');
    }

    public function test_weight_screen_shows_tiles_tip_history_and_prediction(): void
    {
        $u = $this->member(['start_weight_kg' => 102, 'goal_weight_kg' => 80, 'height_cm' => 168]);
        $this->weigh($u, '2026-09-25', 100.0);
        $this->weigh($u, '2026-11-17', 95.4);
        $this->weigh($u, '2026-11-24', 94.2);

        Livewire::actingAs($u)->test(Weight::class)
            ->assertSee('-7.8 kg')                    // total change
            ->assertSee('33.4')                       // BMI
            ->assertSee('80.0 kg')                    // goal
            ->assertSeeInOrder(['Progress', '35%'])
            ->assertSee('You lost 1.2 kg this week')
            ->assertSee('Predicted goal date: about')
            ->assertSee('Tue 24 Nov')
            ->assertSee('BMI is a general guide and does not diagnose any condition');
    }

    public function test_pounds_show_everywhere_for_a_member_who_chose_lb(): void
    {
        config(['coach.units.allow_lb' => true]); // lb is OFF by default (South Africa uses kg)
        $u = $this->member(['units' => 'lb', 'start_weight_kg' => 102, 'goal_weight_kg' => 80]);
        $this->weigh($u, '2026-11-24', 94.2);

        Livewire::actingAs($u)->test(Weight::class)->assertSee('207.7 lb')->assertSee('-17.2 lb')->assertDontSee('94.2 kg');
    }

    public function test_range_buttons_switch_the_chart_and_ignore_unknown_values(): void
    {
        $u = $this->member();
        $this->weigh($u, '2026-11-24', 94.2);

        Livewire::actingAs($u)->test(Weight::class)
            ->assertSet('range', 30)
            ->call('setRange', 14)->assertSet('range', 14)->assertSee('last 14 days')
            ->call('setRange', 90)->assertSet('range', 90)
            ->call('setRange', 7)->assertSet('range', 90);
    }

    public function test_luteal_tip_and_legend_show_for_a_tracking_member_in_her_luteal_phase(): void
    {
        $u = $this->member(['track_cycle' => true, 'last_period_start' => '2026-11-04']);
        $this->weigh($u, '2026-11-24', 94.2);

        Livewire::actingAs($u)->test(Weight::class)
            ->assertSee('You are in your luteal phase')
            ->assertSee('Luteal phase (water can hide fat loss)');
    }

    public function test_goal_editor_defaults_five_below_current_and_saves_a_valid_goal(): void
    {
        $u = $this->member();
        $this->weigh($u, '2026-11-24', 94.2);

        Livewire::actingAs($u)->test(Weight::class)
            ->call('openGoal')
            ->assertSet('showGoal', true)
            ->assertSet('goalInput', 89.0)
            ->call('stepGoal', -1)->call('stepGoal', -1)
            ->assertSet('goalInput', 87.0)
            ->call('saveGoal')
            ->assertSet('showGoal', false)
            ->assertSee('87.0 kg');

        $this->assertEquals(87.0, (float) CoachProfile::where('user_id', $u->id)->value('goal_weight_kg'));
    }

    public function test_goal_editor_refuses_a_goal_at_or_above_current_or_below_the_floor(): void
    {
        $u = $this->member();
        $this->weigh($u, '2026-11-24', 94.2);

        Livewire::actingAs($u)->test(Weight::class)
            ->call('openGoal')->set('goalInput', 95.0)->call('saveGoal')
            ->assertSet('showGoal', true)
            ->assertSee('Your goal must be below your current weight')
            ->set('goalInput', 40.0)->call('saveGoal')
            ->assertSee('A goal cannot be below 45 kg');

        $this->assertNull(CoachProfile::where('user_id', $u->id)->value('goal_weight_kg'));
    }

    public function test_goal_editor_in_pounds_converts_before_checking_the_floor(): void
    {
        config(['coach.units.allow_lb' => true]); // lb is OFF by default (South Africa uses kg)
        $u = $this->member(['units' => 'lb']);
        $this->weigh($u, '2026-11-24', 94.2);

        $c = Livewire::actingAs($u)->test(Weight::class)->call('openGoal')->assertSet('goalInput', 196.0); // 94.2 - 5 = 89.2 kg = 196.7 lb, rounded
        $c->set('goalInput', 99.0)->call('saveGoal')->assertSee('A goal cannot be below 45 kg'); // 99 lb = 44.9 kg
        $c->set('goalInput', 176.0)->call('saveGoal')->assertSet('showGoal', false);

        $this->assertEquals(79.8, (float) CoachProfile::where('user_id', $u->id)->value('goal_weight_kg'));
    }

    public function test_goal_editor_without_a_weigh_in_explains_what_to_do(): void
    {
        Livewire::actingAs($this->member())->test(Weight::class)->call('openGoal')->assertSee('Log your weight first');
    }

    // ---- Log weight screen --------------------------------------------------------------------

    public function test_log_screen_starts_from_the_latest_weight_and_steps_in_tenths(): void
    {
        $u = $this->member();
        $this->weigh($u, '2026-11-20', 94.2);

        Livewire::actingAs($u)->test(LogWeight::class)
            ->assertSet('value', 94.2)
            ->call('step', 1)->assertSet('value', 94.3)
            ->call('step', -1)->call('step', -1)->assertSet('value', 94.1);
    }

    public function test_saving_creates_todays_entry_and_goes_back_to_the_weight_screen(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(LogWeight::class)
            ->set('value', 93.7)
            ->call('save')
            ->assertRedirect(route('coach.weight'));

        $entry = WeightEntry::where('user_id', $u->id)->firstOrFail();
        $this->assertEquals(93.7, (float) $entry->weight_kg);
        $this->assertSame('2026-11-24', $entry->logged_on->toDateString());
    }

    public function test_pounds_are_converted_to_kg_for_storage_and_the_unit_choice_is_remembered(): void
    {
        config(['coach.units.allow_lb' => true]); // lb is OFF by default (South Africa uses kg)
        $u = $this->member();

        Livewire::actingAs($u)->test(LogWeight::class)
            ->set('value', 94.2)
            ->call('setUnit', 'lb')
            ->assertSet('unit', 'lb')
            ->assertSet('value', 207.7)
            ->call('save');

        $this->assertSame('lb', CoachProfile::where('user_id', $u->id)->value('units'));
        $this->assertEquals(94.2, (float) WeightEntry::where('user_id', $u->id)->value('weight_kg'));
    }

    public function test_log_screen_rejects_an_out_of_range_number_without_saving(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(LogWeight::class)
            ->set('value', 5.0)->call('save')
            ->assertSee('between 20 and 400 kg')
            ->assertNoRedirect();

        $this->assertSame(0, WeightEntry::count());
    }

    public function test_log_screen_shows_the_cycle_tag_only_when_tracking(): void
    {
        $on = $this->member(['track_cycle' => true, 'last_period_start' => '2026-11-04']);
        Livewire::actingAs($on)->test(LogWeight::class)->assertSee('Cycle day 21')->assertSee('Luteal phase');

        $off = $this->member(['track_cycle' => false, 'last_period_start' => '2026-11-04']);
        Livewire::actingAs($off)->test(LogWeight::class)->assertDontSee('Cycle day');
    }

    // ---- access + pages -----------------------------------------------------------------------

    public function test_weight_pages_need_the_coach_add_on_and_a_set_up_profile(): void
    {
        $plain = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $plain->id, 'onboarding_complete' => true]);

        $this->actingAs($plain)->get('/coach/weight')->assertRedirect('/weight-loss');
        $this->actingAs($plain)->get('/coach/weight/log')->assertRedirect('/weight-loss');

        $member = $this->member();
        $this->actingAs($member)->get('/coach/weight')->assertOk()->assertSee('Weight');
        $this->actingAs($member)->get('/coach/weight/log')->assertOk()->assertSee("Save today's weight", false);
    }

    public function test_saved_toast_appears_on_the_weight_screen_after_saving(): void
    {
        $u = $this->member();

        $this->actingAs($u)->withSession(['coach_toast' => 'Weight saved. Today\'s value is updated.'])
            ->get('/coach/weight')->assertOk()->assertSee('Weight saved.', false);
    }

    public function test_track_tab_stays_highlighted_on_weight_screens_and_no_medicine_is_named(): void
    {
        $u = $this->member();
        $this->weigh($u, '2026-11-24', 94.2);

        $html = $this->actingAs($u)->get('/coach/weight')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/zc-tab on[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*Track/s', $html);
        foreach (['ozempic', 'wegovy', 'mounjaro', 'saxenda', 'semaglutide', 'tirzepatide', 'liraglutide'] as $name) {
            $this->assertStringNotContainsStringIgnoringCase($name, $html);
        }
    }

    // ---- kg only (South Africa) ---------------------------------------------------------------

    public function test_kg_is_the_only_unit_unless_lb_is_deliberately_switched_on(): void
    {
        $this->assertFalse((bool) config('coach.units.allow_lb'), 'lb must be off by default');
        $this->assertSame('kg', CoachUnits::normalise('lb'));
        $this->assertSame('kg', CoachUnits::normalise(null));
        $this->assertSame('94.2 kg', CoachUnits::format(94.2, 'lb'));
        $this->assertSame(94.2, CoachUnits::fromKg(94.2, 'lb'));
    }

    public function test_a_stale_lb_setting_still_shows_kg_everywhere_and_no_lb_switch_is_offered(): void
    {
        $u = $this->member(['units' => 'lb', 'start_weight_kg' => 102, 'goal_weight_kg' => 80]);
        $this->weigh($u, '2026-11-24', 94.2);

        Livewire::actingAs($u)->test(Weight::class)->assertSee('94.2 kg')->assertDontSee(' lb');

        Livewire::actingAs($u)->test(LogWeight::class)
            ->assertSet('unit', 'kg')
            ->assertSet('value', 94.2)
            ->assertDontSee('setUnit', false)
            ->call('setUnit', 'lb')
            ->assertSet('unit', 'kg')
            ->assertSet('value', 94.2);

        $this->assertSame('lb', CoachProfile::where('user_id', $u->id)->value('units'), 'setUnit(lb) must not change the profile');
    }

    public function test_new_members_default_to_kg_in_the_database(): void
    {
        $u = $this->member();

        $this->assertSame('kg', CoachProfile::where('user_id', $u->id)->first()->fresh()->units);
    }
}