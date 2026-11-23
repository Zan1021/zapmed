<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\FlagsInbox;
use App\Livewire\Coach\LogSideEffect;
use App\Models\Alert;
use App\Models\Appointment;
use App\Models\CoachFlag;
use App\Models\CoachingAssignment;
use App\Models\CoachProfile;
use App\Models\CustomSymptom;
use App\Models\NotificationLog;
use App\Models\PatientProfile;
use App\Models\SideEffectEntry;
use App\Models\User;
use App\Services\Coach\CoachAccess;
use App\Services\Coach\CoachEscalation;
use App\Services\Coach\SideEffectLogger;
use App\Services\Notifications\ChannelResult;
use App\Services\Notifications\Contracts\NotificationChannel;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach App 1.5: side effects and the doctor flag. This is the safety-critical part of the app, so it is tested hard:
 * the levels, that a flag is never lost, that messages carry no health detail, and who may see a flag.
 */
class CoachSideEffectTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int,array{channel:string,message:OutboundMessage}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-24 09:00', 'Africa/Johannesburg'));
        $this->sent = [];

        // Capture what staff would be sent instead of really sending it.
        $dispatcher = app(NotificationDispatcher::class);
        foreach (['email', 'sms'] as $key) {
            $dispatcher->register($this->makeSpy($key));
        }
        $this->app->instance(NotificationDispatcher::class, $dispatcher);
    }

    private function makeSpy(string $key): NotificationChannel
    {
        $test = $this;

        return new class($key, $test) implements NotificationChannel {
            public function __construct(private string $k, private $t) {}

            public function key(): string { return $this->k; }

            public function isConfigured(): bool { return true; }

            public function send(OutboundMessage $message): ChannelResult
            {
                $this->t->record($this->k, $message);

                return ChannelResult::sent('spy');
            }
        };
    }

    public function record(string $channel, OutboundMessage $message): void
    {
        $this->sent[] = ['channel' => $channel, 'message' => $message];
    }

    private function member(array $coach = [], bool $withCoach = true): User
    {
        $user = User::factory()->create(['role' => UserRole::Patient, 'gender' => 'female']);
        PatientProfile::create(['user_id' => $user->id, 'onboarding_complete' => true]);
        CoachProfile::create(array_merge(['user_id' => $user->id, 'onboarded_at' => now()], $coach));

        if ($withCoach) {
            CoachingAssignment::create([
                'patient_id' => $user->id,
                'coach_id' => User::factory()->create(['role' => UserRole::HealthCoach])->id,
                'started_at' => now(),
            ]);
        }

        return $user;
    }

    private function logger(): SideEffectLogger
    {
        return app(SideEffectLogger::class);
    }

    // ---- flag levels --------------------------------------------------------------------------

    public function test_flag_levels_are_abdominal_pain_7_or_any_symptom_9(): void
    {
        $this->assertFalse(CoachEscalation::isFlagLevel('Abdominal pain', 6));
        $this->assertTrue(CoachEscalation::isFlagLevel('Abdominal pain', 7));
        $this->assertTrue(CoachEscalation::isFlagLevel('Abdominal pain', 10));
        $this->assertFalse(CoachEscalation::isFlagLevel('Nausea', 8));
        $this->assertFalse(CoachEscalation::isFlagLevel('Nausea', 7));
        $this->assertTrue(CoachEscalation::isFlagLevel('Nausea', 9));
        $this->assertTrue(CoachEscalation::isFlagLevel('My own symptom', 10));
        $this->assertFalse(CoachEscalation::isFlagLevel('My own symptom', 8));
    }

    // ---- symptom list -------------------------------------------------------------------------

    public function test_a_new_member_sees_the_nine_default_symptoms(): void
    {
        $this->assertSame(config('coach.default_symptoms'), $this->logger()->visible($this->member()));
        $this->assertCount(9, $this->logger()->visible($this->member()));
    }

    public function test_hidden_defaults_can_be_restored_and_custom_symptoms_stay_for_next_time(): void
    {
        $u = $this->member();
        $l = $this->logger();

        $l->hide($u, 'Hair loss');
        $l->addCustom($u, '  Dizzy   spells ');

        $visible = $l->visible($u);
        $this->assertNotContains('Hair loss', $visible);
        $this->assertSame('Dizzy spells', end($visible), 'Custom symptoms come after the defaults, tidied up');
        $this->assertSame(1, $l->hiddenCount($u));

        $l->restoreHidden($u);
        $this->assertContains('Hair loss', $l->visible($u));
        $this->assertContains('Dizzy spells', $l->visible($u), 'Custom symptom is kept');

        $l->hide($u, 'Dizzy spells'); // removing your own symptom deletes it
        $this->assertNotContains('Dizzy spells', $l->visible($u));
        $this->assertSame(0, $l->hiddenCount($u));
    }

    public function test_adding_a_duplicate_does_nothing_and_a_hidden_default_typed_again_comes_back(): void
    {
        $u = $this->member();
        $l = $this->logger();

        $l->addCustom($u, 'nausea');
        $this->assertSame(9, count($l->visible($u)));

        $l->hide($u, 'Nausea');
        $l->addCustom($u, 'NAUSEA');
        $this->assertContains('Nausea', $l->visible($u));
        $this->assertSame(0, CustomSymptom::where('user_id', $u->id)->count());
    }

    public function test_blank_or_too_long_symptom_names_are_rejected(): void
    {
        foreach (['', '   ', str_repeat('x', 61)] as $bad) {
            try {
                $this->logger()->addCustom($this->member(), $bad);
                $this->fail('Should reject');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- saving -------------------------------------------------------------------------------

    public function test_only_scores_above_zero_are_stored_with_todays_south_african_date(): void
    {
        $u = $this->member();

        $r = $this->logger()->save($u, ['Nausea' => 3, 'Fatigue' => 0, 'Reflux' => '2']);

        $this->assertSame(2, $r['saved']);
        $this->assertSame([], $r['flagged']);
        $this->assertEqualsCanonicalizing(['Nausea', 'Reflux'], SideEffectEntry::where('user_id', $u->id)->pluck('symptom')->all());
        $this->assertSame('2026-11-24', SideEffectEntry::where('user_id', $u->id)->first()->logged_on->toDateString());
        $this->assertSame(0, CoachFlag::count());
    }

    public function test_bad_input_is_rejected_and_nothing_is_saved(): void
    {
        $u = $this->member();

        foreach ([[], ['Nausea' => 0], ['Nausea' => 11], ['Nausea' => -1], ['Nausea' => 2.5], ['Nausea' => 'abc'], ['Made up' => 5]] as $bad) {
            try {
                $this->logger()->save($u, $bad);
                $this->fail('Should reject '.json_encode($bad));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, SideEffectEntry::count());
    }

    // ---- the doctor flag ----------------------------------------------------------------------

    public function test_abdominal_pain_7_raises_a_flag_a_board_alert_and_tells_the_coach(): void
    {
        $u = $this->member();

        $r = $this->logger()->save($u, ['Abdominal pain' => 7, 'Nausea' => 2]);

        $this->assertSame(['Abdominal pain' => 7], $r['flagged']);
        $flag = CoachFlag::firstOrFail();
        $this->assertSame('open', $flag->status);
        $this->assertSame('side_effect', $flag->source);
        $this->assertSame('Abdominal pain', $flag->symptom);
        $this->assertSame(7, $flag->score);
        $this->assertNotNull($flag->notified_at);
        $this->assertTrue(SideEffectEntry::where('symptom', 'Abdominal pain')->value('flagged'));
        $this->assertFalse(SideEffectEntry::where('symptom', 'Nausea')->value('flagged'));

        $alert = Alert::where('definition_code', 'coach.safety_flag')->firstOrFail();
        $this->assertSame('critical', $alert->severity->value);
        $this->assertNotEmpty($this->sent, 'Staff must be told straight away');
    }

    public function test_just_below_the_levels_raises_nothing(): void
    {
        $u = $this->member();

        $this->logger()->save($u, ['Abdominal pain' => 6, 'Nausea' => 8, 'Reflux' => 8]);

        $this->assertSame(0, CoachFlag::count());
        $this->assertSame(0, Alert::count());
        $this->assertSame([], $this->sent);
    }

    public function test_any_symptom_at_9_and_a_members_own_symptom_both_flag(): void
    {
        $u = $this->member();
        $this->logger()->addCustom($u, 'Dizzy spells');

        $r = $this->logger()->save($u, ['Fatigue' => 9, 'Dizzy spells' => 10]);

        $this->assertEqualsCanonicalizing(['Fatigue', 'Dizzy spells'], array_keys($r['flagged']));
        $this->assertSame(2, CoachFlag::count());
    }

    public function test_messages_to_staff_carry_no_health_detail(): void
    {
        $u = $this->member();
        $u->update(['member_number' => 'ZM-000123']);

        $this->logger()->save($u, ['Abdominal pain' => 9]);

        $this->assertNotEmpty($this->sent);
        foreach ($this->sent as $s) {
            $text = ($s['message']->subject ?? '').' '.($s['message']->body ?? '');
            $this->assertStringContainsString('ZM-000123', $text);
            foreach (['Abdominal', 'abdominal', 'pain', '9/10', 'nausea', 'side effect'] as $leak) {
                $this->assertStringNotContainsString($leak, $text, "Message leaks '{$leak}'");
            }
        }
    }

    public function test_the_named_doctor_and_the_assigned_coach_are_both_told(): void
    {
        $anke = User::factory()->create(['role' => UserRole::Doctor, 'email' => 'anke@zapmed.test', 'phone' => '0821110000']);
        config(['coach.flags.notify_emails' => ['anke@zapmed.test']]);
        $u = $this->member();
        $coachEmail = User::find(CoachingAssignment::where('patient_id', $u->id)->value('coach_id'))->email;

        $this->logger()->save($u, ['Abdominal pain' => 8]);

        $told = collect($this->sent)->map(fn ($s) => $s['message']->user->email)->unique()->all();
        $this->assertEqualsCanonicalizing(['anke@zapmed.test', $coachEmail], $told);
        $channels = collect($this->sent)->pluck('channel')->unique()->all();
        $this->assertEqualsCanonicalizing(['email', 'sms'], $channels);
    }

    public function test_when_nobody_is_configured_and_there_is_no_coach_active_admins_are_told(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $u = $this->member([], withCoach: false);

        $this->logger()->save($u, ['Abdominal pain' => 7]);

        $this->assertSame([$admin->email], collect($this->sent)->map(fn ($s) => $s['message']->user->email)->unique()->values()->all());
    }

    public function test_a_flag_is_saved_even_if_nobody_can_be_told_or_sending_blows_up(): void
    {
        $u = $this->member([], withCoach: false); // no coach, no admins, no configured doctor

        $this->logger()->save($u, ['Abdominal pain' => 7]);

        $flag = CoachFlag::firstOrFail();
        $this->assertSame('open', $flag->status);
        $this->assertNull($flag->notified_at);

        // A channel that throws must not stop the flag or the save.
        $boom = new class implements NotificationChannel {
            public function key(): string { return 'email'; }
            public function isConfigured(): bool { return true; }
            public function send(OutboundMessage $message): ChannelResult { throw new \RuntimeException('smtp down'); }
        };
        app(NotificationDispatcher::class)->register($boom);
        User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $other = $this->member([], withCoach: false);

        $this->logger()->save($other, ['Fatigue' => 9]);

        $this->assertSame(2, CoachFlag::count());
        $this->assertSame(2, SideEffectEntry::count());
    }

    public function test_repeat_saves_keep_one_open_flag_with_the_highest_score_and_do_not_re_alert(): void
    {
        $u = $this->member();

        $this->logger()->save($u, ['Abdominal pain' => 7]);
        $told = count($this->sent);
        $this->logger()->save($u, ['Abdominal pain' => 9]);
        $this->logger()->save($u, ['Abdominal pain' => 7]);

        $this->assertSame(1, CoachFlag::count());
        $this->assertSame(9, CoachFlag::first()->score);
        $this->assertSame($told, count($this->sent), 'No second alert for the same open flag');

        $this->travel(25)->hours();
        $this->logger()->save($u, ['Abdominal pain' => 8]);
        $this->assertSame(2, CoachFlag::count(), 'A new day is a new flag');
    }

    public function test_reach_the_doctor_creates_one_request_per_hour(): void
    {
        $u = $this->member();
        $e = app(CoachEscalation::class);

        $a = $e->selfReport($u);
        $b = $e->selfReport($u);

        $this->assertTrue($a->is($b));
        $this->assertSame('self_report', $a->source);
        $this->assertNull($a->symptom);
        $this->assertNotEmpty($this->sent);

        $this->travel(61)->minutes();
        $this->assertFalse($e->selfReport($u)->is($a));
    }

    // ---- the screens --------------------------------------------------------------------------

    public function test_screen_lists_the_symptoms_and_cannot_save_without_a_score(): void
    {
        $u = $this->member();

        $c = Livewire::actingAs($u)->test(LogSideEffect::class)
            ->assertSee('Nausea')->assertSee('Abdominal pain')->assertSee('Injection anxiety')
            ->call('save', [])
            ->assertSee('at least one symptom')
            ->assertSet('saved', false);

        $this->assertSame(0, SideEffectEntry::count());
    }

    public function test_a_normal_save_shows_the_reassuring_screen_with_the_luteal_line_for_tracking_women(): void
    {
        $u = $this->member(['track_cycle' => true, 'last_period_start' => '2026-11-04']);

        Livewire::actingAs($u)->test(LogSideEffect::class)
            ->call('save', ['Nausea' => 3])
            ->assertSet('saved', true)
            ->assertSee('This is part of the process')
            ->assertSee('This is common')
            ->assertSee('How to manage')
            ->assertSee('In your luteal phase, cravings and nausea can feel stronger.')
            ->assertSee('message Amy')
            ->assertDontSee('Reach Dr Anke now');

        $other = $this->member(['track_cycle' => false, 'last_period_start' => '2026-11-04']);
        Livewire::actingAs($other)->test(LogSideEffect::class)->call('save', ['Nausea' => 3])->assertDontSee('luteal');
    }

    public function test_a_flagged_save_shows_the_red_screen_and_the_reach_doctor_button(): void
    {
        $u = $this->member();

        $c = Livewire::actingAs($u)->test(LogSideEffect::class)
            ->call('save', ['Abdominal pain' => 8, 'Nausea' => 2])
            ->assertSet('saved', true)
            ->assertSee("This needs a doctor's eyes")
            ->assertSee('Abdominal pain 8/10')
            ->assertSee('Reach Dr Anke now')
            ->assertSee('call 112 or 10177')
            ->assertDontSee('This is part of the process');

        $c->call('reachDoctor')
            ->assertSet('doctorReached', true)
            ->assertSee('Dr Anke has been alerted again')
            ->assertDontSee('Reach Dr Anke now');

        $this->assertSame(1, CoachFlag::where('source', 'self_report')->count());
        $this->assertSame(1, CoachFlag::where('source', 'side_effect')->count());
    }

    public function test_hiding_and_adding_symptoms_through_the_screen(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(LogSideEffect::class)
            ->call('hide', 'Hair loss')->assertDontSee('Hair loss')->assertSee('Restore hidden symptoms')
            ->call('restore')->assertSee('Hair loss')->assertDontSee('Restore hidden symptoms')
            ->set('newName', 'Dizzy spells')->call('add')->assertSee('Dizzy spells')->assertSet('newName', '')
            ->set('newName', '   ')->call('add')->assertSee('Please type a symptom name');
    }

    public function test_a_symptom_hidden_in_this_visit_but_still_in_the_browser_is_ignored_not_an_error(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(LogSideEffect::class)
            ->call('hide', 'Nausea')
            ->call('save', ['Nausea' => 5, 'Fatigue' => 2])
            ->assertSet('saved', true);

        $this->assertSame(['Fatigue'], SideEffectEntry::where('user_id', $u->id)->pluck('symptom')->all());
    }

    // ---- who sees flags -----------------------------------------------------------------------

    private function flagFor(User $patient): CoachFlag
    {
        $this->logger()->save($patient, ['Abdominal pain' => 8]);

        return CoachFlag::where('patient_id', $patient->id)->firstOrFail();
    }

    public function test_flags_are_visible_to_the_assigned_coach_the_treating_doctor_and_the_named_triage_doctor_only(): void
    {
        $u = $this->member();
        $flag = $this->flagFor($u);
        $access = app(CoachAccess::class);

        $coach = User::find(CoachingAssignment::where('patient_id', $u->id)->value('coach_id'));
        $treating = User::factory()->create(['role' => UserRole::Doctor]);
        Appointment::create([
            'patient_id' => $u->id, 'doctor_id' => $treating->id, 'type' => 'general', 'status' => 'completed',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
        ]);
        $stranger = User::factory()->create(['role' => UserRole::Doctor, 'email' => 'someone@zapmed.test']);
        $triage = User::factory()->create(['role' => UserRole::Doctor, 'email' => 'anke@zapmed.test']);
        config(['coach.flags.notify_emails' => ['anke@zapmed.test']]);
        $otherCoach = User::factory()->create(['role' => UserRole::HealthCoach]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue($access->canHandleFlag($coach, $flag));
        $this->assertTrue($access->canHandleFlag($treating, $flag));
        $this->assertTrue($access->canHandleFlag($triage, $flag));
        $this->assertFalse($access->canHandleFlag($stranger, $flag));
        $this->assertFalse($access->canHandleFlag($otherCoach, $flag));
        $this->assertFalse($access->canHandleFlag($admin, $flag));
        $this->assertFalse($access->canHandleFlag($u, $flag), 'The member does not triage their own flag');
    }

    public function test_the_staff_list_shows_detail_to_the_right_people_and_lets_them_close_a_flag(): void
    {
        $u = $this->member();
        $u->update(['member_number' => 'ZM-000777']);
        $flag = $this->flagFor($u);
        $coach = User::find(CoachingAssignment::where('patient_id', $u->id)->value('coach_id'));

        Livewire::actingAs($coach)->test(FlagsInbox::class)
            ->assertSee('ZM-000777')
            ->assertSee('Abdominal pain')
            ->assertSee('8/10')
            ->call('handled', $flag->id)
            ->assertSee('No open flags');

        $flag->refresh();
        $this->assertSame('handled', $flag->status);
        $this->assertSame($coach->id, $flag->handled_by);
        $this->assertNotNull($flag->handled_at);
    }

    public function test_someone_without_access_sees_nothing_and_cannot_close_a_flag(): void
    {
        $u = $this->member();
        $flag = $this->flagFor($u);
        $stranger = User::factory()->create(['role' => UserRole::Doctor, 'email' => 'someone@zapmed.test']);

        Livewire::actingAs($stranger)->test(FlagsInbox::class)->assertDontSee('Abdominal pain')->assertSee('No open flags');
        Livewire::actingAs($stranger)->test(FlagsInbox::class)->call('handled', $flag->id)->assertForbidden();

        $this->assertSame('open', $flag->fresh()->status);
    }

    public function test_the_staff_page_is_for_doctors_and_coaches_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->get('/coach-flags')->assertStatus(403);
        $this->actingAs($this->member())->get('/coach-flags')->assertStatus(403);
        $this->actingAs(User::factory()->create(['role' => UserRole::Doctor]))->get('/coach-flags')->assertOk()->assertSee('Coach safety flags');
        $this->actingAs(User::factory()->create(['role' => UserRole::HealthCoach]))->get('/coach-flags')->assertOk();
    }

    // ---- access + links -----------------------------------------------------------------------

    public function test_member_pages_need_the_add_on_and_today_links_to_the_screen(): void
    {
        $plain = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $plain->id, 'onboarding_complete' => true]);
        $this->actingAs($plain)->get('/coach/side-effects')->assertRedirect('/weight-loss');

        $member = $this->member();
        $this->actingAs($member)->get('/coach/side-effects')->assertOk()->assertSee('How are you feeling?');
        $this->actingAs($member)->get('/coach')->assertOk()->assertSee(route('coach.side-effects'), false);
    }

    public function test_the_screens_never_name_a_medicine_or_advise_a_dose_change(): void
    {
        $u = $this->member();
        $html = $this->actingAs($u)->get('/coach/side-effects')->assertOk()->getContent();
        $c = Livewire::actingAs($u)->test(LogSideEffect::class)->call('save', ['Abdominal pain' => 9]);
        $all = $html.$c->html();

        foreach (['ozempic', 'wegovy', 'mounjaro', 'saxenda', 'semaglutide', 'tirzepatide', 'liraglutide', 'reduce your dose', 'stop taking', 'skip a dose'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $all);
        }
    }
}
