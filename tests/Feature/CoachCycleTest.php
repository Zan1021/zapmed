<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\Cycle;
use App\Models\CoachingAssignment;
use App\Models\CoachProfile;
use App\Models\CycleEntry;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Coach\CycleCalendar;
use App\Services\Coach\CycleTracker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach App 2.5: the cycle screen (wheel, phase cards, tip, mood and symptoms, period started today).
 */
class CoachCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 09:00', 'Africa/Johannesburg'));
    }

    private function member(array $profile = [], string $gender = 'female'): User
    {
        $user = User::factory()->create(['role' => UserRole::Patient, 'gender' => $gender]);
        PatientProfile::create(['user_id' => $user->id, 'onboarding_complete' => true]);
        CoachingAssignment::create([
            'patient_id' => $user->id,
            'coach_id' => User::factory()->create(['role' => UserRole::HealthCoach])->id,
            'started_at' => now(),
        ]);
        CoachProfile::create(array_merge([
            'user_id' => $user->id, 'onboarded_at' => now(), 'track_cycle' => true, 'cycle_length' => 28, 'period_length' => 5,
            'last_period_start' => '2026-11-15',   // today (25 Nov) is cycle day 11
        ], $profile));

        return $user;
    }

    private function tracker(): CycleTracker
    {
        return app(CycleTracker::class);
    }

    public function test_the_screen_shows_the_day_phase_wheel_cards_tip_and_notice(): void
    {
        $m = $this->member();

        $this->actingAs($m)->get(route('coach.cycle'))->assertOk()
            ->assertSee('data-wheel', false)
            ->assertSee('data-today-arc', false)
            ->assertSee('Follicular phase')
            ->assertSee('data-day>11<', false)
            ->assertSee('Period expected in about 18 days')
            ->assertSee('data-card="weight"', false)->assertSee('data-card="hunger"', false)
            ->assertSee('data-card="water"', false)->assertSee('data-card="mood"', false)
            ->assertSee("Amy's tip", false)
            ->assertSee('These are estimates')
            ->assertSee('Tell your doctor if your cycle changes a lot');
    }

    public function test_the_wheel_has_one_arc_per_cycle_day_and_one_thick_today(): void
    {
        $m = $this->member(['cycle_length' => 30]);

        $html = $this->actingAs($m)->get(route('coach.cycle'))->getContent();

        $this->assertSame(30, substr_count($html, 'stroke-linecap="butt"'));
        $this->assertSame(1, substr_count($html, 'data-today-arc'));
        $this->assertSame(1, substr_count($html, 'stroke-width="26"'));
    }

    public function test_the_phase_changes_the_cards(): void
    {
        $m = $this->member(['last_period_start' => '2026-11-22']);   // day 4: period

        $this->actingAs($m)->get(route('coach.cycle'))->assertSee('Period')->assertSee('Water weight can be a little higher');

        CoachProfile::where('user_id', $m->id)->update(['last_period_start' => '2026-11-03']);   // day 23: luteal
        $this->actingAs($m)->get(route('coach.cycle'))->assertSee('Luteal phase')->assertSee('Water retention can push the scale up');
    }

    public function test_it_is_hidden_when_the_tracker_is_off_or_for_men(): void
    {
        $this->get(route('coach.cycle'))->assertRedirect();

        $off = $this->member(['track_cycle' => false]);
        $this->actingAs($off)->get(route('coach.cycle'))->assertRedirect(route('coach.today'));

        $man = $this->member(['track_cycle' => false], 'male');
        $this->actingAs($man)->get(route('coach.cycle'))->assertRedirect(route('coach.today'));

        $plain = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $plain->id, 'onboarding_complete' => true]);
        $this->actingAs($plain)->get(route('coach.cycle'))->assertRedirect('/weight-loss');
    }

    public function test_a_member_with_no_period_date_is_asked_for_it(): void
    {
        $m = $this->member(['last_period_start' => null]);

        $this->actingAs($m)->get(route('coach.cycle'))->assertOk()->assertSee('When did your last period start?')->assertDontSee('data-wheel', false);

        Livewire::actingAs($m)->test(Cycle::class)->set('periodDate', '2026-11-20')->call('savePeriodDate')->assertSee('Cycle day');
        $this->assertSame('2026-11-20', CoachProfile::where('user_id', $m->id)->first()->last_period_start->toDateString());
    }

    public function test_period_started_today_resets_to_day_one(): void
    {
        $m = $this->member();
        $this->assertSame(11, CycleCalendar::status(CoachProfile::where('user_id', $m->id)->first())['day']);

        Livewire::actingAs($m)->test(Cycle::class)->call('startToday')->assertSeeHtml('data-day>1<');

        $status = CycleCalendar::status(CoachProfile::where('user_id', $m->id)->first());
        $this->assertSame(1, $status['day']);
        $this->assertSame('menstrual', $status['phase']);
    }

    public function test_the_period_date_rules(): void
    {
        $m = $this->member();

        foreach (['', 'not a date', '2026-13-45', '2026-11-26', '2025-11-01', '2026-02-30'] as $bad) {
            try {
                $this->tracker()->setLastPeriodStart($m, $bad);
                $this->fail("Accepted {$bad}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->tracker()->setLastPeriodStart($m, '2026-11-25');   // today is fine
        $this->tracker()->setLastPeriodStart($m, '2025-11-26');   // just inside a year
        $this->assertSame('2025-11-26', CoachProfile::where('user_id', $m->id)->first()->last_period_start->toDateString());
    }

    public function test_cycle_and_period_length_rules(): void
    {
        $m = $this->member();

        foreach ([[20, 5], [36, 5], [28, 1], [28, 11]] as [$c, $p]) {
            try {
                $this->tracker()->setLengths($m, $c, $p);
                $this->fail("Accepted {$c}/{$p}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->tracker()->setLengths($m, 21, 2);
        $this->tracker()->setLengths($m, 35, 10);
        $p = CoachProfile::where('user_id', $m->id)->first();
        $this->assertSame([35, 10], [$p->cycle_length, $p->period_length]);

        Livewire::actingAs($m)->test(Cycle::class)->set('cycleLength', 'abc')->call('saveLengths')->assertSee('whole numbers')
            ->set('cycleLength', '30')->set('periodLength', '4')->call('saveLengths')->assertDontSee('whole numbers');
        $this->assertSame(30, CoachProfile::where('user_id', $m->id)->value('cycle_length'));
    }

    public function test_the_phases_follow_the_cycle_length(): void
    {
        // 35 day cycle, 5 day period: ovulation is days 21 to 23 (14 to 12 days before the end), luteal after.
        $this->assertSame('menstrual', CycleCalendar::phaseFor(5, 35, 5));
        $this->assertSame('follicular', CycleCalendar::phaseFor(20, 35, 5));
        $this->assertSame('ovulation', CycleCalendar::phaseFor(21, 35, 5));
        $this->assertSame('ovulation', CycleCalendar::phaseFor(23, 35, 5));
        $this->assertSame('luteal', CycleCalendar::phaseFor(24, 35, 5));
    }

    public function test_mood_is_one_a_day_and_tapping_it_again_clears_it(): void
    {
        $m = $this->member();

        $c = Livewire::actingAs($m)->test(Cycle::class)->call('mood', 4)->assertSeeHtml('data-mood-pick="4"');
        $this->assertSame(4, $this->tracker()->entryToday($m)->mood);
        $c->call('mood', 2);
        $this->assertSame(2, $this->tracker()->entryToday($m)->mood);
        $c->call('mood', 2);
        $this->assertNull($this->tracker()->entryToday($m)->mood);
        $c->call('mood', 9)->assertSee('Pick one of the faces');
        $this->assertSame(1, CycleEntry::count(), 'One row for the day.');
    }

    public function test_symptoms_toggle_and_unknown_ones_are_refused(): void
    {
        $m = $this->member();

        $c = Livewire::actingAs($m)->test(Cycle::class)->call('symptom', 'cramps')->call('symptom', 'bloating');
        $this->assertEqualsCanonicalizing(['cramps', 'bloating'], $this->tracker()->entryToday($m)->symptoms);
        $c->call('symptom', 'cramps');
        $this->assertSame(['bloating'], $this->tracker()->entryToday($m)->symptoms);
        $c->call('symptom', 'fever')->assertSee('not in the list');
        $this->assertCount(7, config('coach.cycle.symptoms'));
    }

    public function test_entries_are_per_day_and_per_member(): void
    {
        $m = $this->member();
        $other = $this->member();
        $this->tracker()->setMood($m, 5);
        $this->tracker()->setMood($other, 1);

        $this->travelTo(CarbonImmutable::parse('2026-11-26 09:00', 'Africa/Johannesburg'));
        $this->assertNull($this->tracker()->entryToday($m), 'A new day starts empty.');
        $this->tracker()->setMood($m, 3);

        $this->assertSame(2, CycleEntry::where('user_id', $m->id)->count());
        $this->assertSame(1, CycleEntry::where('user_id', $other->id)->value('mood'));
    }

    public function test_nothing_can_be_saved_while_the_tracker_is_off(): void
    {
        $m = $this->member(['track_cycle' => false]);

        foreach ([
            fn () => $this->tracker()->setMood($m, 3),
            fn () => $this->tracker()->toggleSymptom($m, 'cramps'),
            fn () => $this->tracker()->startPeriodToday($m),
            fn () => $this->tracker()->setLengths($m, 28, 5),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected the cycle tracker to refuse.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, CycleEntry::count());
    }

    public function test_the_track_home_cycle_card_links_to_the_screen_only_when_on(): void
    {
        $m = $this->member();
        $this->actingAs($m)->get(route('coach.today'))->assertOk()->assertSee('data-cycle-link', false);

        $off = $this->member(['track_cycle' => false]);
        $this->actingAs($off)->get(route('coach.today'))->assertOk()->assertDontSee('data-cycle-link', false)->assertDontSee('data-card="cycle"', false);
    }
}
