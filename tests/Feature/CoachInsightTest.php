<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\Board;
use App\Livewire\Coach\Insight;
use App\Models\ActivityEntry;
use App\Models\CoachingAssignment;
use App\Models\CoachProfile;
use App\Models\DoseEntry;
use App\Models\InsightEntry;
use App\Models\MealEntry;
use App\Models\PatientProfile;
use App\Models\SideEffectEntry;
use App\Models\User;
use App\Models\WaterEntry;
use App\Models\WeightEntry;
use App\Services\Coach\InsightBuilder;
use App\Services\Notifications\NotificationDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach App 2.6: insights (rule based headline, what we noticed, goal, check-in, feedback, weekly sheet).
 */
class CoachInsightTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00', 'Africa/Johannesburg'));   // a Wednesday
    }

    private function member(array $profile = [], bool $withCoach = true): User
    {
        $user = User::factory()->create(['role' => UserRole::Patient, 'gender' => 'female']);
        PatientProfile::create(['user_id' => $user->id, 'onboarding_complete' => true]);
        if ($withCoach) {
            $this->coach = User::factory()->create(['role' => UserRole::HealthCoach, 'first_name' => 'Amy']);
            CoachingAssignment::create(['patient_id' => $user->id, 'coach_id' => $this->coach->id, 'started_at' => now()]);
        } else {
            // Access through the subscription path would need a plan; an ended assignment is not enough, so give a coach then end it later.
            $c = User::factory()->create(['role' => UserRole::HealthCoach]);
            CoachingAssignment::create(['patient_id' => $user->id, 'coach_id' => $c->id, 'started_at' => now()]);
        }
        CoachProfile::create(array_merge(['user_id' => $user->id, 'onboarded_at' => now(), 'track_cycle' => false], $profile));

        return $user;
    }

    private function insights(): InsightBuilder
    {
        return app(InsightBuilder::class);
    }

    private function weigh(User $u, float $kg, string $date): void
    {
        WeightEntry::create(['user_id' => $u->id, 'logged_on' => $date, 'weight_kg' => $kg]);
    }

    private function dose(User $u, string $when): void
    {
        DoseEntry::create(['user_id' => $u->id, 'taken_at' => CarbonImmutable::parse($when, 'Africa/Johannesburg')->utc(), 'site' => 'abdomen_left', 'pain' => 0]);
    }

    // --- Rules -----------------------------------------------------------------------------------------------------

    public function test_a_fresh_member_gets_the_steady_headline_and_a_weigh_in_goal(): void
    {
        $m = $this->member();

        $d = $this->insights()->daily($m);

        $this->assertSame('steady', $d['rule']);
        $this->assertSame('You are on track', $d['headline']);
        $this->assertSame('Log your weight today', $d['goal']['text']);
        $this->assertStringContainsString('/coach/weight/log', $d['goal']['url']);
        $this->assertNull($d['levelPercent']);
        $this->assertNull($d['cycle']);
    }

    public function test_a_recent_side_effect_uses_the_symptom_name_and_when(): void
    {
        $m = $this->member();
        SideEffectEntry::create(['user_id' => $m->id, 'logged_on' => '2026-11-23', 'symptom' => 'Nausea', 'score' => 4, 'flagged' => false]);

        $d = $this->insights()->daily($m);

        $this->assertSame('symptom_recent', $d['rule']);
        $this->assertSame('Side effects usually settle', $d['headline']);
        $this->assertStringContainsString('nausea 2 days ago', $d['text']);

        SideEffectEntry::create(['user_id' => $m->id, 'logged_on' => '2026-11-25', 'symptom' => 'Headache', 'score' => 3, 'flagged' => false]);
        $this->assertStringContainsString('headache today', $this->insights()->daily($m)['text']);
    }

    public function test_an_old_side_effect_is_ignored(): void
    {
        $m = $this->member();
        SideEffectEntry::create(['user_id' => $m->id, 'logged_on' => '2026-11-15', 'symptom' => 'Nausea', 'score' => 4, 'flagged' => false]);

        $this->assertSame('steady', $this->insights()->daily($m)['rule']);
    }

    public function test_a_flagged_symptom_points_to_the_coach_and_doctor_route(): void
    {
        $m = $this->member();
        SideEffectEntry::create(['user_id' => $m->id, 'logged_on' => '2026-11-25', 'symptom' => 'Abdominal pain', 'score' => 8, 'flagged' => true]);

        $d = $this->insights()->daily($m);

        $this->assertSame('tough_symptom', $d['rule']);
        $this->assertStringContainsString('Something feels wrong', $d['text']);
    }

    public function test_dose_day_and_the_days_after_a_dose(): void
    {
        $m = $this->member();

        $this->dose($m, '2026-11-18 20:00');   // 7 days ago: due today
        $this->assertSame('dose_day', $this->insights()->daily($m)['rule']);

        DoseEntry::where('user_id', $m->id)->delete();
        $this->dose($m, '2026-11-23 20:00');   // 2 days ago
        $this->assertSame('after_dose', $this->insights()->daily($m)['rule']);

        DoseEntry::where('user_id', $m->id)->delete();
        $this->dose($m, '2026-11-21 20:00');   // 4 days ago: nothing special, falls through
        $this->assertSame('steady', $this->insights()->daily($m)['rule']);
    }

    public function test_the_medication_level_is_an_estimate_with_a_disclaimer(): void
    {
        $m = $this->member();
        $this->dose($m, '2026-11-24 20:00');

        $this->assertIsInt($this->insights()->daily($m)['levelPercent']);
        Livewire::actingAs($m)->test(Insight::class)->assertSee('Your level today')->assertSee('illustrative');
    }

    public function test_a_long_gap_since_the_last_weigh_in_asks_for_one(): void
    {
        $m = $this->member();
        $this->weigh($m, 90, '2026-11-10');

        $this->assertSame('weigh_in', $this->insights()->daily($m)['rule']);
        $this->weigh($m, 89, '2026-11-24');
        $this->assertSame('steady', $this->insights()->daily($m)['rule']);
    }

    public function test_priority_a_hard_symptom_beats_everything(): void
    {
        $m = $this->member();
        $this->dose($m, '2026-11-18 20:00');
        $this->weigh($m, 90, '2026-11-01');
        SideEffectEntry::create(['user_id' => $m->id, 'logged_on' => '2026-11-24', 'symptom' => 'Vomiting', 'score' => 9, 'flagged' => true]);

        $this->assertSame('tough_symptom', $this->insights()->daily($m)['rule']);
    }

    // --- What we noticed and the goal --------------------------------------------------------------------------------

    public function test_what_we_noticed_reports_weight_water_and_meals(): void
    {
        $m = $this->member();
        $this->weigh($m, 90.0, '2026-11-18');
        $this->weigh($m, 88.5, '2026-11-25');
        foreach ([0, 1, 2] as $ago) {
            WaterEntry::create(['user_id' => $m->id, 'logged_at' => now()->subDays($ago), 'ml' => 2300]);
        }
        MealEntry::create(['user_id' => $m->id, 'logged_at' => now(), 'slot' => 'lunch', 'name' => 'X', 'kcal' => 400, 'protein_g' => 20]);

        $lines = $this->insights()->daily($m)['noticed'];

        $this->assertStringContainsString('down 1.5 kg', $lines[0]);
        $this->assertContains('You reached your water goal on 3 of the last 7 days.', $lines);
        $this->assertContains('You logged meals on 1 of the last 7 days.', $lines);
    }

    public function test_the_goal_follows_the_day(): void
    {
        $m = $this->member();
        $this->weigh($m, 90, '2026-11-24');
        $this->assertSame('Log your first meal today', $this->insights()->daily($m)['goal']['text']);

        MealEntry::create(['user_id' => $m->id, 'logged_at' => now(), 'slot' => 'breakfast', 'name' => 'Oats', 'kcal' => 300, 'protein_g' => 20]);
        $this->assertSame('Take a short walk', $this->insights()->daily($m)['goal']['text'], 'Morning, meal logged: a walk.');

        $this->travelTo(CarbonImmutable::parse('2026-11-25 15:30', 'Africa/Johannesburg'));
        MealEntry::where('user_id', $m->id)->update(['logged_at' => now()->subHours(2)]);
        $this->assertSame('Have a glass of water', $this->insights()->daily($m)['goal']['text'], 'Afternoon with little water.');

        WaterEntry::create(['user_id' => $m->id, 'logged_at' => now(), 'ml' => 2000]);
        $this->assertSame('Add a protein snack', $this->insights()->daily($m)['goal']['text'], 'Protein well under target by the afternoon.');
    }

    public function test_women_with_the_tracker_on_get_a_cycle_card_and_others_do_not(): void
    {
        $on = $this->member(['track_cycle' => true, 'cycle_length' => 28, 'period_length' => 5, 'last_period_start' => '2026-11-15']);
        $off = $this->member(['track_cycle' => false, 'last_period_start' => '2026-11-15']);

        $this->assertStringContainsString('Cycle day 11, Follicular phase', $this->insights()->daily($on)['cycle']['text']);
        $this->assertNull($this->insights()->daily($off)['cycle']);
        Livewire::actingAs($off)->test(Insight::class)->assertDontSee('data-cycle-card', false);
    }

    // --- Check-in, feedback ------------------------------------------------------------------------------------------

    public function test_check_in_answers_are_saved_one_a_day_and_can_change(): void
    {
        $m = $this->member();

        $this->assertStringContainsString('Good to hear', $this->insights()->answerCheckin($m, 'better'));
        $this->assertStringContainsString('Keep logging', $this->insights()->answerCheckin($m, 'same'));
        $this->assertSame('same', $this->insights()->todayEntry($m)->answer);
        $this->assertSame(1, InsightEntry::count());

        $this->expectException(InvalidArgumentException::class);
        $this->insights()->answerCheckin($m, 'terrible');
    }

    public function test_worse_tells_the_assigned_coach_by_name_and_marks_the_board(): void
    {
        $m = $this->member();

        $reply = $this->insights()->answerCheckin($m, 'worse');

        $this->assertStringContainsString('Amy has been told', $reply);
        $this->assertNotNull($this->insights()->todayEntry($m)->coach_told_at);

        Livewire::actingAs($this->coach)->test(Board::class)->assertSee('data-worse', false)->assertSee('feels worse today');
    }

    public function test_worse_sends_one_message_without_health_detail_when_switched_on(): void
    {
        config(['coach.insights.notify_enabled' => true]);
        $m = $this->member();
        $sent = [];
        $this->mock(NotificationDispatcher::class, function ($mock) use (&$sent) {
            $mock->shouldReceive('send')->andReturnUsing(function ($message) use (&$sent) {
                $sent[] = $message;

                return [];
            });
        });

        $this->insights()->answerCheckin($m, 'worse');
        $this->insights()->answerCheckin($m, 'better');
        $this->insights()->answerCheckin($m, 'worse');

        $this->assertCount(1, $sent, 'The coach is told once a day.');
        $this->assertSame($this->coach->id, $sent[0]->user->id);
        $this->assertStringContainsString('No health detail', $sent[0]->body);
        $this->assertStringNotContainsStringIgnoringCase('nausea', $sent[0]->body);
    }

    public function test_worse_sends_nothing_by_default(): void
    {
        $m = $this->member();
        $sent = 0;
        $this->mock(NotificationDispatcher::class, function ($mock) use (&$sent) {
            $mock->shouldReceive('send')->andReturnUsing(function () use (&$sent) {
                $sent++;

                return [];
            });
        });

        $this->insights()->answerCheckin($m, 'worse');

        $this->assertSame(0, $sent, 'COACH_CHECKIN_NOTIFY is off by default.');
    }

    public function test_worse_without_a_coach_does_not_claim_one_was_told(): void
    {
        $m = $this->member();
        CoachingAssignment::where('patient_id', $m->id)->update(['ended_at' => now()]);

        $reply = $this->insights()->answerCheckin($m, 'worse');

        $this->assertStringContainsString('We have noted it', $reply);
        $this->assertStringNotContainsString('has been told', $reply);
        $this->assertNull($this->insights()->todayEntry($m)->coach_told_at);
    }

    public function test_the_board_only_flags_todays_worse_answers(): void
    {
        $m = $this->member();
        InsightEntry::create(['user_id' => $m->id, 'entry_date' => '2026-11-24', 'answer' => 'worse']);

        Livewire::actingAs($this->coach)->test(Board::class)->assertDontSee('data-worse', false);

        $this->insights()->answerCheckin($m, 'worse');
        Livewire::actingAs($this->coach)->test(Board::class)->assertSee('data-worse', false);
    }

    public function test_thumbs_feedback_toggles(): void
    {
        $m = $this->member();

        $this->assertTrue($this->insights()->feedback($m, true)->helpful);
        $this->assertFalse($this->insights()->feedback($m, false)->helpful);
        $this->assertNull($this->insights()->feedback($m, false)->helpful, 'Tapping the same thumb again clears it.');
    }

    // --- Screen and weekly sheet -----------------------------------------------------------------------------------

    public function test_the_screen_needs_the_addon_and_always_shows_the_footer(): void
    {
        $this->get(route('coach.insight'))->assertRedirect();
        $plain = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $plain->id, 'onboarding_complete' => true]);
        $this->actingAs($plain)->get(route('coach.insight'))->assertRedirect('/weight-loss');

        $m = $this->member();
        $this->actingAs($m)->get(route('coach.insight'))->assertOk()
            ->assertSee('How are you feeling today?')
            ->assertSee("Today's goal", false)
            ->assertSee('What we noticed')
            ->assertSee('Weekly insight')
            ->assertSee('Insights are general information and not medical advice.');
    }

    public function test_the_screen_check_in_and_thumbs(): void
    {
        $m = $this->member();

        Livewire::actingAs($m)->test(Insight::class)
            ->call('answer', 'worse')->assertSee('Amy has been told')
            ->call('answer', 'gone')->assertSee('Great news')->assertDontSee('Amy has been told')
            ->call('answer', 'dizzy')->assertSee('Pick one of the answers')
            ->call('feedback', true)->assertSeeHtml('data-thumb="up"');
        $this->assertTrue($this->insights()->todayEntry($m)->helpful);
    }

    public function test_the_weekly_sheet_counts_entries_and_wins(): void
    {
        $m = $this->member();
        $this->weigh($m, 90.0, '2026-11-19');
        $this->weigh($m, 88.8, '2026-11-25');
        $this->dose($m, '2026-11-23 20:00');
        WaterEntry::create(['user_id' => $m->id, 'logged_at' => now(), 'ml' => 2300]);
        MealEntry::create(['user_id' => $m->id, 'logged_at' => now(), 'slot' => 'lunch', 'name' => 'X', 'kcal' => 400, 'protein_g' => 20]);
        ActivityEntry::create(['user_id' => $m->id, 'logged_at' => now(), 'kind' => 'workout', 'name' => 'Walk', 'minutes' => 30, 'kcal' => 100]);
        $this->insights()->answerCheckin($m, 'better');
        // Outside the 7 day window: ignored.
        DoseEntry::create(['user_id' => $m->id, 'taken_at' => now()->subDays(9), 'site' => 'abdomen_left', 'pain' => 0]);

        $w = $this->insights()->weekly($m);

        $this->assertSame('2026-11-19', $w['from']->toDateString());
        $this->assertSame('2026-11-25', $w['to']->toDateString());
        $this->assertSame(1.2, $w['weightChangeKg']);
        $this->assertSame(7, $w['entries']);   // 2 weights, 1 dose, 1 water, 1 meal, 1 activity, 1 check-in
        $this->assertSame('A strong week', $w['headline']);
        $this->assertCount(5, $w['wins']);

        Livewire::actingAs($m)->test(Insight::class)->call('openWeekly')->assertSee('A strong week')->assertSee('Wins this week')->assertSee('data-entries', false)
            ->call('closeWeekly')->assertDontSee('data-weekly-title', false);
    }

    public function test_a_quiet_week_says_every_log_counts(): void
    {
        $m = $this->member();

        $w = $this->insights()->weekly($m);

        $this->assertSame('Every log counts', $w['headline']);
        $this->assertSame(0, $w['entries']);
        $this->assertNull($w['weightChangeKg']);
    }

    public function test_the_insight_tab_is_live(): void
    {
        $m = $this->member();

        $this->actingAs($m)->get(route('coach.today'))->assertOk()->assertSee(route('coach.insight'), false);
    }
}
