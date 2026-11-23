<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\LogDose;
use App\Livewire\Coach\Medication;
use App\Models\CoachingAssignment;
use App\Models\CoachProfile;
use App\Models\DoseEntry;
use App\Models\PatientProfile;
use App\Models\ReminderSetting;
use App\Models\User;
use App\Services\Coach\DoseLogger;
use App\Services\Coach\MedicationChart;
use App\Services\Coach\MedicationLevel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach App 1.4: medication tracker + log dose (sites, rotation star, pain, estimate-only curve).
 */
class CoachMedicationTest extends TestCase
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

    private function dose(User $u, $when, string $site = 'Left thigh', int $pain = 2): DoseEntry
    {
        return DoseEntry::create(['user_id' => $u->id, 'taken_at' => $when, 'site' => $site, 'pain' => $pain]);
    }

    // ---- DoseLogger ---------------------------------------------------------------------------

    public function test_a_valid_dose_is_saved_with_site_and_pain(): void
    {
        $u = $this->member();

        app(DoseLogger::class)->log($u, 'Right upper arm', 4);

        $d = DoseEntry::where('user_id', $u->id)->firstOrFail();
        $this->assertSame('Right upper arm', $d->site);
        $this->assertSame(4, $d->pain);
    }

    public function test_unknown_sites_and_out_of_range_pain_are_rejected(): void
    {
        $u = $this->member();
        $logger = app(DoseLogger::class);

        foreach ([['Left ear', 2], ['', 2], ['Left thigh', -1], ['Left thigh', 11]] as [$site, $pain]) {
            try {
                $logger->log($u, $site, $pain);
                $this->fail("{$site}/{$pain} should be rejected");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, DoseEntry::count());
    }

    public function test_logging_again_the_same_local_day_corrects_todays_dose(): void
    {
        $u = $this->member();
        $logger = app(DoseLogger::class);

        $logger->log($u, 'Left thigh', 2);
        $logger->log($u, 'Right thigh', 5);

        $this->assertSame(1, DoseEntry::where('user_id', $u->id)->count());
        $d = DoseEntry::where('user_id', $u->id)->first();
        $this->assertSame('Right thigh', $d->site);
        $this->assertSame(5, $d->pain);
    }

    public function test_the_local_day_boundary_is_south_african_time(): void
    {
        $u = $this->member();
        // 23:30 SAST yesterday is 21:30 UTC yesterday. Today (SAST) already started, so this is a different day.
        $this->dose($u, '2026-11-23 21:30:00');

        app(DoseLogger::class)->log($u, 'Right thigh', 1);

        $this->assertSame(2, DoseEntry::where('user_id', $u->id)->count());
    }

    public function test_next_site_follows_the_rotation_and_wraps(): void
    {
        $u = $this->member();
        $logger = app(DoseLogger::class);

        $this->assertNull($logger->nextSite($u), 'No star before the first dose');

        $this->dose($u, now()->subDays(14), 'Left upper arm');
        $this->assertSame('Right upper arm', $logger->nextSite($u));

        $this->dose($u, now()->subDays(7), 'Right thigh'); // last in the list
        $this->assertSame('Left upper arm', $logger->nextSite($u));
    }

    // ---- chart --------------------------------------------------------------------------------

    public function test_chart_is_empty_until_a_dose_exists_then_has_past_and_future_lines(): void
    {
        $u = $this->member();
        $this->assertFalse(MedicationChart::build(MedicationLevel::forUser($u))['hasData']);

        $this->dose($u, now()->subDays(3));
        $c = MedicationChart::build(MedicationLevel::forUser($u));

        $this->assertTrue($c['hasData']);
        $this->assertNotNull($c['past']);
        $this->assertNotNull($c['future']);
        $this->assertGreaterThan(15, $c['todayX']);
        $this->assertLessThan(315, $c['todayX']);
    }

    // ---- Medication screen --------------------------------------------------------------------

    public function test_medication_screen_empty_state(): void
    {
        Livewire::actingAs($this->member())->test(Medication::class)
            ->assertSee('Nothing logged yet')
            ->assertSee('Log my first dose')
            ->assertSee('We never advise changing your dose')
            ->assertDontSee('Estimate only');
    }

    public function test_medication_screen_shows_next_and_last_dose_estimate_and_history(): void
    {
        $u = $this->member();
        $this->dose($u, now()->subDays(10), 'Left upper arm', 3);
        $this->dose($u, now()->subDays(3), 'Right thigh', 1);

        Livewire::actingAs($u)->test(Medication::class)
            ->assertSee('Estimate only')
            ->assertSee('estimated level today')
            ->assertSee('Next dose')
            ->assertSee('Sat 28 Nov')           // 7 days after Sat 21 Nov
            ->assertSee('4 days to go')
            ->assertSee('Sat 21 Nov')
            ->assertSee('Right thigh')
            ->assertSee('Pain 3/10')
            ->assertSee('Pain 1/10')
            ->assertSee('It is not a blood test')
            ->assertSee('Appetite is usually lowest in the first two days after a dose.');
    }

    public function test_overdue_and_due_today_are_stated_plainly_without_advice(): void
    {
        $u = $this->member();
        $this->dose($u, now()->subDays(9), 'Left thigh'); // next was due 2 days ago

        Livewire::actingAs($u)->test(Medication::class)->assertSee('Was due 2 days ago')->assertDontSee('should', false);

        DoseEntry::query()->delete();
        $this->dose($u, now()->subDays(7), 'Left thigh');

        Livewire::actingAs($u)->test(Medication::class)->assertSee('Today');
    }

    public function test_luteal_tip_only_shows_for_a_tracking_member_in_her_luteal_phase(): void
    {
        $on = $this->member(['track_cycle' => true, 'last_period_start' => '2026-11-04']); // day 21
        $this->dose($on, now()->subDays(3));
        Livewire::actingAs($on)->test(Medication::class)->assertSee('In your luteal phase, nausea or cravings can feel stronger.');

        $off = $this->member(['track_cycle' => false, 'last_period_start' => '2026-11-04']);
        $this->dose($off, now()->subDays(3));
        Livewire::actingAs($off)->test(Medication::class)->assertDontSee('luteal');
    }

    public function test_dose_reminder_row_reflects_the_saved_setting(): void
    {
        $u = $this->member();
        ReminderSetting::create(['user_id' => $u->id, 'type' => 'dose_day', 'enabled' => true, 'settings' => ['time' => '18:00']]);
        Livewire::actingAs($u)->test(Medication::class)->assertSee('Evening before, 18:00');

        ReminderSetting::where('user_id', $u->id)->update(['enabled' => false]);
        Livewire::actingAs($u)->test(Medication::class)->assertSee('Off');
    }

    // ---- Log dose screen ----------------------------------------------------------------------

    public function test_log_dose_needs_a_site_before_saving(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(LogDose::class)
            ->assertSet('pain', 0)
            ->call('save')
            ->assertSee('Please choose where you injected.')
            ->assertNoRedirect();

        $this->assertSame(0, DoseEntry::count());
    }

    public function test_log_dose_ignores_unknown_sites_and_saves_a_real_choice(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(LogDose::class)
            ->call('chooseSite', 'Left ear')->assertSet('site', null)
            ->call('chooseSite', 'Left stomach')->assertSet('site', 'Left stomach')
            ->set('pain', 7)
            ->call('save')
            ->assertRedirect(route('coach.medication'));

        $d = DoseEntry::where('user_id', $u->id)->firstOrFail();
        $this->assertSame('Left stomach', $d->site);
        $this->assertSame(7, $d->pain);
    }

    public function test_the_star_marks_only_the_next_site_in_rotation(): void
    {
        $u = $this->member();
        $this->dose($u, now()->subDays(7), 'Right upper arm');

        $html = Livewire::actingAs($u)->test(LogDose::class)->html();

        $this->assertSame(1, substr_count($html, 'Next in rotation'));
        // Each site's button text sits between its data-site attribute and the next one. Only Left stomach has the star.
        $parts = preg_split('/data-site="/', $html);
        array_shift($parts);
        foreach ($parts as $part) {
            $site = strstr($part, '"', true);
            $this->assertSame($site === 'Left stomach', str_contains($part, 'Next in rotation'), "star on {$site}");
        }
    }

    public function test_no_star_before_the_first_dose_and_the_screen_never_advises_a_dose_change(): void
    {
        $html = Livewire::actingAs($this->member())->test(LogDose::class)
            ->assertSee('We never advise changing your dose')
            ->html();

        $this->assertStringNotContainsString('Next in rotation', $html);
        $this->assertSame(6, substr_count($html, 'data-site='));
    }

    // ---- access + links -----------------------------------------------------------------------

    public function test_pages_need_the_add_on_and_today_links_to_the_medication_screen(): void
    {
        $plain = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $plain->id, 'onboarding_complete' => true]);
        $this->actingAs($plain)->get('/coach/medication')->assertRedirect('/weight-loss');
        $this->actingAs($plain)->get('/coach/medication/log')->assertRedirect('/weight-loss');

        $member = $this->member();
        $this->actingAs($member)->get('/coach/medication')->assertOk();
        $this->actingAs($member)->get('/coach/medication/log')->assertOk()->assertSee('Save dose');
        $this->actingAs($member)->get('/coach')->assertOk()->assertSee(route('coach.medication'), false);
    }

    public function test_medication_pages_never_name_a_medicine_and_the_track_tab_stays_on(): void
    {
        $u = $this->member();
        $this->dose($u, now()->subDays(3));

        foreach (['/coach/medication', '/coach/medication/log'] as $url) {
            $html = $this->actingAs($u)->get($url)->assertOk()->getContent();
            foreach (['ozempic', 'wegovy', 'mounjaro', 'saxenda', 'semaglutide', 'tirzepatide', 'liraglutide'] as $name) {
                $this->assertStringNotContainsStringIgnoringCase($name, $html);
            }
        }

        $html = $this->actingAs($u)->get('/coach/medication')->getContent();
        $this->assertMatchesRegularExpression('/zc-tab on[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*Track/s', $html);
    }
}
