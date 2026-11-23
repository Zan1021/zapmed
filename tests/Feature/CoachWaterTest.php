<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\Today;
use App\Livewire\Coach\Water;
use App\Models\CoachingAssignment;
use App\Models\CoachProfile;
use App\Models\PatientProfile;
use App\Models\User;
use App\Models\WaterEntry;
use App\Services\Coach\WaterLogger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach App 1.6: the water screen (drop, quick adds, exact amount, last entries, undo, luteal tip).
 */
class CoachWaterTest extends TestCase
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

    private function logger(): WaterLogger
    {
        return app(WaterLogger::class);
    }

    // ---- WaterLogger --------------------------------------------------------------------------

    public function test_amounts_are_added_and_totalled_for_today(): void
    {
        $u = $this->member();

        $this->logger()->add($u, 250);
        $this->logger()->add($u, 330);

        $this->assertSame(580, $this->logger()->todayTotal($u));
        $this->assertSame(2, WaterEntry::where('user_id', $u->id)->count());
    }

    public function test_amounts_outside_the_allowed_range_are_rejected(): void
    {
        $u = $this->member();

        foreach ([0, -250, 9, 3001, 99999] as $bad) {
            try {
                $this->logger()->add($u, $bad);
                $this->fail("{$bad} should be rejected");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->logger()->add($u, 10);    // the floor is allowed
        $this->logger()->add($u, 3000);  // and so is the ceiling
        $this->assertSame(3010, $this->logger()->todayTotal($u));
    }

    public function test_today_is_the_south_african_day(): void
    {
        $u = $this->member();
        WaterEntry::create(['user_id' => $u->id, 'logged_at' => '2026-11-23 22:30:00', 'ml' => 500]);   // 00:30 SAST today
        WaterEntry::create(['user_id' => $u->id, 'logged_at' => '2026-11-23 21:30:00', 'ml' => 1000]);  // 23:30 SAST yesterday

        $this->assertSame(500, $this->logger()->todayTotal($u));
    }

    public function test_undo_removes_the_latest_entry_of_today_and_never_yesterdays(): void
    {
        $u = $this->member();
        WaterEntry::create(['user_id' => $u->id, 'logged_at' => '2026-11-23 21:30:00', 'ml' => 1000]); // yesterday SAST
        $this->assertFalse($this->logger()->undoLast($u), 'Nothing today to undo');
        $this->assertSame(1, WaterEntry::count(), "Yesterday's entry is untouched");

        $this->logger()->add($u, 250);
        $this->travel(5)->minutes();
        $this->logger()->add($u, 500);

        $this->assertTrue($this->logger()->undoLast($u));
        $this->assertSame(250, $this->logger()->todayTotal($u));
        $this->assertTrue($this->logger()->undoLast($u));
        $this->assertFalse($this->logger()->undoLast($u));
        $this->assertSame(1, WaterEntry::count());
    }

    public function test_one_members_water_never_touches_another(): void
    {
        $a = $this->member();
        $b = $this->member();
        $this->logger()->add($a, 500);

        $this->assertSame(0, $this->logger()->todayTotal($b));
        $this->assertFalse($this->logger()->undoLast($b));
        $this->assertSame(1, WaterEntry::where('user_id', $a->id)->count());
    }

    // ---- the screen ---------------------------------------------------------------------------

    public function test_empty_screen_shows_zero_and_nothing_to_undo(): void
    {
        Livewire::actingAs($this->member())->test(Water::class)
            ->assertSee('0 mL')
            ->assertSee('of 2,200 mL goal')
            ->assertSee('Nothing logged yet.')
            ->assertSeeHtml('data-drop-y="144.0"')
            ->assertSeeHtml('wire:click="undo" disabled');
    }

    public function test_four_quick_add_buttons_with_the_guide_amounts(): void
    {
        Livewire::actingAs($this->member())->test(Water::class)
            ->assertSee('Glass')->assertSee('250 mL')
            ->assertSee('Bottle')->assertSee('350 mL')
            ->assertSee('Large')->assertSee('500 mL')
            ->assertSee('Flask')->assertSee('1,000 mL');
    }

    public function test_quick_add_updates_the_total_and_the_drop(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(Water::class)
            ->call('add', 1000)
            ->set('exact', '100')->call('addExact')
            ->assertSee('1,100 mL')
            ->assertSeeHtml('data-drop-y="76.0"'); // 1,100 of 2,200 = half full: 144 - 0.5 x 136

        $this->assertSame(1100, $this->logger()->todayTotal($u));
    }

    public function test_the_quick_add_buttons_only_accept_the_four_offered_amounts(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(Water::class)->call('add', 100)->call('add', 999)->call('add', -250);

        $this->assertSame(0, WaterEntry::count());
    }
    public function test_exact_amount_accepts_whole_numbers_in_range_and_clears_the_box(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(Water::class)
            ->set('exact', '330')->call('addExact')
            ->assertSet('exact', '')->assertSet('error', null)->assertSee('330 mL');

        $this->assertSame(330, $this->logger()->todayTotal($u));
    }

    public function test_exact_amount_rejects_bad_input_with_a_message_and_saves_nothing(): void
    {
        $u = $this->member();
        $c = Livewire::actingAs($u)->test(Water::class);

        foreach (['', 'abc', '12.5', '-50', '5 0'] as $bad) {
            $c->set('exact', $bad)->call('addExact')->assertSee('Enter a whole number of mL');
        }
        foreach (['5', '5000'] as $bad) {
            $c->set('exact', $bad)->call('addExact')->assertSee('between 10 and 3000 mL');
        }

        $this->assertSame(0, WaterEntry::count());
    }

    public function test_the_log_shows_the_last_five_entries_newest_first_in_south_african_time(): void
    {
        $u = $this->member();
        foreach ([111, 222, 333, 444, 555, 666, 777] as $ml) {
            $this->logger()->add($u, $ml);
            $this->travel(1)->minutes();
        }

        $c = Livewire::actingAs($u)->test(Water::class);
        $c->assertSeeInOrder(['777 mL', '666 mL', '555 mL', '444 mL', '333 mL'])
            ->assertDontSee('222 mL')->assertDontSee('111 mL')
            ->assertSee('09:06'); // 777 logged 6 minutes after 09:00 SAST (the stored UTC time is shown as local)
    }

    public function test_undo_button_removes_the_last_entry_and_updates_everything(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(Water::class)
            ->call('add', 250)->call('add', 500)
            ->assertSee('750 mL')
            ->call('undo')
            ->assertSee('250 mL')->assertDontSee('750 mL')
            ->call('undo')
            ->assertSee('Nothing logged yet.');

        $this->assertSame(0, WaterEntry::count());
    }

    public function test_reaching_the_goal_is_celebrated_and_going_over_is_fine(): void
    {
        $u = $this->member();
        $c = Livewire::actingAs($u)->test(Water::class);

        $c->call('add', 1000)->call('add', 1000)->assertDontSee('Goal reached');
        $c->call('add', 500)->assertSee('Goal reached. Well done.')->assertSee('2,500 mL')->assertSeeHtml('data-drop-y="8.0"');
    }

    public function test_the_luteal_tip_shows_only_for_a_tracking_member_in_her_luteal_phase(): void
    {
        $on = $this->member(['track_cycle' => true, 'last_period_start' => '2026-11-04']); // day 21
        Livewire::actingAs($on)->test(Water::class)->assertSee('drinking enough water helps with water retention');

        $off = $this->member(['track_cycle' => false, 'last_period_start' => '2026-11-04']);
        Livewire::actingAs($off)->test(Water::class)->assertDontSee('water retention');

        $follicular = $this->member(['track_cycle' => true, 'last_period_start' => '2026-11-18']); // day 7
        Livewire::actingAs($follicular)->test(Water::class)->assertDontSee('water retention');
    }

    // ---- links + access -----------------------------------------------------------------------

    public function test_today_water_button_still_works_and_the_card_links_to_the_water_screen(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(Today::class)
            ->call('addWater', 250)
            ->assertSeeHtml('250</b> / 2,200 mL')
            ->call('addWater', 123)
            ->assertSeeHtml('250</b> / 2,200 mL');

        $this->actingAs($u)->get('/coach')->assertOk()->assertSee(route('coach.water'), false);
    }

    public function test_water_page_needs_the_add_on_and_keeps_the_track_tab_on(): void
    {
        $plain = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $plain->id, 'onboarding_complete' => true]);
        $this->actingAs($plain)->get('/coach/water')->assertRedirect('/weight-loss');

        $html = $this->actingAs($this->member())->get('/coach/water')->assertOk()->assertSee('Water')->getContent();
        $this->assertMatchesRegularExpression('/zc-tab on[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*Track/s', $html);
        foreach (['ozempic', 'wegovy', 'mounjaro', 'semaglutide', 'tirzepatide'] as $name) {
            $this->assertStringNotContainsStringIgnoringCase($name, $html);
        }
    }
}
