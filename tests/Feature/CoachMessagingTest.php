<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coach\CoachSheet;
use App\Livewire\Coach\Inbox;
use App\Livewire\Coach\Messages;
use App\Livewire\Coach\Thread;
use App\Models\CoachFlag;
use App\Models\CoachingAssignment;
use App\Models\CoachMessage;
use App\Models\CoachProfile;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Coach\CoachMessaging;
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
 * Coach App 1.8: the coach menu and the in-app thread between a member and their coach.
 * 2026-11-24 is a Tuesday.
 */
class CoachMessagingTest extends TestCase
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

    private function member(bool $withCoach = true, ?User &$coach = null): User
    {
        $user = User::factory()->create(['role' => UserRole::Patient, 'gender' => 'female', 'phone' => '0821234567']);
        PatientProfile::create(['user_id' => $user->id, 'onboarding_complete' => true]);
        CoachProfile::create(['user_id' => $user->id, 'onboarded_at' => now()]);

        if ($withCoach) {
            $coach = User::factory()->create(['role' => UserRole::HealthCoach, 'first_name' => 'Amy']);
            CoachingAssignment::create(['patient_id' => $user->id, 'coach_id' => $coach->id, 'started_at' => now()]);
        }

        return $user;
    }

    private function svc(): CoachMessaging
    {
        return app(CoachMessaging::class);
    }

    // ---- the service --------------------------------------------------------------------------

    public function test_a_member_without_a_coach_cannot_send(): void
    {
        $u = $this->member(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('assigned soon');
        $this->svc()->sendFromPatient($u, 'Hello');
    }

    public function test_messages_are_tidied_and_limited(): void
    {
        $u = $this->member();

        $m = $this->svc()->sendFromPatient($u, "  Hello Amy \r\n how are you  ");
        $this->assertSame("Hello Amy \n how are you", $m->body);
        $this->assertSame('patient', $m->from);
        $this->assertSame($u->id, $m->sender_id);

        foreach (['', '   ', "\n\n", str_repeat('x', 1001)] as $bad) {
            try {
                $this->svc()->sendFromPatient($u, $bad);
                $this->fail('Should reject');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->svc()->sendFromPatient($u, str_repeat('x', 1000)); // exactly the maximum is fine
        $this->assertSame(2, CoachMessage::count());
    }

    public function test_a_member_is_limited_to_20_messages_an_hour(): void
    {
        $u = $this->member();
        for ($i = 0; $i < 20; $i++) {
            $this->svc()->sendFromPatient($u, "Message {$i}");
        }

        try {
            $this->svc()->sendFromPatient($u, 'One too many');
            $this->fail('Should be limited');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('a lot of messages', $e->getMessage());
        }

        $this->travel(61)->minutes();
        $this->svc()->sendFromPatient($u, 'Back again');
        $this->assertSame(21, CoachMessage::count());
    }

    public function test_only_the_assigned_coach_can_message_a_member(): void
    {
        $u = $this->member(true, $coach);
        $other = User::factory()->create(['role' => UserRole::HealthCoach]);

        $m = $this->svc()->sendFromCoach($coach, $u, 'Hi there');
        $this->assertSame('coach', $m->from);
        $this->assertSame($coach->id, $m->sender_id);

        $this->expectException(InvalidArgumentException::class);
        $this->svc()->sendFromCoach($other, $u, 'I am not your coach');
    }

    public function test_reading_marks_only_the_other_sides_messages_and_only_for_the_right_coach(): void
    {
        $u = $this->member(true, $coach);
        $other = User::factory()->create(['role' => UserRole::HealthCoach]);
        $this->svc()->sendFromPatient($u, 'Question');
        $this->svc()->sendFromCoach($coach, $u, 'Answer');

        $this->assertSame(1, $this->svc()->unreadForPatient($u));
        $this->assertSame(1, $this->svc()->unreadForCoach($coach));

        $this->svc()->markReadByCoach($other, $u);
        $this->assertSame(1, $this->svc()->unreadForCoach($coach), 'A different coach cannot mark it read');

        $this->svc()->markReadByCoach($coach, $u);
        $this->assertSame(0, $this->svc()->unreadForCoach($coach));
        $this->assertSame(1, $this->svc()->unreadForPatient($u), 'Coach opening the thread does not read the coach own message');

        $this->svc()->markReadByPatient($u);
        $this->assertSame(0, $this->svc()->unreadForPatient($u));
    }

    public function test_online_means_inside_the_published_reply_hours(): void
    {
        $at = fn (string $dt) => CarbonImmutable::parse($dt, 'Africa/Johannesburg');

        $this->assertTrue($this->svc()->isOnline($at('2026-11-24 10:00')));  // Tuesday
        $this->assertTrue($this->svc()->isOnline($at('2026-11-24 08:00')));
        $this->assertFalse($this->svc()->isOnline($at('2026-11-24 07:59')));
        $this->assertFalse($this->svc()->isOnline($at('2026-11-24 17:00')));
        $this->assertFalse($this->svc()->isOnline($at('2026-11-28 10:00')));  // Saturday
        $this->assertFalse($this->svc()->isOnline($at('2026-11-29 10:00')));  // Sunday
    }

    public function test_the_coach_inbox_lists_only_assigned_members_with_unread_counts(): void
    {
        $a = $this->member(true, $coach);
        $b = $this->member(false);
        CoachingAssignment::create(['patient_id' => $b->id, 'coach_id' => $coach->id, 'started_at' => now()]);
        $stranger = $this->member(true, $otherCoach);

        $this->svc()->sendFromPatient($a, 'One');
        $this->svc()->sendFromPatient($a, 'Two');
        $this->travel(1)->minutes();
        $this->svc()->sendFromPatient($b, 'Hello from b');
        $this->svc()->sendFromPatient($stranger, 'Not yours');

        $inbox = $this->svc()->inboxFor($coach);

        $this->assertSame([$b->id, $a->id], $inbox->pluck('patient.id')->all(), 'Newest activity first');
        $this->assertSame([1, 2], $inbox->pluck('unread')->all());
        $this->assertSame(3, $this->svc()->unreadForCoach($coach));

        CoachingAssignment::where('patient_id', $a->id)->update(['ended_at' => now()]);
        $this->assertSame([$b->id], $this->svc()->inboxFor($coach)->pluck('patient.id')->all(), 'An ended assignment drops out');
    }

    // ---- notifications ------------------------------------------------------------------------

    public function test_nobody_is_notified_while_the_message_switch_is_off(): void
    {
        config(['coach.messaging.notify_enabled' => false]);
        $u = $this->member(true, $coach);

        $this->svc()->sendFromPatient($u, 'Hello');
        $this->svc()->sendFromCoach($coach, $u, 'Hi');

        $this->assertSame([], $this->sent);
    }

    public function test_the_first_unread_message_notifies_with_no_message_text_and_the_next_ones_do_not(): void
    {
        config(['coach.messaging.notify_enabled' => true]);
        $u = $this->member(true, $coach);

        $this->svc()->sendFromPatient($u, 'My secret symptom text');
        $this->assertCount(1, $this->sent);
        $this->assertSame($coach->id, $this->sent[0]['message']->user->id);
        $this->assertSame('email', $this->sent[0]['channel']);

        $this->svc()->sendFromPatient($u, 'Another one');
        $this->assertCount(1, $this->sent, 'Still unread: no second notification');

        $this->svc()->markReadByCoach($coach, $u);
        $this->svc()->sendFromPatient($u, 'After reading');
        $this->assertCount(2, $this->sent, 'Read, so a new message notifies again');

        foreach ($this->sent as $s) {
            $text = ($s['message']->subject ?? '').' '.($s['message']->body ?? '');
            $this->assertStringNotContainsString('secret', $text);
            $this->assertStringNotContainsString('symptom', $text);
        }
    }

    public function test_a_coach_reply_notifies_the_member_on_their_channel_without_the_text(): void
    {
        config(['coach.messaging.notify_enabled' => true, 'services.whatsapp.token' => null]);
        $u = $this->member(true, $coach);

        $this->svc()->sendFromCoach($coach, $u, 'Please increase your protein');

        $this->assertCount(1, $this->sent);
        $this->assertSame($u->id, $this->sent[0]['message']->user->id);
        $this->assertSame('email', $this->sent[0]['channel'], 'WhatsApp is not configured, so email');
        $this->assertStringContainsString('Amy sent you a message', $this->sent[0]['message']->body);
        $this->assertStringNotContainsString('protein', $this->sent[0]['message']->body);
    }

    // ---- the member screen --------------------------------------------------------------------

    public function test_member_can_send_and_see_messages_and_the_hours_notice(): void
    {
        $u = $this->member(true, $coach);

        Livewire::actingAs($u)->test(Messages::class)
            ->assertSee('Amy')->assertSee('Online now')->assertSee('No messages yet')
            ->assertSee('Messages are answered on weekdays from 08:00 to 17:00')
            ->set('draft', 'Hi Amy, I have a question')->call('send')
            ->assertSet('draft', '')->assertSee('Hi Amy, I have a question')->assertSee('Tue 10:00');

        $this->assertSame(1, CoachMessage::where('patient_id', $u->id)->count());
    }

    public function test_blank_message_shows_an_error_and_sends_nothing(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(Messages::class)->set('draft', '   ')->call('send')->assertSee('Please type a message.');

        $this->assertSame(0, CoachMessage::count());
    }

    public function test_opening_the_thread_marks_coach_messages_read(): void
    {
        $u = $this->member(true, $coach);
        $this->svc()->sendFromCoach($coach, $u, 'Welcome!');
        $this->assertSame(1, $this->svc()->unreadForPatient($u));

        Livewire::actingAs($u)->test(Messages::class)->assertSee('Welcome!');

        $this->assertSame(0, $this->svc()->unreadForPatient($u));
    }

    public function test_without_a_coach_the_thread_explains_and_has_no_box_to_type_in(): void
    {
        Livewire::actingAs($this->member(false))->test(Messages::class)
            ->assertSee('Your coach will be assigned soon')
            ->assertDontSee('Type your message')
            ->assertDontSeeHtml('wire:click="send"');
    }

    public function test_offline_hours_say_replies_come_within_a_working_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00', 'Africa/Johannesburg')); // Saturday

        Livewire::actingAs($this->member())->test(Messages::class)->assertSee('Replies within one working day')->assertDontSee('Online now');
    }

    // ---- the coach menu -----------------------------------------------------------------------

    public function test_the_menu_has_the_four_options_and_links_to_messages_when_there_is_a_coach(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(CoachSheet::class)
            ->assertSee('How can we help?')->assertSee('Amy is')->assertSee('Online now')
            ->assertSee('Quick question')->assertSee('Message Amy')->assertSee('Book a call')->assertSee('Something feels wrong')
            ->assertSee('Straight to a doctor, no queue')
            ->assertSeeHtml('href="'.route('coach.messages').'"');
    }

    public function test_the_menu_without_a_coach_does_not_link_to_messages(): void
    {
        Livewire::actingAs($this->member(false))->test(CoachSheet::class)
            ->assertSee('Your coach will be assigned soon')
            ->assertDontSeeHtml('href="'.route('coach.messages').'"');
    }

    public function test_the_menu_shows_unread_replies(): void
    {
        $u = $this->member(true, $coach);
        $this->svc()->sendFromCoach($coach, $u, 'One');
        $this->svc()->sendFromCoach($coach, $u, 'Two');

        Livewire::actingAs($u)->test(CoachSheet::class)->assertSee('2 new');
    }

    public function test_something_feels_wrong_goes_straight_to_a_doctor_and_confirms(): void
    {
        $u = $this->member();

        Livewire::actingAs($u)->test(CoachSheet::class)
            ->call('feelsWrong')
            ->assertSet('doctorReached', true)
            ->assertSee('Dr Anke has been alerted again')
            ->assertSee('call 112 or 10177')
            ->assertDontSee('Straight to a doctor, no queue');

        $flag = CoachFlag::where('patient_id', $u->id)->firstOrFail();
        $this->assertSame('self_report', $flag->source);
        $this->assertSame('open', $flag->status);
    }

    public function test_the_coach_tab_opens_the_menu_and_shows_a_badge_for_unread_replies(): void
    {
        $u = $this->member(true, $coach);
        $this->svc()->sendFromCoach($coach, $u, 'Hello');

        $html = $this->actingAs($u)->get('/coach')->assertOk()->getContent();

        $this->assertStringContainsString("@click=\"sheet = 'coach'\"", $html);
        $this->assertStringContainsString('data-coach-sheet', $html);
        $this->assertMatchesRegularExpression('/data-badge[^>]*>\s*1\s*</', $html);
    }

    public function test_the_menu_is_not_on_screens_without_the_tab_bar(): void
    {
        $html = $this->actingAs($this->member())->get('/coach/reminders')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-coach-sheet', $html);
    }

    // ---- staff --------------------------------------------------------------------------------

    public function test_the_coach_sees_the_inbox_opens_a_thread_and_replies(): void
    {
        $u = $this->member(true, $coach);
        $u->update(['first_name' => 'Sam', 'last_name' => 'Jones', 'member_number' => 'ZM-000555']);
        $this->svc()->sendFromPatient($u, 'Can we talk about snacks?');

        Livewire::actingAs($coach)->test(Inbox::class)->assertSee('Sam Jones')->assertSee('ZM-000555')->assertSee('1 new');

        Livewire::actingAs($coach)->test(Thread::class, ['patient' => $u])
            ->assertSee('Can we talk about snacks?')
            ->set('draft', 'Yes, tell me what you usually eat.')->call('reply')
            ->assertSet('draft', '')->assertSee('Yes, tell me what you usually eat.');

        $this->assertSame(0, $this->svc()->unreadForCoach($coach), 'Opening the thread read it');
        $this->assertSame(1, $this->svc()->unreadForPatient($u));
        Livewire::actingAs($coach)->test(Inbox::class)->assertDontSee('1 new');
    }

    public function test_another_coach_cannot_open_or_reply_to_the_thread(): void
    {
        $u = $this->member();
        $other = User::factory()->create(['role' => UserRole::HealthCoach]);

        Livewire::actingAs($other)->test(Thread::class, ['patient' => $u])->assertForbidden();
        $this->assertSame(0, CoachMessage::count());
    }

    public function test_staff_pages_are_for_coaches_only(): void
    {
        $u = $this->member(true, $coach);

        $this->actingAs($coach)->get('/coach-inbox')->assertOk();
        $this->actingAs($coach)->get('/coach-inbox/'.$u->id)->assertOk();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->get('/coach-inbox')->assertStatus(403);
        $this->actingAs(User::factory()->create(['role' => UserRole::Doctor]))->get('/coach-inbox')->assertStatus(403);
        $this->actingAs($u)->get('/coach-inbox')->assertStatus(403);
        $this->actingAs(User::factory()->create(['role' => UserRole::HealthCoach]))->get('/coach-inbox/'.$u->id)->assertStatus(403);
    }

    public function test_the_member_thread_needs_the_add_on(): void
    {
        $plain = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $plain->id, 'onboarding_complete' => true]);

        $this->actingAs($plain)->get('/coach/messages')->assertRedirect('/weight-loss');
        $this->actingAs($this->member())->get('/coach/messages')->assertOk();
    }
}
