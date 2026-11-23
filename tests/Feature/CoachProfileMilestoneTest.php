<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\Milestone;
use App\Livewire\Coach\Profile;
use App\Models\CoachCall;
use App\Models\CoachingAssignment;
use App\Models\CoachProfile;
use App\Models\DoseEntry;
use App\Models\PatientProfile;
use App\Models\User;
use App\Models\WaterEntry;
use App\Models\WeightEntry;
use App\Services\Coach\MilestoneStats;
use App\Services\Coach\ProfileSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach App 1.10: the profile screen (saved choices) and the milestone screen (progress numbers).
 */
class CoachProfileMilestoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-24 09:00', 'Africa/Johannesburg'));
    }

    private function member(string $gender = 'female', array $coach = []): User
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

    private function weigh(User $user, string $date, float $kg): void
    {
        WeightEntry::create(['user_id' => $user->id, 'logged_on' => $date, 'weight_kg' => $kg]);
    }

    public function test_profile_and_milestone_need_the_addon_and_a_patient(): void
    {
        $this->get(route('coach.profile'))->assertRedirect();

        $plain = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $plain->id, 'onboarding_complete' => true]);
        $this->actingAs($plain)->get(route('coach.profile'))->assertRedirect('/weight-loss');
        $this->actingAs($plain)->get(route('coach.milestone'))->assertRedirect('/weight-loss');
    }

    public function test_profile_page_renders_with_the_tab_active(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get(route('coach.profile'))
            ->assertOk()
            ->assertSee('Zapmed programme')
            ->assertSee('data-section="cycle"', false)
            ->assertSee('Share Zapmed, earn rewards')
            ->assertDontSee('data-section="units"', false); // kg only
    }

    public function test_saves_diet_activity_months_and_lists(): void
    {
        $user = $this->member();

        Livewire::actingAs($user)->test(Profile::class)
            ->call('setDiet', 'vegetarian')
            ->call('setActivity', 'light')
            ->call('setMonths', 6)
            ->call('toggle', 'allergies', 'gluten')
            ->call('toggle', 'allergies', 'nuts')
            ->call('toggle', 'allergies', 'gluten') // off again
            ->call('toggle', 'avoids', 'pork')
            ->call('toggle', 'health_concerns', 'pcos')
            ->assertSet('error', null);

        $p = CoachProfile::where('user_id', $user->id)->first();
        $this->assertSame('vegetarian', $p->diet);
        $this->assertSame('light', $p->activity_level);
        $this->assertSame(6, (int) $p->target_months);
        $this->assertSame(['nuts'], $p->allergies);
        $this->assertSame(['pork'], $p->avoids);
        $this->assertSame(['pcos'], $p->health_concerns);
    }

    public function test_unknown_choices_are_refused_and_nothing_is_saved(): void
    {
        $user = $this->member();

        Livewire::actingAs($user)->test(Profile::class)
            ->call('setDiet', 'carnivore<script>')
            ->assertSet('error', 'That choice is not available.')
            ->call('setMonths', 5)
            ->assertSet('error', 'Please pick one of the listed durations.')
            ->call('toggle', 'allergies', 'kryptonite')
            ->assertSet('error', 'That choice is not available.')
            ->call('toggle', 'secret_column', 'x')
            ->assertSet('error', 'Unknown profile list.');

        $p = CoachProfile::where('user_id', $user->id)->first();
        $this->assertNull($p->diet);
        $this->assertNull($p->target_months);
        $this->assertEmpty((array) $p->allergies);
    }

    public function test_height_is_validated(): void
    {
        $user = $this->member();

        Livewire::actingAs($user)->test(Profile::class)
            ->set('height', '50')->call('saveHeight')
            ->assertSet('error', 'Please enter a height between 100 and 230 cm.')
            ->set('height', '168')->call('saveHeight')
            ->assertSet('error', null);

        $this->assertSame(168, (int) CoachProfile::where('user_id', $user->id)->value('height_cm'));
    }

    public function test_goal_follows_the_weight_goal_rules(): void
    {
        $user = $this->member();
        $this->weigh($user, '2026-11-24', 90.0);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('goal', '40')->call('saveGoal')
            ->assertSet('error', 'A goal cannot be below 45 kg.')
            ->set('goal', '95')->call('saveGoal')
            ->assertSet('error', 'Your goal must be below your current weight.')
            ->set('goal', '78,5')->call('saveGoal')
            ->assertSet('error', null);

        $this->assertEquals(78.5, (float) CoachProfile::where('user_id', $user->id)->value('goal_weight_kg'));
    }

    public function test_cycle_switch_toggles_for_women_and_never_turns_on_for_men(): void
    {
        $woman = $this->member('female', ['track_cycle' => false]);
        Livewire::actingAs($woman)->test(Profile::class)->call('toggleCycle');
        $this->assertTrue((bool) CoachProfile::where('user_id', $woman->id)->value('track_cycle'));

        $man = $this->member('male', ['track_cycle' => false]);
        $this->actingAs($man)->get(route('coach.profile'))->assertOk()->assertDontSee('data-section="cycle"', false);
        app(ProfileSettings::class)->setTrackCycle($man, true);
        $this->assertFalse((bool) CoachProfile::where('user_id', $man->id)->value('track_cycle'));
    }

    public function test_lb_is_ignored_while_the_switch_is_off(): void
    {
        $user = $this->member();
        app(ProfileSettings::class)->setUnits($user, 'lb');

        $this->assertSame('kg', CoachProfile::where('user_id', $user->id)->value('units'));
    }

    public function test_one_member_cannot_change_another_members_profile(): void
    {
        $a = $this->member();
        $b = $this->member();

        Livewire::actingAs($a)->test(Profile::class)->call('setDiet', 'vegan');

        $this->assertSame('vegan', CoachProfile::where('user_id', $a->id)->value('diet'));
        $this->assertNull(CoachProfile::where('user_id', $b->id)->value('diet'));
    }

    public function test_milestone_numbers(): void
    {
        $user = $this->member('female', ['start_weight_kg' => 100.0]);
        $this->weigh($user, '2026-11-01', 100.0);
        $this->weigh($user, '2026-11-24', 92.5);
        WaterEntry::create(['user_id' => $user->id, 'logged_at' => now(), 'ml' => 250]);
        WaterEntry::create(['user_id' => $user->id, 'logged_at' => now()->addHour(), 'ml' => 250]); // same day
        DoseEntry::create(['user_id' => $user->id, 'taken_at' => now()->subDays(2), 'site' => 'Left thigh', 'pain' => 1]);

        $coachId = CoachingAssignment::where('patient_id', $user->id)->value('coach_id');
        CoachCall::create(['patient_id' => $user->id, 'coach_id' => $coachId, 'starts_at' => now()->subDays(3), 'length_minutes' => 15, 'status' => CoachCall::BOOKED, 'slot_key' => 'a']);
        CoachCall::create(['patient_id' => $user->id, 'coach_id' => $coachId, 'starts_at' => now()->subDays(9), 'length_minutes' => 15, 'status' => CoachCall::COMPLETED, 'slot_key' => 'b']);
        CoachCall::create(['patient_id' => $user->id, 'coach_id' => $coachId, 'starts_at' => now()->subDays(5), 'length_minutes' => 15, 'status' => CoachCall::CANCELLED, 'slot_key' => 'c']);
        CoachCall::create(['patient_id' => $user->id, 'coach_id' => $coachId, 'starts_at' => now()->addDays(2), 'length_minutes' => 15, 'status' => CoachCall::BOOKED, 'slot_key' => 'd']);

        $s = MilestoneStats::forUser($user);

        $this->assertSame(7.5, $s->percentLost);
        $this->assertSame(7.5, $s->kgLost);
        $this->assertSame(3, $s->daysLogged); // 1 Nov (weight), 22 Nov (dose), 24 Nov (weight + 2 water, one day)
        $this->assertSame(2, $s->checkIns);   // completed + past booked; not cancelled, not future
    }

    public function test_milestone_page_shows_stats_and_maintenance_button(): void
    {
        $user = $this->member('female', ['start_weight_kg' => 80.0]);
        $this->weigh($user, '2026-11-24', 76.0);

        $this->actingAs($user)->get(route('coach.milestone'))
            ->assertOk()
            ->assertSee('5%')
            ->assertSee('4.0 kg')
            ->assertSee('A note from')
            ->assertSee('Book a maintenance call')
            ->assertSee('for=maintenance', false);
    }

    public function test_milestone_with_no_loss_is_encouraging_not_negative(): void
    {
        $user = $this->member('female', ['start_weight_kg' => 80.0]);
        $this->weigh($user, '2026-11-24', 81.0); // gained

        Livewire::actingAs($user)->test(Milestone::class)
            ->assertSee('Your first milestone is on its way')
            ->assertDontSee('data-percent', false);

        $stats = MilestoneStats::forUser($user);
        $this->assertFalse($stats->hasProgress());
        $this->assertSame(0.0, $stats->kgLost);
    }

    public function test_milestone_with_no_data_does_not_crash(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get(route('coach.milestone'))->assertOk()->assertSee('days logged');
    }

    public function test_service_rejects_bad_input_directly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ProfileSettings::class)->setActivity($this->member(), 'astronaut');
    }
}
