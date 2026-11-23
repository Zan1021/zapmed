<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\Today;
use App\Models\CoachingAssignment;
use App\Models\CoachProfile;
use App\Models\DoseEntry;
use App\Models\PatientProfile;
use App\Models\ReminderSetting;
use App\Models\User;
use App\Models\WaterEntry;
use App\Models\WeightEntry;
use App\Services\Coach\CoachClock;
use App\Services\Coach\CycleCalendar;
use App\Services\Coach\MedicationLevel;
use App\Services\Coach\NextReminder;
use App\Services\Coach\WeightStats;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach App 1.2: the Track home and the numbers behind its cards (weight, medication, cycle, water, reminders).
 */
class CoachTrackHomeTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $coach = [], string $gender = 'female'): User
    {
        $user = User::factory()->create(['role' => UserRole::Patient, 'gender' => $gender]);
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

    // ---- WeightStats --------------------------------------------------------------------------

    public function test_percent_to_goal_follows_the_guide_formula_and_is_clamped(): void
    {
        $this->assertSame(35, WeightStats::percent(102.0, 94.3, 80.0));
        $this->assertSame(0, WeightStats::percent(102.0, 103.0, 80.0), 'Gaining never shows below 0');
        $this->assertSame(100, WeightStats::percent(102.0, 79.0, 80.0), 'Passing the goal caps at 100');
        $this->assertNull(WeightStats::percent(102.0, 94.0, null), 'No goal, no percent');
        $this->assertNull(WeightStats::percent(80.0, 79.0, 80.0), 'Start at or below goal is meaningless');
    }

    public function test_bmi_is_kg_over_height_squared_and_needs_a_height(): void
    {
        $this->assertSame(33.4, WeightStats::bmi(94.2, 168));
        $this->assertNull(WeightStats::bmi(94.2, null));
        $this->assertNull(WeightStats::bmi(null, 168));
    }

    public function test_predicted_goal_date_uses_average_daily_loss_over_90_days(): void
    {
        $u = $this->member(['start_weight_kg' => 100, 'goal_weight_kg' => 80]);
        $this->weigh($u, '2026-09-25', 100.0);
        $this->weigh($u, '2026-11-24', 94.0); // 6 kg in 60 days = 0.1 kg/day, 14 kg to go = 140 days

        $stats = WeightStats::forUser($u);

        $this->assertSame(94.0, $stats->current);
        $this->assertSame('2027-04-13', $stats->predictedGoalDate->toDateString());
    }

    public function test_no_prediction_without_loss_goal_or_two_weigh_ins(): void
    {
        $u = $this->member(['goal_weight_kg' => 80]);
        $this->weigh($u, '2026-11-24', 94.0);
        $this->assertNull(WeightStats::forUser($u)->predictedGoalDate, 'One weigh-in is not enough');

        $this->weigh($u, '2026-11-01', 90.0); // earlier and lighter = gained since
        $this->assertNull(WeightStats::forUser($u)->predictedGoalDate, 'No loss shows a dash');

        $noGoal = $this->member();
        $this->weigh($noGoal, '2026-11-01', 100.0);
        $this->weigh($noGoal, '2026-11-24', 94.0);
        $this->assertNull(WeightStats::forUser($noGoal)->predictedGoalDate);
    }

    public function test_weigh_ins_older_than_90_days_are_ignored_for_the_prediction(): void
    {
        $u = $this->member(['goal_weight_kg' => 80]);
        $this->weigh($u, '2026-06-01', 120.0); // far outside the window
        $this->weigh($u, '2026-09-01', 100.0);
        $this->weigh($u, '2026-11-24', 94.0);

        $rate = (100.0 - 94.0) / CoachClock::daysBetween(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-11-24'));
        $expected = CarbonImmutable::parse('2026-11-24')->addDays((int) ceil(14 / $rate))->toDateString();

        $this->assertSame($expected, WeightStats::forUser($u)->predictedGoalDate->toDateString());
    }

    // ---- MedicationLevel ----------------------------------------------------------------------

    public function test_medication_level_is_empty_until_a_dose_is_logged(): void
    {
        $m = MedicationLevel::forUser($this->member());

        $this->assertNull($m->levelNowPercent);
        $this->assertNull($m->nextDoseDate);
        $this->assertSame([], $m->curve);
    }

    public function test_next_dose_is_seven_days_after_the_last_and_the_curve_peaks_at_one(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-24 09:00', 'Africa/Johannesburg'));
        $u = $this->member();
        DoseEntry::create(['user_id' => $u->id, 'taken_at' => now()->subDays(3), 'site' => 'Left thigh', 'pain' => 2]);

        $m = MedicationLevel::forUser($u);

        $this->assertSame(4, $m->daysToNext);
        $this->assertSame('Left thigh', $m->lastSite);
        $this->assertGreaterThan(0, $m->levelNowPercent);
        $this->assertLessThanOrEqual(100, $m->levelNowPercent);
        $curve = $m->curve;
        $this->assertEqualsWithDelta(1.0, max(array_column($curve, 'level')), 0.0001);
        $this->assertSame(-14.0, (float) $curve[0]['t']);
        $this->assertSame(7.0, (float) end($curve)['t']);
        $this->assertTrue(end($curve)['future']);
        $this->assertFalse($curve[0]['future']);
    }

    // ---- CycleCalendar ------------------------------------------------------------------------

    public function test_phases_for_a_28_day_cycle_match_the_guide(): void
    {
        $expect = [1 => 'menstrual', 5 => 'menstrual', 6 => 'follicular', 13 => 'follicular',
            14 => 'ovulation', 16 => 'ovulation', 17 => 'luteal', 28 => 'luteal'];

        foreach ($expect as $day => $phase) {
            $this->assertSame($phase, CycleCalendar::phaseFor($day, 28, 5), "day {$day}");
        }
    }

    public function test_cycle_status_gives_day_phase_and_days_to_the_next_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-24 09:00', 'Africa/Johannesburg'));
        $profile = CoachProfile::create([
            'user_id' => User::factory()->create()->id, 'track_cycle' => true, 'cycle_length' => 28,
            'period_length' => 5, 'last_period_start' => '2026-11-04', // 20 days ago = day 21
        ]);

        $s = CycleCalendar::status($profile);
        $this->assertSame(21, $s['day']);
        $this->assertSame('luteal', $s['phase']);
        $this->assertSame(8, $s['days_to_period']);
        $this->assertSame('up', $s['arrows']['weight']);

        $profile->update(['last_period_start' => '2026-10-25']); // 30 days ago, wraps to day 3
        $this->assertSame(3, CycleCalendar::status($profile->fresh())['day']);
    }

    public function test_cycle_status_is_hidden_when_switched_off_or_undated(): void
    {
        $off = CoachProfile::create(['user_id' => User::factory()->create()->id, 'track_cycle' => false, 'last_period_start' => '2026-11-04']);
        $undated = CoachProfile::create(['user_id' => User::factory()->create()->id, 'track_cycle' => true]);

        $this->assertNull(CycleCalendar::status($off));
        $this->assertNull(CycleCalendar::status($undated));
        $this->assertNull(CycleCalendar::status(null));
    }

    // ---- NextReminder -------------------------------------------------------------------------

    public function test_next_water_time_steps_through_the_window(): void
    {
        $s = ['every_hours' => 2, 'from' => '08:00', 'until' => '20:00'];
        $at = fn (string $t) => CarbonImmutable::parse("2026-11-24 {$t}", 'Africa/Johannesburg');

        $this->assertSame('10:00', NextReminder::nextWater($s, $at('09:30')));
        $this->assertSame('08:00', NextReminder::nextWater($s, $at('07:00')));
        $this->assertSame('08:00', NextReminder::nextWater($s, $at('20:30')), 'After the window: first slot tomorrow');
    }

    public function test_reminder_phrases_use_the_saved_settings_and_skip_disabled_ones(): void
    {
        $u = $this->member();
        ReminderSetting::create(['user_id' => $u->id, 'type' => 'hydration', 'enabled' => true, 'settings' => ['every_hours' => 3, 'from' => '06:00', 'until' => '22:00']]);
        ReminderSetting::create(['user_id' => $u->id, 'type' => 'weigh_in', 'enabled' => true, 'settings' => ['day' => 'wednesday']]);

        $now = CarbonImmutable::parse('2026-11-24 07:00', 'Africa/Johannesburg');
        $this->assertSame(['Water at 09:00', 'Weigh-in Wednesday'], NextReminder::phrases($u, $now));

        ReminderSetting::where('user_id', $u->id)->where('type', 'hydration')->update(['enabled' => false]);
        $this->assertSame(['Weigh-in Wednesday'], NextReminder::phrases($u, $now));
    }

    // ---- The Track home screen ----------------------------------------------------------------

    public function test_empty_state_has_no_invented_numbers(): void
    {
        Livewire::actingAs($this->member())->test(Today::class)
            ->assertSee('No weight logged yet')
            ->assertSee('Log your first dose')
            ->assertSee('0</b> / 2,200 mL', false)
            ->assertDontSee('to goal');
    }

    public function test_weight_card_shows_current_goal_percent_and_prediction(): void
    {
        $u = $this->member(['start_weight_kg' => 102, 'goal_weight_kg' => 80]);
        $this->weigh($u, '2026-10-25', 98.0);
        $this->weigh($u, '2026-11-24', 94.3);

        Livewire::actingAs($u)->test(Today::class)
            ->assertSee('94.3 kg')
            ->assertSee('Goal 80.0 kg')
            ->assertSee('35% to goal')
            ->assertSee('Predicted');
    }

    public function test_pounds_are_shown_when_the_member_chose_lb(): void
    {
        config(['coach.units.allow_lb' => true]); // lb is OFF by default (South Africa uses kg)
        $u = $this->member(['units' => 'lb', 'start_weight_kg' => 102, 'goal_weight_kg' => 80]);
        $this->weigh($u, '2026-11-24', 94.2);

        Livewire::actingAs($u)->test(Today::class)->assertSee('207.7 lb')->assertDontSee('94.2 kg');
    }

    public function test_medication_card_shows_next_dose_and_the_estimate_disclaimer(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-24 09:00', 'Africa/Johannesburg'));
        $u = $this->member();
        DoseEntry::create(['user_id' => $u->id, 'taken_at' => now()->subDays(3), 'site' => 'Right thigh', 'pain' => 1]);

        Livewire::actingAs($u)->test(Today::class)
            ->assertSee('Next dose in 4 days')
            ->assertSee('estimated level today')
            ->assertSee('It is not a blood test');
    }

    public function test_cycle_card_only_appears_for_members_who_switched_it_on(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-24 09:00', 'Africa/Johannesburg'));

        $on = $this->member(['track_cycle' => true, 'last_period_start' => '2026-11-04']);
        Livewire::actingAs($on)->test(Today::class)
            ->assertSee('Luteal phase')
            ->assertSee('period expected in 8 days')
            ->assertSee('Predictions are estimates');

        $off = $this->member(['track_cycle' => false, 'last_period_start' => '2026-11-04']);
        Livewire::actingAs($off)->test(Today::class)->assertDontSee('Luteal phase')->assertDontSee('period expected');

        $undated = $this->member(['track_cycle' => true]);
        Livewire::actingAs($undated)->test(Today::class)->assertSee('Add the date your last period started');
    }

    public function test_water_quick_add_saves_an_entry_and_updates_the_total(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(Today::class)
            ->call('addWater', 250)
            ->assertSee('250</b> / 2,200 mL', false)
            ->call('addWater', 250)
            ->assertSee('500</b> / 2,200 mL', false);

        $this->assertSame(2, WaterEntry::where('user_id', $u->id)->count());
    }

    public function test_water_only_accepts_the_amounts_the_app_offers(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(Today::class)->call('addWater', 123)->call('addWater', 99999)->call('addWater', -250);

        $this->assertSame(0, WaterEntry::where('user_id', $u->id)->count());
    }

    public function test_todays_water_is_counted_in_south_african_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-24 09:00', 'Africa/Johannesburg'));
        $u = $this->member();
        // 00:30 SAST today is still yesterday in UTC. It must count. 23:30 SAST yesterday must not.
        WaterEntry::create(['user_id' => $u->id, 'logged_at' => '2026-11-23 22:30:00', 'ml' => 500]);
        WaterEntry::create(['user_id' => $u->id, 'logged_at' => '2026-11-23 21:30:00', 'ml' => 1000]);

        Livewire::actingAs($u)->test(Today::class)->assertSee('500</b> / 2,200 mL', false);
    }

    public function test_the_track_home_never_names_a_medicine_and_keeps_the_disclaimer(): void
    {
        $u = $this->member();
        $html = $this->actingAs($u)->get('/coach')->assertOk()->assertSee('not medical advice')->getContent();

        foreach (['ozempic', 'wegovy', 'mounjaro', 'saxenda', 'semaglutide', 'tirzepatide', 'liraglutide'] as $name) {
            $this->assertStringNotContainsStringIgnoringCase($name, $html);
        }
    }
}
