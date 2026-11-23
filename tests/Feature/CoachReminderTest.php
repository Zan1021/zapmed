<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\Reminders;
use App\Models\CoachingAssignment;
use App\Models\CoachProfile;
use App\Models\DoseEntry;
use App\Models\NotificationLog;
use App\Models\PatientProfile;
use App\Models\ReminderSetting;
use App\Models\User;
use App\Models\WaterEntry;
use App\Models\WeightEntry;
use App\Services\Coach\ReminderScheduler;
use App\Services\Coach\ReminderSettings;
use App\Services\Notifications\ChannelResult;
use App\Services\Notifications\Contracts\NotificationChannel;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coach App 1.7: reminder settings screen, the scheduler that decides what is due, and how it is delivered.
 * 2026-11-24 is a Tuesday. All clock times are South African time.
 */
class CoachReminderTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int,array{channel:string,message:OutboundMessage}> */
    public array $sent = [];

    /** @var array<string,bool> channel => delivers? */
    public array $works = ['email' => true, 'sms' => true, 'whatsapp' => true];

    protected function setUp(): void
    {
        parent::setUp();
        config(['coach.reminder_sending.enabled' => true]);
        $this->sent = [];

        $dispatcher = app(NotificationDispatcher::class);
        foreach (['email', 'sms', 'whatsapp'] as $key) {
            $dispatcher->register($this->makeChannel($key));
        }
        $this->app->instance(NotificationDispatcher::class, $dispatcher);
    }

    private function makeChannel(string $key): NotificationChannel
    {
        $t = $this;

        return new class($key, $t) implements NotificationChannel {
            public function __construct(private string $k, private $t) {}

            public function key(): string { return $this->k; }

            public function isConfigured(): bool { return true; }

            public function send(OutboundMessage $message): ChannelResult
            {
                $this->t->sent[] = ['channel' => $this->k, 'message' => $message];

                return $this->t->works[$this->k] ? ChannelResult::sent('spy') : ChannelResult::failed('spy', 'down');
            }
        };
    }

    private function at(string $hhmm): CarbonImmutable
    {
        $now = CarbonImmutable::parse("2026-11-24 {$hhmm}", 'Africa/Johannesburg');
        $this->travelTo($now);

        return $now;
    }

    private function member(array $coach = [], array $userAttrs = []): User
    {
        $user = User::factory()->create(array_merge(['role' => UserRole::Patient, 'gender' => 'female', 'phone' => '0821234567'], $userAttrs));
        PatientProfile::create(['user_id' => $user->id, 'onboarding_complete' => true]);
        CoachingAssignment::create([
            'patient_id' => $user->id,
            'coach_id' => User::factory()->create(['role' => UserRole::HealthCoach])->id,
            'started_at' => now(),
        ]);
        CoachProfile::create(array_merge(['user_id' => $user->id, 'onboarded_at' => now()], $coach));

        return $user;
    }

    private function clock(): CarbonImmutable
    {
        return CarbonImmutable::now('Africa/Johannesburg');
    }

    private function settings(): ReminderSettings
    {
        return app(ReminderSettings::class);
    }

    private function scheduler(): ReminderScheduler
    {
        return app(ReminderScheduler::class);
    }

    /** Turn every reminder off except the ones named, so a test checks one thing at a time. */
    private function only(User $u, array $keep, array $changes = []): void
    {
        foreach (ReminderSettings::TYPES as $type) {
            if (! in_array($type, $keep, true)) {
                ReminderSetting::updateOrCreate(['user_id' => $u->id, 'type' => $type], ['enabled' => false, 'settings' => []]);
            }
        }
        foreach ($changes as $type => $kv) {
            foreach ($kv as $k => $v) {
                $this->settings()->set($u, $type, $k, $v);
            }
        }
    }

    // ---- settings -----------------------------------------------------------------------------

    public function test_a_member_with_nothing_saved_gets_the_guide_defaults(): void
    {
        $s = $this->settings()->all($this->member());

        $this->assertTrue($s['hydration']['enabled']);
        $this->assertSame(2, $s['hydration']['every_hours']);
        $this->assertSame('08:00', $s['hydration']['from']);
        $this->assertSame('20:00', $s['hydration']['until']);
        $this->assertTrue($s['hydration']['smart_skip']);
        $this->assertSame('whatsapp', $s['hydration']['channel']);
        $this->assertSame('monday', $s['weigh_in']['day']);
        $this->assertSame('weekly', $s['weigh_in']['frequency']);
        $this->assertSame('20:00', $s['dose_day']['time']);
        $this->assertCount(5, $s);
    }

    public function test_older_channel_values_both_and_push_mean_whatsapp(): void
    {
        $u = $this->member();
        ReminderSetting::create(['user_id' => $u->id, 'type' => 'hydration', 'enabled' => true, 'settings' => ['channel' => 'both']]);

        $this->assertSame('whatsapp', $this->settings()->get($u, 'hydration')['channel']);
        $this->assertSame('whatsapp', $this->settings()->channel('push'));
        $this->assertSame('email', $this->settings()->channel('email'));
    }

    public function test_valid_changes_are_saved_and_keep_the_other_options(): void
    {
        $u = $this->member();

        $this->settings()->set($u, 'hydration', 'every_hours', 3);
        $this->settings()->set($u, 'hydration', 'from', '06:00');
        $this->settings()->set($u, 'hydration', 'until', '22:00');
        $this->settings()->set($u, 'hydration', 'smart_skip', false);
        $this->settings()->set($u, 'hydration', 'channel', 'email');
        $this->settings()->set($u, 'weigh_in', 'day', 'Friday');
        $this->settings()->set($u, 'weigh_in', 'frequency', 'daily');
        $this->settings()->set($u, 'dose_day', 'time', '08:00');

        $s = $this->settings()->all($u);
        $this->assertSame([3, '06:00', '22:00', false, 'email'], [$s['hydration']['every_hours'], $s['hydration']['from'], $s['hydration']['until'], $s['hydration']['smart_skip'], $s['hydration']['channel']]);
        $this->assertSame(['friday', 'daily'], [$s['weigh_in']['day'], $s['weigh_in']['frequency']]);
        $this->assertSame('08:00', $s['dose_day']['time']);
        $this->assertTrue($s['hydration']['enabled'], 'Changing an option does not switch the reminder off');
    }

    public function test_choices_that_are_not_offered_are_rejected(): void
    {
        $u = $this->member();
        $bad = [
            ['hydration', 'every_hours', 5], ['hydration', 'every_hours', 0], ['hydration', 'from', '05:00'], ['hydration', 'from', '11:00'],
            ['hydration', 'until', '17:00'], ['hydration', 'until', '23:00'], ['hydration', 'channel', 'carrier-pigeon'], ['hydration', 'smart_skip', 'maybe'],
            ['weigh_in', 'day', 'funday'], ['weigh_in', 'frequency', 'monthly'], ['dose_day', 'time', '09:00'],
            ['hydration', 'enabled', true], ['meals', 'anything', 1], ['nonsense', 'x', 1],
        ];

        foreach ($bad as [$type, $key, $value]) {
            try {
                $this->settings()->set($u, $type, $key, $value);
                $this->fail("{$type}.{$key}={$value} should be rejected");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, ReminderSetting::where('user_id', $u->id)->count());
    }

    public function test_toggle_flips_and_remembers(): void
    {
        $u = $this->member();

        $this->assertFalse($this->settings()->toggle($u, 'meals'));
        $this->assertFalse($this->settings()->get($u, 'meals')['enabled']);
        $this->assertTrue($this->settings()->toggle($u, 'meals'));
        $this->assertTrue($this->settings()->get($u, 'meals')['enabled']);
    }

    // ---- the screen ---------------------------------------------------------------------------

    public function test_screen_shows_the_controls_and_hides_them_when_a_reminder_is_off(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(Reminders::class)
            ->assertSee('Hydration')->assertSee('Weekly weigh-in')->assertSee('Dose day')->assertSee('Log your meals')
            ->assertSee('Smart nudge')->assertSee('Preview')->assertSee('Every 2 hours')
            ->assertSee('never name your medication')
            ->call('toggle', 'hydration')
            ->assertDontSee('Smart nudge')->assertDontSee('Preview');
    }

    public function test_changes_through_the_screen_save_at_once_and_bad_values_show_an_error(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(Reminders::class)
            ->call('setValue', 'hydration', 'every_hours', '3')->assertSet('error', null)
            ->call('setValue', 'hydration', 'every_hours', '7')->assertSee('That choice is not available.');

        $this->assertSame(3, $this->settings()->get($u, 'hydration')['every_hours']);
    }

    public function test_the_cycle_row_is_only_for_members_who_track_their_cycle(): void
    {
        Livewire::actingAs($this->member(['track_cycle' => true]))->test(Reminders::class)->assertSee('Cycle heads-up');
        Livewire::actingAs($this->member(['track_cycle' => false]))->test(Reminders::class)->assertDontSee('Cycle heads-up');
    }

    public function test_preview_shows_the_text_and_sends_nothing(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(Reminders::class)->call('preview', 'hydration')->assertDispatched('coach-toast');

        $this->assertSame([], $this->sent);
        $this->assertSame(0, NotificationLog::count());
    }

    // ---- master switch + command + schedule ---------------------------------------------------

    public function test_nothing_is_sent_while_the_master_switch_is_off(): void
    {
        config(['coach.reminder_sending.enabled' => false]);
        $u = $this->member();
        $this->only($u, ['hydration'], ['hydration' => ['smart_skip' => false]]);

        $this->assertSame(0, $this->scheduler()->run($this->at('10:03')));
        $this->assertSame([], $this->sent);

        $this->artisan('coach:send-reminders')->expectsOutputToContain('switched off')->assertSuccessful();
    }

    public function test_the_command_runs_the_scheduler_when_switched_on_and_is_on_the_schedule(): void
    {
        $u = $this->member();
        $this->only($u, ['hydration'], ['hydration' => ['smart_skip' => false, 'channel' => 'email']]);
        $this->at('10:03');

        $this->artisan('coach:send-reminders')->expectsOutputToContain('Sent 1 reminder')->assertSuccessful();

        $commands = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode(' ');
        $this->assertStringContainsString('coach:send-reminders', $commands);
    }

    // ---- hydration ----------------------------------------------------------------------------

    public function test_hydration_goes_out_at_each_slot_once(): void
    {
        $u = $this->member();
        $this->only($u, ['hydration'], ['hydration' => ['smart_skip' => false, 'channel' => 'email']]);

        $this->assertSame(0, $this->scheduler()->run($this->at('07:03')), 'Before the window starts');
        $this->assertSame(1, $this->scheduler()->run($this->at('08:03')), 'First slot');
        $this->assertSame(0, $this->scheduler()->run($this->at('08:08')), 'Same slot is not sent twice');
        $this->assertSame(0, $this->scheduler()->run($this->at('09:03')), 'Between slots');
        $this->assertSame(1, $this->scheduler()->run($this->at('10:03')), 'Second slot');
        $this->assertSame(0, $this->scheduler()->run($this->at('10:20')), 'Too late for that slot');
        $this->assertSame(1, $this->scheduler()->run($this->at('20:03')), 'Last slot is the "until" time');
        $this->assertSame(0, $this->scheduler()->run($this->at('20:40')), 'After the window');
        $this->assertCount(3, $this->sent);
    }

    public function test_hydration_respects_the_chosen_interval_and_window(): void
    {
        $u = $this->member();
        $this->only($u, ['hydration'], ['hydration' => ['smart_skip' => false, 'channel' => 'email', 'every_hours' => 3, 'from' => '06:00', 'until' => '18:00']]);

        $this->assertSame(1, $this->scheduler()->run($this->at('06:02')));
        $this->assertSame(0, $this->scheduler()->run($this->at('08:02')), 'Every 3 hours, not 2');
        $this->assertSame(1, $this->scheduler()->run($this->at('09:02')));
        $this->assertSame(1, $this->scheduler()->run($this->at('18:02')));
        $this->assertSame(0, $this->scheduler()->run($this->at('21:02')), 'Past the until time');
    }

    public function test_smart_nudge_skips_when_on_pace_and_sends_when_behind(): void
    {
        $onPace = $this->member();
        $this->only($onPace, ['hydration'], ['hydration' => ['channel' => 'email']]);
        $behind = $this->member();
        $this->only($behind, ['hydration'], ['hydration' => ['channel' => 'email']]);

        $now = $this->at('10:03'); // slot 2 of 7: the share due by now is 2/7 of 2,200 = 628 mL
        WaterEntry::create(['user_id' => $onPace->id, 'logged_at' => now()->subHour(), 'ml' => 1000]);
        WaterEntry::create(['user_id' => $behind->id, 'logged_at' => now()->subHour(), 'ml' => 100]);

        $this->assertSame(1, $this->scheduler()->run($now));
        $this->assertSame($behind->id, $this->sent[0]['message']->user->id);
    }

    // ---- weigh-in -----------------------------------------------------------------------------

    public function test_weekly_weigh_in_goes_out_on_the_chosen_day_unless_already_weighed(): void
    {
        $u = $this->member();
        $this->only($u, ['weigh_in'], ['hydration' => ['channel' => 'email'], 'weigh_in' => ['day' => 'tuesday']]);

        $this->assertSame(0, $this->scheduler()->run($this->at('07:50')), 'Not before 08:00');
        $this->assertSame(1, $this->scheduler()->run($this->at('08:02')));

        $this->settings()->set($u, 'weigh_in', 'day', 'wednesday');
        ReminderSetting::where('user_id', $u->id)->where('type', 'weigh_in')->update(['last_sent_at' => null]);
        $this->assertSame(0, $this->scheduler()->run($this->at('08:02')), 'Tuesday is not the chosen day any more');

        $this->settings()->set($u, 'weigh_in', 'frequency', 'daily');
        WeightEntry::create(['user_id' => $u->id, 'logged_on' => '2026-11-24', 'weight_kg' => 90]);
        $this->assertSame(0, $this->scheduler()->run($this->at('08:02')), 'Already weighed today');

        WeightEntry::query()->delete();
        $this->assertSame(1, $this->scheduler()->run($this->at('08:02')), 'Daily frequency sends on any day');
    }

    // ---- dose day -----------------------------------------------------------------------------

    public function test_dose_day_goes_out_the_evening_before_and_on_the_day(): void
    {
        $before = $this->member();
        $this->only($before, ['dose_day'], ['hydration' => ['channel' => 'email']]);
        $this->at('20:03');
        DoseEntry::create(['user_id' => $before->id, 'taken_at' => now()->subDays(6), 'site' => 'Left thigh', 'pain' => 1]); // next dose tomorrow

        $on = $this->member();
        $this->only($on, ['dose_day'], ['hydration' => ['channel' => 'email']]);
        DoseEntry::create(['user_id' => $on->id, 'taken_at' => now()->subDays(7), 'site' => 'Left thigh', 'pain' => 1]); // next dose today

        $early = $this->member();
        $this->only($early, ['dose_day'], ['hydration' => ['channel' => 'email']]);
        DoseEntry::create(['user_id' => $early->id, 'taken_at' => now()->subDays(5), 'site' => 'Left thigh', 'pain' => 1]); // two days away

        $none = $this->member();
        $this->only($none, ['dose_day'], ['hydration' => ['channel' => 'email']]);

        $this->assertSame(2, $this->scheduler()->run($this->clock()));
        $this->assertEqualsCanonicalizing([$before->id, $on->id], collect($this->sent)->map(fn ($s) => $s['message']->user->id)->all());
    }

    public function test_dose_day_uses_the_chosen_time_and_stops_once_the_dose_is_logged(): void
    {
        $u = $this->member();
        $this->only($u, ['dose_day'], ['hydration' => ['channel' => 'email'], 'dose_day' => ['time' => '08:00']]);
        $this->at('08:02');
        DoseEntry::create(['user_id' => $u->id, 'taken_at' => now()->subDays(7), 'site' => 'Left thigh', 'pain' => 1]);

        $this->assertSame(1, $this->scheduler()->run($this->clock()));

        ReminderSetting::where('user_id', $u->id)->where('type', 'dose_day')->update(['last_sent_at' => null]);
        DoseEntry::create(['user_id' => $u->id, 'taken_at' => now(), 'site' => 'Right thigh', 'pain' => 0]); // logged today
        $this->assertSame(0, $this->scheduler()->run($this->clock()), 'Dose logged: nothing more to remind');
    }

    // ---- cycle --------------------------------------------------------------------------------

    public function test_cycle_heads_up_two_days_before_the_period_and_at_the_luteal_phase(): void
    {
        $twoDays = $this->member(['track_cycle' => true, 'last_period_start' => '2026-11-01']); // day 24 of 28: period in 5 days
        $this->only($twoDays, ['cycle'], ['hydration' => ['channel' => 'email']]);

        $luteal = $this->member(['track_cycle' => true, 'last_period_start' => '2026-11-08']); // day 17: first luteal day
        $this->only($luteal, ['cycle'], ['hydration' => ['channel' => 'email']]);

        $due = $this->member(['track_cycle' => true, 'last_period_start' => '2026-10-29']); // day 27: period in 2 days
        $this->only($due, ['cycle'], ['hydration' => ['channel' => 'email']]);

        $off = $this->member(['track_cycle' => false, 'last_period_start' => '2026-10-29']);
        $this->only($off, ['cycle'], ['hydration' => ['channel' => 'email']]);

        $this->at('09:02');

        $this->assertSame(2, $this->scheduler()->run($this->clock()));
        $this->assertEqualsCanonicalizing([$luteal->id, $due->id], collect($this->sent)->map(fn ($s) => $s['message']->user->id)->all());
    }

    // ---- meals + who is eligible --------------------------------------------------------------

    public function test_meal_nudges_stay_off_until_meal_logging_exists_then_follow_the_times(): void
    {
        $u = $this->member();
        $this->only($u, ['meals'], ['hydration' => ['channel' => 'email']]);

        $this->assertSame(0, $this->scheduler()->run($this->at('12:32')), 'Off by config today');

        config(['coach.reminder_sending.meals_enabled' => true]);
        $this->assertSame(1, $this->scheduler()->run($this->at('12:32')));
        $this->assertSame(0, $this->scheduler()->run($this->at('15:00')));
        $this->assertSame(1, $this->scheduler()->run($this->at('18:32')));
    }

    public function test_only_onboarded_members_with_the_add_on_and_reminders_on_are_messaged(): void
    {
        $this->at('10:03');

        $off = $this->member();
        $this->only($off, [], []); // every reminder off

        $noAddon = User::factory()->create(['role' => UserRole::Patient, 'phone' => '0820000000']);
        PatientProfile::create(['user_id' => $noAddon->id, 'onboarding_complete' => true]);
        CoachProfile::create(['user_id' => $noAddon->id, 'onboarded_at' => now()]);
        $this->settings()->set($noAddon, 'hydration', 'smart_skip', false);
        $this->settings()->set($noAddon, 'hydration', 'channel', 'email');

        $notOnboarded = $this->member(['onboarded_at' => null]);
        $this->settings()->set($notOnboarded, 'hydration', 'smart_skip', false);

        $this->assertSame(0, $this->scheduler()->run($this->clock()));
        $this->assertSame([], $this->sent);
    }

    // ---- delivery -----------------------------------------------------------------------------

    private function hydrationDue(string $channel, array $userAttrs = []): User
    {
        $u = $this->member([], $userAttrs);
        $this->only($u, ['hydration'], ['hydration' => ['smart_skip' => false, 'channel' => $channel]]);
        $this->at('10:03');

        return $u;
    }

    public function test_whatsapp_falls_back_to_email_until_meta_is_configured(): void
    {
        config(['services.whatsapp.token' => null, 'services.whatsapp.phone_number_id' => null]);
        $this->hydrationDue('whatsapp');

        $this->assertSame(1, $this->scheduler()->run($this->clock()));
        $this->assertSame(['email'], collect($this->sent)->pluck('channel')->all(), 'Meta not set up: use email, do not drop the reminder');
    }

    public function test_whatsapp_is_used_when_configured_and_email_is_the_backup_when_it_fails(): void
    {
        config(['services.whatsapp.token' => 'x', 'services.whatsapp.phone_number_id' => '1']);
        $this->hydrationDue('whatsapp');

        $this->assertSame(1, $this->scheduler()->run($this->clock()));
        $this->assertSame(['whatsapp'], collect($this->sent)->pluck('channel')->all());

        $this->sent = [];
        $this->works['whatsapp'] = false;
        ReminderSetting::query()->update(['last_sent_at' => null]);

        $this->assertSame(1, $this->scheduler()->run($this->clock()));
        $this->assertSame(['whatsapp', 'email'], collect($this->sent)->pluck('channel')->all(), 'Both attempts are made and logged');
        $log = NotificationLog::where('template_key', 'coach.reminder.hydration');
        $this->assertSame(1, (clone $log)->where('channel', 'whatsapp')->where('status', 'failed')->count());
        $this->assertSame(1, (clone $log)->where('channel', 'whatsapp')->where('status', 'sent')->count());
        $this->assertSame(1, (clone $log)->where('channel', 'email')->where('status', 'sent')->count());
    }

    public function test_sms_and_email_choices_and_a_missing_phone(): void
    {
        $this->hydrationDue('sms');
        $this->assertSame(1, $this->scheduler()->run($this->clock()));
        $this->assertSame(['sms'], collect($this->sent)->pluck('channel')->all());

        $this->sent = [];
        $this->hydrationDue('sms', ['phone' => null]);
        $this->scheduler()->run($this->clock());
        $this->assertSame(['email'], collect($this->sent)->pluck('channel')->all(), 'No phone number: email');

        $this->sent = [];
        $this->hydrationDue('email');
        $this->scheduler()->run($this->clock());
        $this->assertSame(['email'], collect($this->sent)->pluck('channel')->all());
    }

    public function test_a_reminder_that_could_not_be_delivered_is_not_marked_sent_so_it_retries_in_the_window(): void
    {
        $u = $this->hydrationDue('email');
        $this->works['email'] = false;

        $this->assertSame(0, $this->scheduler()->run($this->clock()));
        $this->assertNull(ReminderSetting::where('user_id', $u->id)->where('type', 'hydration')->value('last_sent_at'));

        $this->works['email'] = true;
        $this->assertSame(1, $this->scheduler()->run($this->clock()), 'Next run, still inside the window');
    }

    // ---- wording ------------------------------------------------------------------------------

    public function test_reminder_wording_names_no_medicine_and_carries_no_health_detail(): void
    {
        foreach (['hydration', 'weigh_in', 'dose_day', 'meals', 'cycle'] as $type) {
            $text = strtolower(config("coach.reminder_text.{$type}"));
            $this->assertNotSame('', $text);
            foreach (['ozempic', 'wegovy', 'mounjaro', 'saxenda', 'semaglutide', 'tirzepatide', 'liraglutide', 'medication', 'injection', 'inject', 'dose', 'period', 'cycle', 'luteal', 'pain', 'symptom', 'side effect', 'weight loss'] as $word) {
                $this->assertStringNotContainsString($word, $text, "{$type} reminder contains '{$word}'");
            }
        }
    }

    // ---- links + access -----------------------------------------------------------------------

    public function test_the_reminders_page_needs_the_add_on_and_is_linked_from_the_other_screens(): void
    {
        $plain = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $plain->id, 'onboarding_complete' => true]);
        $this->actingAs($plain)->get('/coach/reminders')->assertRedirect('/weight-loss');

        $u = $this->member();
        $url = route('coach.reminders');
        $this->actingAs($u)->get('/coach/reminders')->assertOk()->assertSee('Reminders');
        foreach (['/coach', '/coach/water', '/coach/medication', '/coach/weight'] as $page) {
            $this->actingAs($u)->get($page)->assertOk()->assertSee($url, false);
        }
    }

    public function test_today_says_so_when_every_reminder_is_off(): void
    {
        $u = $this->member();
        $this->only($u, []);

        Livewire::actingAs($u)->test(\App\Livewire\Coach\Today::class)->assertSee('Reminders are off. Tap to set them up.');
    }
}
