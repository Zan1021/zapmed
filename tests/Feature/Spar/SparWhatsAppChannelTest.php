<?php

namespace Tests\Feature\Spar;

use App\Models\SparPatient;
use App\Models\SparPharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Zapmed\SparCore\Services\Channels\WhatsAppChannel;

/**
 * Phase 6.4 + 6.5 — WhatsAppChannel (Meta Cloud API direct).
 *
 * Proves the channel works WITHOUT Meta credentials (log driver) and, when
 * pointed at cloud_api, forms the correct request (asserted against a faked
 * HTTP layer — never hits Meta). Also proves the 24h session-window rule:
 * proactive sends outside the window require an approved template or defer.
 */
class SparWhatsAppChannelTest extends TestCase
{
    use RefreshDatabase;

    private function patient(array $overrides = []): SparPatient
    {
        $pharmacy = SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'is_active' => true,
        ]);

        return SparPatient::create(array_merge([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'cellphone' => '0821234567',
            'consent_status' => 'opted_in',
            'is_primary_member' => true,
            'is_active' => true,
        ], $overrides));
    }

    // ---- enable / reachability ----------------------------------------------

    public function test_not_reachable_when_disabled(): void
    {
        config(['spar.whatsapp.enabled' => false]);
        $this->assertFalseReach($this->patient());
    }

    public function test_reachable_when_enabled_and_has_phone(): void
    {
        config(['spar.whatsapp.enabled' => true, 'spar.whatsapp.driver' => 'log']);
        $this->assertTrue((new WhatsAppChannel())->canReach($this->patient()));
    }

    // ---- log driver: works with NO credentials -------------------------------

    public function test_log_driver_sends_without_credentials(): void
    {
        config([
            'spar.whatsapp.enabled' => true,
            'spar.whatsapp.driver' => 'log',
            'spar.whatsapp.templates.renewal_due' => 'spar_renewal_due_v1',
        ]);
        Http::fake(); // ensure NO real HTTP happens

        $sent = (new WhatsAppChannel())->send($this->patient(), [
            'template' => 'renewal_due',
            'body' => 'Your script is due for renewal.',
            'link' => 'https://track.test/abc',
            'vars' => ['Thabo', 'SPAR Mega City'],
        ]);

        $this->assertTrue($sent);
        Http::assertNothingSent();
    }

    // ---- cloud_api driver: correct request, faked HTTP -----------------------

    public function test_cloud_api_sends_template_outside_window(): void
    {
        config([
            'spar.whatsapp.enabled' => true,
            'spar.whatsapp.driver' => 'cloud_api',
            'spar.whatsapp.phone_number_id' => '123456',
            'spar.whatsapp.token' => 'TESTTOKEN',
            'spar.whatsapp.api_version' => 'v21.0',
            'spar.whatsapp.templates.renewal_due' => 'spar_renewal_due_v1',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TEST']]], 200),
        ]);

        $sent = (new WhatsAppChannel())->send($this->patient(), [
            'template' => 'renewal_due',
            'link' => 'https://track.test/abc',
            'vars' => ['Thabo'],
        ]);

        $this->assertTrue($sent);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v21.0/123456/messages')
                && $request['type'] === 'template'
                && $request['template']['name'] === 'spar_renewal_due_v1'
                && $request['to'] === '27821234567';          // E.164 normalised
        });
    }

    public function test_cloud_api_reports_false_on_api_error(): void
    {
        config([
            'spar.whatsapp.enabled' => true,
            'spar.whatsapp.driver' => 'cloud_api',
            'spar.whatsapp.phone_number_id' => '123456',
            'spar.whatsapp.token' => 'TESTTOKEN',
            'spar.whatsapp.templates.renewal_due' => 'spar_renewal_due_v1',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad template']], 400),
        ]);

        $sent = (new WhatsAppChannel())->send($this->patient(), ['template' => 'renewal_due']);

        $this->assertFalse($sent, 'A 4xx from Meta must report false so the dispatcher can fall back.');
    }

    // ---- 24h session window (6.5) -------------------------------------------

    public function test_proactive_send_outside_window_without_template_defers(): void
    {
        config([
            'spar.whatsapp.enabled' => true,
            'spar.whatsapp.driver' => 'cloud_api',
            'spar.whatsapp.phone_number_id' => '123456',
            'spar.whatsapp.token' => 'TESTTOKEN',
            'spar.whatsapp.templates' => [],   // no approved template configured
        ]);
        Http::fake();

        // No inbound history => outside the 24h window. Free-form is not allowed.
        $sent = (new WhatsAppChannel())->send($this->patient(), [
            'body' => 'Free-form reminder (not allowed proactively).',
        ]);

        $this->assertFalse($sent, 'Proactive free-form outside the window must defer, not send.');
        Http::assertNothingSent();
    }

    public function test_free_form_allowed_inside_window(): void
    {
        config([
            'spar.whatsapp.enabled' => true,
            'spar.whatsapp.driver' => 'cloud_api',
            'spar.whatsapp.phone_number_id' => '123456',
            'spar.whatsapp.token' => 'TESTTOKEN',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.T']]], 200),
        ]);

        // Patient messaged us 1 hour ago => inside the 24h window.
        $patient = $this->patient([
            'metadata' => ['wa_last_inbound_at' => now()->subHour()->toISOString()],
        ]);

        $sent = (new WhatsAppChannel())->send($patient, [
            'body' => 'Thanks — your order is ready for collection.',
        ]);

        $this->assertTrue($sent);
        Http::assertSent(fn ($request) => $request['type'] === 'text');
    }

    private function assertFalseReach(SparPatient $patient): void
    {
        $this->assertFalse((new WhatsAppChannel())->canReach($patient));
    }
}
