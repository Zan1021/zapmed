<?php

namespace Tests\Feature\Spar;

use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Zapmed\SparCore\Contracts\MessagingChannel;
use Zapmed\SparCore\Models\SparPatient as SparCorePatient;
use Zapmed\SparCore\Services\SparReminderService;

/**
 * Phase 5.2 — Reminder-parity (spec FR-11.2, FR-13, AC-4), asserted end-to-end
 * through the real dispatch pipeline (not just the private message builder).
 *
 * A spy MessagingChannel is registered via `spar.channel_factories` and captures
 * the payload the reminder service actually dispatches. We then drive the true
 * `SparReminderService::processReminders()` renewal path under each host mode and
 * assert on the delivered renewal message body:
 *   - integrated  => offers the "Consult a ZapMed doctor online" option
 *   - standalone  => offers only "your own doctor"; no ZapMed-online option
 *
 * The TelehealthBridge binding is what flips the copy, so the parity is proven
 * against the same code path production reminders use.
 */
class SparRenewalParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset the spy and register it as the primary push channel so the
        // dispatcher records exactly what the reminder service produced.
        SpyRenewalChannel::$captured = [];

        config([
            'spar.channels' => ['spy'],
            'spar.channel_factories' => [
                'spy' => fn () => new SpyRenewalChannel(),
            ],
        ]);
    }

    private function renewalDueJourney(): SparPrescriptionJourney
    {
        $pharmacy = SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'is_active' => true,
        ]);

        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'cellphone' => '0821234567',
            'consent_status' => 'opted_in',
            'is_active' => true,
        ]);

        return SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => 'R-1',
            'status' => 'renewal_due',
            'total_dispenses' => 6,
            'dispenses_completed' => 6,
            'start_date' => now()->subMonths(6),
            'renewal_due_date' => now()->addDays(3),
            'medications' => [['name' => 'CO-COPALIA 10MG TAB 28']],
        ]);
    }

    /** The last renewal body captured by the spy channel. */
    private function capturedRenewalBody(): string
    {
        $renewal = collect(SpyRenewalChannel::$captured)
            ->first(fn ($p) => str_contains((string) ($p['subject'] ?? ''), 'renewal'));

        $this->assertNotNull($renewal, 'Expected a renewal message to be dispatched.');

        return (string) ($renewal['body'] ?? '');
    }

    public function test_integrated_renewal_reminder_offers_zapmed_online_option(): void
    {
        config(['spar.host_mode' => 'integrated']);
        $this->renewalDueJourney();

        $stats = (new SparReminderService())->processReminders();
        $this->assertSame(1, $stats['renewal_reminders_sent']);

        $body = $this->capturedRenewalBody();
        $this->assertStringContainsStringIgnoringCase('ZapMed doctor', $body);
        $this->assertStringContainsStringIgnoringCase('online', $body);
        $this->assertStringContainsStringIgnoringCase('primary doctor', $body);
    }

    public function test_standalone_renewal_reminder_omits_zapmed_online_option(): void
    {
        config(['spar.host_mode' => 'standalone']);
        $this->renewalDueJourney();

        $stats = (new SparReminderService())->processReminders();
        $this->assertSame(1, $stats['renewal_reminders_sent']);

        $body = $this->capturedRenewalBody();
        $this->assertStringNotContainsStringIgnoringCase('ZapMed doctor', $body);
        $this->assertStringNotContainsStringIgnoringCase('consult a zapmed', $body);
        // Standalone still tells the patient to renew with their own doctor.
        $this->assertStringContainsStringIgnoringCase('your doctor', $body);
    }
}

/**
 * Minimal in-memory MessagingChannel that captures every dispatched payload.
 * Always reachable so it deterministically records the renewal message.
 */
class SpyRenewalChannel implements MessagingChannel
{
    /** @var array<int, array<string, mixed>> */
    public static array $captured = [];

    public function key(): string
    {
        return 'spy';
    }

    public function canReach(SparCorePatient $patient): bool
    {
        return true;
    }

    public function send(SparCorePatient $patient, array $payload): bool
    {
        self::$captured[] = $payload;

        return true;
    }
}
