<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ConsentRecord;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\CommunicationPreferenceService;
use App\Services\Notifications\ChannelResult;
use App\Services\Notifications\Channels\WhatsAppChannel;
use App\Services\Notifications\Contracts\NotificationChannel;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * UAT Task 8 — the single notifications choke point.
 *
 * Guards: preference gating (transactional always sends, marketing respects
 * opt-out), one NotificationLog row per attempt with correct status, failures
 * are recorded not thrown, unknown channels are skipped, and the WhatsApp
 * channel no-ops cleanly (logs) until Meta creds are configured.
 */
class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private function fakeChannel(string $key, bool $ok = true, string $provider = 'fake'): NotificationChannel
    {
        return new class($key, $ok, $provider) implements NotificationChannel {
            public int $calls = 0;

            public function __construct(
                private string $keyName,
                private bool $ok,
                private string $provider,
            ) {}

            public function key(): string { return $this->keyName; }
            public function isConfigured(): bool { return true; }

            public function send(OutboundMessage $message): ChannelResult
            {
                $this->calls++;

                return $this->ok
                    ? ChannelResult::sent($this->provider, 'ref-123')
                    : ChannelResult::failed($this->provider, 'boom');
            }
        };
    }

    private function dispatcherWith(NotificationChannel ...$channels): NotificationDispatcher
    {
        // Build with real deps then swap in fakes via register().
        $dispatcher = app(NotificationDispatcher::class);
        foreach ($channels as $c) {
            $dispatcher->register($c);
        }

        return $dispatcher;
    }

    private function msg(User $user, string $category = 'transactional', string $phone = '0821234567'): OutboundMessage
    {
        return new OutboundMessage(
            templateKey: 'test.message',
            category: $category,
            user: $user,
            email: $user->email,
            phone: $phone,
            subject: 'Test',
            body: 'Hello',
            meta: ['k' => 'v'],
        );
    }

    public function test_successful_send_writes_a_sent_log_row(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        $fake = $this->fakeChannel('email');
        $dispatcher = $this->dispatcherWith($fake);

        $log = $dispatcher->sendVia($this->msg($user), 'email');

        $this->assertSame(NotificationLog::STATUS_SENT, $log->status);
        $this->assertSame('email', $log->channel);
        $this->assertSame('test.message', $log->template_key);
        $this->assertSame('ref-123', $log->provider_ref);
        $this->assertSame(['k' => 'v'], $log->meta);
        $this->assertSame(1, $fake->calls);
    }

    public function test_failed_send_is_recorded_not_thrown(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        $dispatcher = $this->dispatcherWith($this->fakeChannel('email', ok: false));

        $log = $dispatcher->sendVia($this->msg($user), 'email');

        $this->assertSame(NotificationLog::STATUS_FAILED, $log->status);
        $this->assertSame('boom', $log->error);
    }

    public function test_transactional_always_sends_even_with_marketing_opt_out(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        app(CommunicationPreferenceService::class)->optOutOfMarketing($user);

        $dispatcher = $this->dispatcherWith($this->fakeChannel('email'));

        $log = $dispatcher->sendVia($this->msg($user, 'transactional'), 'email');

        $this->assertSame(NotificationLog::STATUS_SENT, $log->status);
    }

    public function test_marketing_is_suppressed_when_opted_out(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        app(CommunicationPreferenceService::class)->optOutOfMarketing($user);

        $fake = $this->fakeChannel('email');
        $dispatcher = $this->dispatcherWith($fake);

        $log = $dispatcher->sendVia($this->msg($user, 'marketing'), 'email');

        $this->assertSame(NotificationLog::STATUS_SUPPRESSED, $log->status);
        $this->assertSame(0, $fake->calls, 'Channel must not be called when suppressed.');
    }

    public function test_marketing_sends_when_not_opted_out(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        $dispatcher = $this->dispatcherWith($this->fakeChannel('email'));

        $log = $dispatcher->sendVia($this->msg($user, 'marketing'), 'email');

        $this->assertSame(NotificationLog::STATUS_SENT, $log->status);
    }

    public function test_unknown_channel_is_skipped(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        $dispatcher = app(NotificationDispatcher::class);

        $log = $dispatcher->sendVia($this->msg($user), 'carrier_pigeon');

        $this->assertSame(NotificationLog::STATUS_SKIPPED, $log->status);
    }

    public function test_multi_channel_send_writes_one_row_per_channel(): void
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        $dispatcher = $this->dispatcherWith(
            $this->fakeChannel('email'),
            $this->fakeChannel('sms'),
        );

        $logs = $dispatcher->send($this->msg($user), ['email', 'sms']);

        $this->assertCount(2, $logs);
        $this->assertSame(2, NotificationLog::count());
    }

    public function test_whatsapp_channel_noops_and_reports_sent_without_creds(): void
    {
        config()->set('services.whatsapp.token', null);
        config()->set('services.whatsapp.phone_number_id', null);
        Log::spy();

        $channel = new WhatsAppChannel();
        $this->assertFalse($channel->isConfigured());

        $user = User::factory()->create(['role' => UserRole::Patient]);
        $result = $channel->send($this->msg($user, phone: '0821234567'));

        $this->assertTrue($result->ok);
        $this->assertSame('log', $result->provider);
        Log::shouldHaveReceived('info')->once();
    }

    public function test_whatsapp_channel_fails_cleanly_without_phone(): void
    {
        $channel = new WhatsAppChannel();
        $user = User::factory()->create(['role' => UserRole::Patient]);

        $result = $channel->send($this->msg($user, phone: ''));

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('No phone number', (string) $result->error);
    }
}
