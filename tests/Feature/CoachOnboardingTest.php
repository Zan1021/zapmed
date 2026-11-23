<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\Onboarding;
use App\Models\CoachProfile;
use App\Models\CoachingAssignment;
use App\Models\PatientProfile;
use App\Models\ReminderSetting;
use App\Models\User;
use App\Services\Coach\CoachOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach App 1.1: worry question, plan reveal, cycle switch (design screens 1 and 2).
 */
class CoachOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function member(?string $gender = 'female', array $profile = []): User
    {
        $user = User::factory()->create(['role' => UserRole::Patient, 'gender' => $gender]);
        PatientProfile::create(array_merge(['user_id' => $user->id, 'onboarding_complete' => true], $profile));

        // Coach access through an active assignment.
        CoachingAssignment::create([
            'patient_id' => $user->id,
            'coach_id' => User::factory()->create(['role' => UserRole::HealthCoach])->id,
            'started_at' => now(),
        ]);

        return $user;
    }

    public function test_new_member_is_sent_to_onboarding_and_onboarded_member_is_sent_home(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get('/coach')->assertRedirect('/coach/start');
        $this->actingAs($user)->get('/coach/start')->assertOk()->assertSee('What worries you most about starting?');

        CoachProfile::create(['user_id' => $user->id, 'onboarded_at' => now()]);

        $this->actingAs($user)->get('/coach/start')->assertRedirect('/coach');
        $this->actingAs($user)->get('/coach')->assertOk();
    }

    public function test_cannot_continue_without_choosing_a_worry(): void
    {
        Livewire::actingAs($this->member())->test(Onboarding::class)
            ->call('next')
            ->assertSet('step', 1)
            ->assertSee('Tell me what');
    }

    public function test_amys_reply_changes_with_the_choice_and_only_one_can_be_picked(): void
    {
        $c = Livewire::actingAs($this->member())->test(Onboarding::class);

        $c->call('chooseWorry', 'cost')->assertSet('worry', 'cost')->assertSee('affordable');
        $c->call('chooseWorry', 'motivation')->assertSet('worry', 'motivation')->assertSee('in your corner')->assertDontSee('affordable');
        $c->call('chooseWorry', 'not-a-real-choice')->assertSet('worry', 'motivation');
    }

    public function test_plan_reveal_shows_targets_and_finishing_saves_the_profile(): void
    {
        $user = $this->member('female', ['height_cm' => 168.0, 'weight_kg' => 102.0]);

        Livewire::actingAs($user)->test(Onboarding::class)
            ->call('chooseWorry', 'what_to_eat')
            ->call('next')
            ->assertSet('step', 2)
            ->assertSee('92 g')
            ->assertSee('28 g')
            ->assertSee('2.2 L')
            ->assertSee('Track my cycle')
            ->call('finish')
            ->assertRedirect(route('coach.today'));

        $profile = CoachProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('what_to_eat', $profile->worry);
        $this->assertTrue($profile->track_cycle, 'Cycle tracker defaults on for women.');
        $this->assertNotNull($profile->onboarded_at);
        $this->assertSame(168, $profile->height_cm);
        $this->assertEquals(102.0, (float) $profile->start_weight_kg);
    }

    public function test_woman_can_switch_the_cycle_tracker_off(): void
    {
        $user = $this->member('female');

        Livewire::actingAs($user)->test(Onboarding::class)
            ->assertSet('trackCycle', true)
            ->call('chooseWorry', 'side_effects')->call('next')
            ->call('toggleCycle')->assertSet('trackCycle', false)
            ->call('finish');

        $this->assertFalse(CoachProfile::where('user_id', $user->id)->value('track_cycle'));
    }

    public function test_men_never_get_the_cycle_tracker_and_the_switch_is_hidden(): void
    {
        $user = $this->member('male');

        Livewire::actingAs($user)->test(Onboarding::class)
            ->assertSet('trackCycle', false)
            ->call('chooseWorry', 'cost')->call('next')
            ->assertDontSee('Track my cycle')
            ->set('trackCycle', true) // tampering
            ->call('finish');

        $this->assertFalse(CoachProfile::where('user_id', $user->id)->value('track_cycle'));
    }

    public function test_unknown_gender_defaults_off_but_shows_the_switch(): void
    {
        $user = $this->member(null);

        Livewire::actingAs($user)->test(Onboarding::class)
            ->assertSet('trackCycle', false)
            ->call('chooseWorry', 'cost')->call('next')
            ->assertSee('Track my cycle');
    }

    public function test_default_reminders_are_created_once(): void
    {
        $user = $this->member();

        Livewire::actingAs($user)->test(Onboarding::class)
            ->call('chooseWorry', 'motivation')->call('next')->call('finish');

        $this->assertSame(5, ReminderSetting::where('user_id', $user->id)->count());
        $hydration = ReminderSetting::where('user_id', $user->id)->where('type', 'hydration')->firstOrFail();
        $this->assertTrue($hydration->enabled);
        $this->assertSame(2, $hydration->settings['every_hours']);

        // Completing again keeps the original timestamp and does not duplicate reminders.
        $first = CoachProfile::where('user_id', $user->id)->value('onboarded_at');
        app(CoachOnboarding::class)->complete($user, 'cost', true);
        $this->assertSame(5, ReminderSetting::where('user_id', $user->id)->count());
        $this->assertEquals($first, CoachProfile::where('user_id', $user->id)->value('onboarded_at'));
    }

    public function test_service_rejects_an_unknown_worry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(CoachOnboarding::class)->complete($this->member(), 'nope', false);
    }

    public function test_onboarding_screens_never_name_a_medicine_and_hide_the_tab_bar(): void
    {
        $html = $this->actingAs($this->member())->get('/coach/start')->assertOk()->getContent();

        foreach (['ozempic', 'wegovy', 'mounjaro', 'saxenda', 'semaglutide', 'tirzepatide', 'liraglutide'] as $name) {
            $this->assertStringNotContainsStringIgnoringCase($name, $html);
        }
        $this->assertStringNotContainsString('zc-tabs', $html, 'No tab bar during onboarding.');
    }
}
