<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\BookCall;
use App\Livewire\Coach\CoachSheet;
use App\Livewire\Coach\Inbox;
use App\Livewire\Coach\Onboarding;
use App\Livewire\Coach\Today;
use App\Models\CoachCall;
use App\Models\CoachingAssignment;
use App\Models\CoachProfile;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Coach\CoachCalls;
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
 * Coach App 1.9: booking a call with the coach. Now is Tuesday 2026-11-24 10:00 SAST.
 * Call hours: weekdays 08:30 to 16:30, break 12:00 to 13:00, slots on the half hour, 2 hours' notice.
 */
class CoachCallTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int,array{channel:string,message:OutboundMessage}> */
    public array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00', 'Africa/Johannesburg'));
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

                return ChannelResult::sent('spy');
            }
        };
    }

    private function member(?User $coach = null, bool $assign = true): User
    {
        $user = User::factory()->create(['role' => UserRole::Patient, 'gender' => 'female', 'phone' => '0821234567']);
        PatientProfile::create(['user_id' => $user->id, 'onboarding_complete' => true]);
        CoachProfile::create(['user_id' => $user->id, 'onboarded_at' => now()]);

        if ($assign) {
            $coach ??= User::factory()->create(['role' => UserRole::HealthCoach, 'first_name' => 'Amy']);
            CoachingAssignment::create(['patient_id' => $user->id, 'coach_id' => $coach->id, 'started_at' => now()]);
        }

        return $user;
    }

    private function coach(): User
    {
        return User::factory()->create(['role' => UserRole::HealthCoach, 'first_name' => 'Amy']);
    }

    private function svc(): CoachCalls
    {
        return app(CoachCalls::class);
    }

    private function sast(string $dt): CarbonImmutable
    {
        return CarbonImmutable::parse($dt, 'Africa/Johannesburg');
    }

    private function times(array $slots, bool $onlyAvailable = false): array
    {
        return collect($slots)->when($onlyAvailable, fn ($c) => $c->where('available', true))->pluck('time')->values()->all();
    }

    // ---- days + slots -------------------------------------------------------------------------

    public function test_the_next_five_working_days_skip_the_weekend(): void
    {
        $days = $this->svc()->days()->map(fn ($d) => $d->toDateString())->all();

        $this->assertSame(['2026-11-24', '2026-11-25', '2026-11-26', '2026-11-27', '2026-11-30'], $days);

        $fromSaturday = $this->svc()->days($this->sast('2026-11-28 09:00'))->map(fn ($d) => $d->toDateString())->all();
        $this->assertSame('2026-11-30', $fromSaturday[0]);
        $this->assertCount(5, $fromSaturday);
    }

    public function test_slots_start_on_the_half_hour_inside_call_hours_and_skip_the_lunch_break(): void
    {
        $u = $this->member();
        $wed = $this->sast('2026-11-25');

        $thirty = $this->times($this->svc()->slots($u, $wed, 30));
        $this->assertSame('08:30', $thirty[0]);
        $this->assertContains('11:30', $thirty, '11:30 to 12:00 ends exactly at the break');
        $this->assertNotContains('12:00', $thirty);
        $this->assertNotContains('12:30', $thirty);
        $this->assertContains('13:00', $thirty);
        $this->assertSame('16:00', end($thirty), '16:00 to 16:30 is the last 30 minute call');
        $this->assertNotContains('16:30', $thirty);

        $fifteen = $this->times($this->svc()->slots($u, $wed, 15));
        $this->assertContains('16:00', $fifteen);
        $this->assertNotContains('16:30', $fifteen, '16:30 to 16:45 would run past the end of the day');
        $this->assertNotContains('12:30', $fifteen);
    }

    public function test_weekends_and_unknown_lengths_have_no_slots(): void
    {
        $u = $this->member();

        $this->assertSame([], $this->svc()->slots($u, $this->sast('2026-11-28'), 30));
        $this->assertSame([], $this->svc()->slots($u, $this->sast('2026-11-25'), 20));
    }

    public function test_two_hours_notice_is_needed_today(): void
    {
        $u = $this->member();

        $open = $this->times($this->svc()->slots($u, $this->sast('2026-11-24'), 30), true);

        $this->assertNotContains('09:00', $open);
        $this->assertNotContains('11:30', $open, 'Now is 10:00, so the first bookable time is 12:00');
        $this->assertSame('13:00', $open[0], '12:00 and 12:30 are the break');
    }

    public function test_a_booked_call_blocks_overlapping_slots_only(): void
    {
        $coach = $this->coach();
        $a = $this->member($coach);
        $b = $this->member($coach);
        $this->svc()->book($a, $this->sast('2026-11-25 09:00'), 30);

        $open = $this->times($this->svc()->slots($b, $this->sast('2026-11-25'), 30), true);

        $this->assertNotContains('09:00', $open);
        $this->assertContains('08:30', $open, '08:30 to 09:00 ends as the other call starts');
        $this->assertContains('09:30', $open);
    }

    public function test_cancelled_calls_and_other_coaches_do_not_block(): void
    {
        $coach = $this->coach();
        $a = $this->member($coach);
        $b = $this->member($coach);
        $other = $this->member();   // a different coach
        $this->svc()->book($other, $this->sast('2026-11-25 10:00'), 30);

        $this->assertContains('10:00', $this->times($this->svc()->slots($b, $this->sast('2026-11-25'), 30), true), 'Another coach\'s call is not ours');

        $call = $this->svc()->book($a, $this->sast('2026-11-25 11:00'), 30);
        $this->assertNotContains('11:00', $this->times($this->svc()->slots($b, $this->sast('2026-11-25'), 30), true));
        $this->svc()->cancel($a, $call->id);
        $this->assertContains('11:00', $this->times($this->svc()->slots($b, $this->sast('2026-11-25'), 30), true));
    }

    public function test_without_a_coach_nothing_can_be_booked(): void
    {
        $u = $this->member(null, false);

        $slots = $this->svc()->slots($u, $this->sast('2026-11-25'), 30);
        $this->assertNotEmpty($slots);
        $this->assertSame([], $this->times($slots, true));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('assigned soon');
        $this->svc()->book($u, $this->sast('2026-11-25 10:00'), 30);
    }

    // ---- booking ------------------------------------------------------------------------------

    public function test_a_call_is_booked_for_the_assigned_coach_and_stored_in_utc(): void
    {
        $coach = $this->coach();
        $u = $this->member($coach);

        $call = $this->svc()->book($u, $this->sast('2026-11-25 08:30'), 15, 'welcome');

        $this->assertSame($coach->id, $call->coach_id);
        $this->assertSame($u->id, $call->patient_id);
        $this->assertSame('booked', $call->status);
        $this->assertSame('welcome', $call->purpose);
        $this->assertSame(15, $call->length_minutes);
        $this->assertSame('2026-11-25 06:30:00', $call->fresh()->starts_at->format('Y-m-d H:i:s'), '08:30 SAST is 06:30 UTC');
        $this->assertNotNull($call->slot_key);
        $this->assertSame('Wed 25 Nov, 08:30', $this->svc()->when($call));
    }

    public function test_unknown_purposes_are_dropped(): void
    {
        $this->assertNull($this->svc()->book($this->member(), $this->sast('2026-11-25 09:00'), 30, 'anything')->purpose);
    }

    public function test_times_that_are_not_offered_are_refused(): void
    {
        $bad = [
            ['2026-11-25 09:10', 30, 'not on the half hour'],
            ['2026-11-25 07:30', 30, 'before hours'],
            ['2026-11-25 16:30', 30, 'runs past the end'],
            ['2026-11-25 12:00', 30, 'lunch break'],
            ['2026-11-24 11:00', 30, 'less than 2 hours away'],
            ['2026-11-24 09:00', 30, 'in the past'],
            ['2026-11-28 10:00', 30, 'a Saturday'],
            ['2026-12-08 10:00', 30, 'beyond the 5 working days'],
            ['2026-11-25 10:00', 20, 'a length that is not offered'],
        ];

        foreach ($bad as [$when, $len, $why]) {
            try {
                $this->svc()->book($this->member(), $this->sast($when), $len);
                $this->fail("{$why} should be refused");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, CoachCall::count());
    }

    public function test_a_member_holds_one_upcoming_call_at_a_time(): void
    {
        $u = $this->member();
        $this->svc()->book($u, $this->sast('2026-11-25 09:00'), 30);

        try {
            $this->svc()->book($u, $this->sast('2026-11-26 09:00'), 30);
            $this->fail('Second call should be refused');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already have a call booked', $e->getMessage());
        }
        $this->assertSame(1, CoachCall::count());
    }

    public function test_two_members_cannot_take_the_same_coach_slot(): void
    {
        $coach = $this->coach();
        $a = $this->member($coach);
        $b = $this->member($coach);

        $this->svc()->book($a, $this->sast('2026-11-25 09:00'), 30);

        try {
            $this->svc()->book($b, $this->sast('2026-11-25 09:00'), 15);
            $this->fail('Slot is taken');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('just taken', $e->getMessage());
        }
        $this->assertSame(1, CoachCall::count());
    }

    public function test_the_database_stops_a_same_moment_double_booking_even_if_the_check_passes(): void
    {
        $coach = $this->coach();
        $a = $this->member($coach);
        $b = $this->member($coach);
        // A leftover key with a cancelled status: the availability check passes but the unique key must still refuse.
        CoachCall::create(['patient_id' => $a->id, 'coach_id' => $coach->id, 'starts_at' => $this->sast('2026-11-25 09:00')->utc(),
            'length_minutes' => 30, 'status' => 'cancelled', 'slot_key' => $coach->id.':202611250700']);

        try {
            $this->svc()->book($b, $this->sast('2026-11-25 09:00'), 30);
            $this->fail('Unique key should refuse');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('just taken', $e->getMessage());
        }
        $this->assertSame(0, CoachCall::where('patient_id', $b->id)->count());
    }

    // ---- cancelling ---------------------------------------------------------------------------

    public function test_a_member_cancels_their_own_call_and_the_slot_opens_again(): void
    {
        $coach = $this->coach();
        $a = $this->member($coach);
        $b = $this->member($coach);
        $call = $this->svc()->book($a, $this->sast('2026-11-25 09:00'), 30);

        $this->assertTrue($this->svc()->cancel($a, $call->id));

        $call->refresh();
        $this->assertSame('cancelled', $call->status);
        $this->assertNull($call->slot_key);
        $this->assertNull($this->svc()->upcomingFor($a));
        $this->svc()->book($b, $this->sast('2026-11-25 09:00'), 30);   // free again
        $this->svc()->book($a, $this->sast('2026-11-26 09:00'), 30);   // and the member can book again
        $this->assertSame(3, CoachCall::count());
    }

    public function test_nobody_can_cancel_someone_elses_or_a_past_call(): void
    {
        $coach = $this->coach();
        $a = $this->member($coach);
        $b = $this->member($coach);
        $call = $this->svc()->book($a, $this->sast('2026-11-25 09:00'), 30);

        $this->assertFalse($this->svc()->cancel($b, $call->id));
        $this->assertSame('booked', $call->fresh()->status);

        $past = CoachCall::create(['patient_id' => $b->id, 'coach_id' => $coach->id, 'starts_at' => now()->subHour(), 'length_minutes' => 15, 'status' => 'booked']);
        $this->assertFalse($this->svc()->cancel($b, $past->id));
    }

    // ---- messages -----------------------------------------------------------------------------

    public function test_no_messages_while_the_call_switch_is_off(): void
    {
        config(['coach.calls.notify_enabled' => false]);
        $u = $this->member();

        $this->svc()->book($u, $this->sast('2026-11-25 09:00'), 30);

        $this->assertSame([], $this->sent);
    }

    public function test_confirmation_goes_to_the_member_and_the_coach_with_the_time_only(): void
    {
        config(['coach.calls.notify_enabled' => true, 'services.whatsapp.token' => null]);
        $coach = $this->coach();
        $u = $this->member($coach);

        $this->svc()->book($u, $this->sast('2026-11-25 14:30'), 30);

        $this->assertCount(2, $this->sent);
        $toMember = collect($this->sent)->first(fn ($s) => $s['message']->user->id === $u->id);
        $toCoach = collect($this->sent)->first(fn ($s) => $s['message']->user->id === $coach->id);
        $this->assertStringContainsString('Wed 25 Nov, 14:30', $toMember['message']->body);
        $this->assertStringContainsString('30 minutes', $toMember['message']->body);
        $this->assertSame('email', $toMember['channel'], 'WhatsApp is not configured, so email');
        $this->assertStringContainsString('Wed 25 Nov, 14:30', $toCoach['message']->body);
        foreach ($this->sent as $s) {
            foreach (['symptom', 'medication', 'weight', 'dose', 'pain'] as $word) {
                $this->assertStringNotContainsString($word, strtolower($s['message']->body));
            }
        }
    }

    public function test_cancelling_tells_the_coach_when_switched_on(): void
    {
        config(['coach.calls.notify_enabled' => true]);
        $coach = $this->coach();
        $u = $this->member($coach);
        $call = $this->svc()->book($u, $this->sast('2026-11-25 14:30'), 30);
        $this->sent = [];

        $this->svc()->cancel($u, $call->id);

        $this->assertCount(1, $this->sent);
        $this->assertSame($coach->id, $this->sent[0]['message']->user->id);
        $this->assertStringContainsString('cancelled', $this->sent[0]['message']->body);
    }

    public function test_the_reminder_goes_out_an_hour_before_once(): void
    {
        config(['coach.calls.notify_enabled' => true]);
        $coach = $this->coach();
        $soon = $this->member($coach);
        $later = $this->member($coach);
        $this->svc()->book($soon, $this->sast('2026-11-24 13:00'), 15);     // 3 hours away when booked
        $this->svc()->book($later, $this->sast('2026-11-25 09:00'), 30);
        $this->sent = [];

        $this->travelTo($this->sast('2026-11-24 12:10'));                    // 50 minutes before
        $this->assertSame(1, $this->svc()->sendDueReminders());
        $this->assertSame($soon->id, $this->sent[0]['message']->user->id);
        $this->assertStringContainsString('at 13:00', $this->sent[0]['message']->body);

        $this->assertSame(0, $this->svc()->sendDueReminders(), 'Only once');
        $this->assertNotNull(CoachCall::where('patient_id', $soon->id)->value('reminded_at'));
        $this->assertNull(CoachCall::where('patient_id', $later->id)->value('reminded_at'));
    }

    public function test_no_reminders_while_the_switch_is_off_and_none_for_cancelled_calls(): void
    {
        config(['coach.calls.notify_enabled' => false]);
        $u = $this->member();
        $this->svc()->book($u, $this->sast('2026-11-24 13:00'), 15);
        $this->travelTo($this->sast('2026-11-24 12:10'));
        $this->assertSame(0, $this->svc()->sendDueReminders());

        config(['coach.calls.notify_enabled' => true]);
        CoachCall::query()->update(['status' => 'cancelled']);
        $this->assertSame(0, $this->svc()->sendDueReminders());
    }

    public function test_the_reminder_command_covers_calls_and_says_so_when_everything_is_off(): void
    {
        config(['coach.reminder_sending.enabled' => false, 'coach.calls.notify_enabled' => false]);
        $this->artisan('coach:send-reminders')->expectsOutputToContain('switched off')->assertSuccessful();

        config(['coach.calls.notify_enabled' => true]);
        $u = $this->member();
        $this->svc()->book($u, $this->sast('2026-11-24 13:00'), 15);
        $this->travelTo($this->sast('2026-11-24 12:10'));
        $this->artisan('coach:send-reminders')->expectsOutputToContain('1 call reminder')->assertSuccessful();
    }

    // ---- the screen ---------------------------------------------------------------------------

    public function test_the_screen_offers_five_days_two_lengths_and_greys_taken_times(): void
    {
        $coach = $this->coach();
        $u = $this->member($coach);
        $other = $this->member($coach);
        $this->svc()->book($other, $this->sast('2026-11-24 14:00'), 30);

        $html = Livewire::actingAs($u)->test(BookCall::class)
            ->assertSee('Book a call with Amy')->assertSee('15 min')->assertSee('30 min')
            ->assertSeeHtml('data-day="2026-11-30"')->assertDontSeeHtml('data-day="2026-12-01"')
            ->assertSee('Greyed times are already taken')
            ->html();

        $this->assertMatchesRegularExpression('/data-slot="14:00"[^>]*disabled/', $html);
        $this->assertMatchesRegularExpression('/data-slot="09:00"[^>]*disabled/', $html);      // under 2 hours' notice
        $this->assertDoesNotMatchRegularExpression('/data-slot="14:30"[^>]*disabled/', $html);
    }

    public function test_picking_and_confirming_books_the_call_and_goes_back_to_today(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(BookCall::class)
            ->call('pickDate', '2026-11-25')->call('pickLength', 30)->call('pickSlot', '10:30')
            ->assertSet('slot', '10:30')
            ->call('confirm')
            ->assertRedirect(route('coach.today'));

        $call = CoachCall::where('patient_id', $u->id)->firstOrFail();
        $this->assertSame(30, $call->length_minutes);
        $this->assertSame('Wed 25 Nov, 10:30', $this->svc()->when($call));
    }

    public function test_confirming_without_a_choice_asks_for_one_and_a_stolen_slot_shows_a_clear_message(): void
    {
        $coach = $this->coach();
        $a = $this->member($coach);
        $b = $this->member($coach);

        $c = Livewire::actingAs($a)->test(BookCall::class)->call('confirm')->assertSee('Please pick a day and a time.');

        $c->call('pickDate', '2026-11-25')->call('pickSlot', '09:00');
        $this->svc()->book($b, $this->sast('2026-11-25 09:00'), 15);     // someone else gets there first
        $c->call('confirm')->assertSee('just taken')->assertSet('slot', null);

        $this->assertSame(0, CoachCall::where('patient_id', $a->id)->count());
    }

    public function test_unknown_days_and_lengths_are_ignored(): void
    {
        Livewire::actingAs($this->member())->test(BookCall::class)
            ->call('pickDate', '2026-12-25')->assertSet('date', '2026-11-24')
            ->call('pickLength', 45)->assertSet('length', 15);
    }

    public function test_a_member_with_a_booked_call_sees_it_and_can_cancel_it(): void
    {
        $u = $this->member();
        $this->svc()->book($u, $this->sast('2026-11-25 09:00'), 30);

        Livewire::actingAs($u)->test(BookCall::class)
            ->assertSee('Your call is booked')->assertSee('Wed 25 Nov, 09:00')->assertSee('one call at a time')
            ->assertDontSeeHtml('data-slots')
            ->call('cancel', CoachCall::first()->id)
            ->assertSee('Confirm booking');

        $this->assertSame('cancelled', CoachCall::first()->status);
    }

    public function test_without_a_coach_the_screen_explains(): void
    {
        Livewire::actingAs($this->member(null, false))->test(BookCall::class)
            ->assertSee('Your coach will be assigned soon')->assertDontSee('Confirm booking');
    }

    // ---- entry points -------------------------------------------------------------------------

    public function test_the_coach_menu_links_to_booking_only_when_there_is_a_coach(): void
    {
        Livewire::actingAs($this->member())->test(CoachSheet::class)->assertSeeHtml('href="'.route('coach.book').'"');
        Livewire::actingAs($this->member(null, false))->test(CoachSheet::class)->assertDontSeeHtml('href="'.route('coach.book').'"');
    }

    public function test_onboarding_offers_a_welcome_call_and_it_is_remembered_as_one(): void
    {
        $u = User::factory()->create(['role' => UserRole::Patient, 'gender' => 'female']);
        PatientProfile::create(['user_id' => $u->id, 'onboarding_complete' => true]);
        $coach = $this->coach();
        CoachingAssignment::create(['patient_id' => $u->id, 'coach_id' => $coach->id, 'started_at' => now()]);

        Livewire::actingAs($u)->test(Onboarding::class)
            ->call('chooseWorry', 'cost')->call('next')
            ->assertSee('Book a welcome call')
            ->call('finishAndBook')
            ->assertRedirect(route('coach.book', ['for' => 'welcome']));

        $this->assertNotNull(CoachProfile::where('user_id', $u->id)->value('onboarded_at'));

        Livewire::actingAs($u)->withQueryParams(['for' => 'welcome'])->test(BookCall::class)
            ->call('pickDate', '2026-11-25')->call('pickSlot', '09:00')->call('confirm');

        $this->assertSame('welcome', CoachCall::where('patient_id', $u->id)->value('purpose'));
    }

    public function test_onboarding_without_a_coach_only_offers_the_tracker(): void
    {
        $u = User::factory()->create(['role' => UserRole::Patient, 'gender' => 'female']);
        PatientProfile::create(['user_id' => $u->id, 'onboarding_complete' => true]);

        Livewire::actingAs($u)->test(Onboarding::class)->call('chooseWorry', 'cost')->call('next')
            ->assertDontSee('Book a welcome call')->assertSee('Go to my tracker');
    }

    public function test_today_shows_the_next_call_and_the_coach_inbox_lists_upcoming_calls(): void
    {
        $coach = $this->coach();
        $u = $this->member($coach);
        $u->update(['first_name' => 'Sam', 'last_name' => 'Jones']);
        $this->svc()->book($u, $this->sast('2026-11-25 09:00'), 30, 'check_in');

        Livewire::actingAs($u)->test(Today::class)->assertSee('Your call')->assertSee('Wed 25 Nov, 09:00')->assertSeeHtml('data-card="call"');
        Livewire::actingAs($coach)->test(Inbox::class)->assertSee('Upcoming calls')->assertSee('Sam Jones')->assertSee('Wed 25 Nov, 09:00')->assertSee('check in');
        Livewire::actingAs($this->coach())->test(Inbox::class)->assertDontSee('Sam Jones');
    }

    public function test_the_booking_page_needs_the_add_on(): void
    {
        $plain = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $plain->id, 'onboarding_complete' => true]);

        $this->actingAs($plain)->get('/coach/book')->assertRedirect('/weight-loss');
        $this->actingAs($this->member())->get('/coach/book')->assertOk()->assertSee('Book a call');
    }
}
