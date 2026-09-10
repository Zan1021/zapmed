<?php

namespace Tests\Feature;

use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Services\Channels\WhatsAppChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WhatsApp channel (Meta Cloud API direct) — log-driver behaviour.
 *
 * Verifies the channel the client chose as PRIMARY works end-to-end on the
 * safe 'log' driver (no creds, no network) BEFORE Meta onboarding completes:
 *   - it's reachable only when enabled + patient has a phone,
 *   - a proactive send (outside the 24h window) with an approved template
 *     renders a correct Cloud API TEMPLATE payload (name, E.164 to, link button),
 *   - a proactive send with NO approved template returns false so the
 *     dispatcher falls back to SMS (Meta would reject a free-form proactive msg).
 */
class WhatsAppChannelTest extends TestCase
{
    use RefreshDatabase;

    private function patient(array $overrides = []): SparPatient
    {
        static $seq = 0;
        $seq++;

        $pharmacy = SparPharmacy::create([
            'name' => 'Test SPAR', 'spar_store_id' => 'T-' . $seq, 'is_active' => true,
        ]);

        return SparPatient::create(array_merge([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => 'P-1', 'dependent_code' => '0',
            'first_name' => 'Michelle', 'last_name' => 'Visser',
            'cellphone' => '0710201481',
            'is_primary_member' => true, 'is_active' => true,
            'onboarding_status' => 'active', 'consent_status' => 'opted_in',
            'consent_given_at' => now(),
        ], $overrides));
    }

    public function test_channel_is_registered_and_keyed_whatsapp(): void
    {
        config(['spar.whatsapp.enabled' => true, 'spar.whatsapp.driver' => 'log']);
        $channel = new WhatsAppChannel();
        $this->assertSame('whatsapp', $channel->key());
    }

    public function test_can_reach_requires_enabled_and_phone(): void
    {
        $channel = new WhatsAppChannel();

        config(['spar.whatsapp.enabled' => false]);
        $this->assertFalse($channel->canReach($this->patient()));

        config(['spar.whatsapp.enabled' => true]);
        $this->assertTrue($channel->canReach($this->patient()));

        config(['spar.whatsapp.enabled' => true]);
        $this->assertFalse($channel->canReach($this->patient(['cellphone' => null, 'email' => 'a@b.test'])));
    }

    public function test_proactive_send_with_approved_template_logs_template_payload(): void
    {
        config([
            'spar.whatsapp.enabled' => true,
            'spar.whatsapp.driver' => 'log',
            'spar.whatsapp.language' => 'en',
            'spar.whatsapp.templates.onboarding_consent' => 'spar_onboarding_consent',
            // Force the 'stack' branch of Log::channel() so we can capture it.
            'logging.channels.spar_audit' => null,
        ]);

        $captured = null;
        $logger = \Mockery::mock();
        $logger->shouldReceive('info')->once()->andReturnUsing(function ($msg, $ctx) use (&$captured) {
            $captured = ['msg' => $msg, 'ctx' => $ctx];
        });
        \Illuminate\Support\Facades\Log::shouldReceive('channel')->with('stack')->andReturn($logger);

        $channel = new WhatsAppChannel();
        $ok = $channel->send($this->patient(), [
            'template' => 'onboarding_consent',
            'vars' => ['Michelle'],
            'link' => 'https://sparmeds.test/track/6?signature=abc',
        ]);

        $this->assertTrue($ok);
        $this->assertNotNull($captured, 'log driver should have logged the payload');

        $m = $captured['ctx']['message'] ?? [];
        $this->assertStringContainsString('WhatsApp', $captured['msg']);
        $this->assertSame('template', $m['type'] ?? null);
        $this->assertSame('spar_onboarding_consent', $m['template']['name'] ?? null);
        $this->assertSame('27710201481', $m['to'] ?? null); // 0710201481 → E.164

        // Link is carried as a URL button parameter.
        $components = $m['template']['components'] ?? [];
        $buttonParam = collect($components)
            ->firstWhere('type', 'button')['parameters'][0]['text'] ?? null;
        $this->assertSame('https://sparmeds.test/track/6?signature=abc', $buttonParam);
    }

    public function test_proactive_send_without_template_returns_false_for_sms_fallback(): void
    {
        config([
            'spar.whatsapp.enabled' => true,
            'spar.whatsapp.driver' => 'log',
            'spar.whatsapp.templates' => [], // none approved yet
        ]);

        $channel = new WhatsAppChannel();
        $ok = $channel->send($this->patient(), [
            'body' => 'Your repeat is ready.',
            'link' => 'https://sparmeds.test/track/6',
        ]);

        // No template + outside 24h window → do not send free-form; let dispatcher fall back.
        $this->assertFalse($ok);
    }
}
