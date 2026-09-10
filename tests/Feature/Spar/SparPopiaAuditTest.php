<?php

namespace Tests\Feature\Spar;

use App\Models\SparPatient;
use App\Models\SparPharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Zapmed\SparCore\Concerns\LogsSparActivity;
use Zapmed\SparCore\Contracts\AuditLogger;

/**
 * Phase 5.4 — POPIA verification (spec NFR-1, FR-5.2, FR-5.3, AC-5).
 *
 * Two guarantees, both previously unasserted:
 *   1. Sensitive SPAR fields are ENCRYPTED AT REST (ciphertext in the column,
 *      plaintext only via the model) — NFR-1 / FR-5.3.
 *   2. Audited SPAR events land on the `spar_audit` channel for patient access,
 *      order changes, imports, and consent changes — FR-5.2 / AC-5. Covers both
 *      audit seams: the `LogsSparActivity` trait (Livewire/UI path) and the
 *      `AuditLogger` contract (service/bridge path).
 */
class SparPopiaAuditTest extends TestCase
{
    use RefreshDatabase;

    private function pharmacy(): SparPharmacy
    {
        return SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'is_active' => true,
        ]);
    }

    // ---- NFR-1 / FR-5.3 — encryption at rest -------------------------------

    public function test_sensitive_fields_are_encrypted_at_rest(): void
    {
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $this->pharmacy()->id,
            'profile_code' => '550',
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'cellphone' => '0821234567',
            'email' => 'thabo@example.co.za',
            'consent_status' => 'pending',
            'is_active' => true,
        ]);

        // Raw column values (bypass the model's decrypting accessor) are ciphertext.
        $raw = DB::table('spar_patients')->where('id', $patient->id)->first();

        $plaintext = [
            'profile_code' => '550',
            'cellphone' => '0821234567',
            'email' => 'thabo@example.co.za',
        ];

        foreach ($plaintext as $field => $value) {
            $this->assertNotSame($value, $raw->{$field}, "{$field} should not be stored in plaintext");
            // Laravel encrypted strings are base64-encoded JSON starting 'eyJ'.
            $this->assertStringStartsWith('eyJ', (string) $raw->{$field}, "{$field} should be encrypted ciphertext");
        }

        // The model decrypts transparently on read.
        $fresh = SparPatient::find($patient->id);
        $this->assertSame('0821234567', $fresh->cellphone);
        $this->assertSame('550', $fresh->profile_code);
        $this->assertSame('thabo@example.co.za', $fresh->email);
    }

    // ---- FR-5.2 / AC-5 — spar_audit via the LogsSparActivity trait ---------

    public function test_logs_spar_activity_trait_writes_to_spar_audit_channel(): void
    {
        // Spy the dedicated audit channel.
        $spy = new SpyAuditChannel();
        Log::shouldReceive('channel')->with('spar_audit')->andReturn($spy);

        $auditor = new class {
            use LogsSparActivity;

            public function access(int $id): void { $this->logPatientAccess($id, 'view'); }
            public function order(int $id): void { $this->logOrderAction($id, 'prepare', 'requested', 'preparing'); }
            public function import(int $b): void { $this->logImportEvent($b, 'extract.csv', 'completed'); }
            public function consent(int $id): void { $this->logConsentChange($id, 'pending', 'opted_in'); }
        };

        $auditor->access(1);
        $auditor->order(7);
        $auditor->import(3);
        $auditor->consent(1);

        $events = array_column($spy->entries, 'context');
        $eventKeys = array_map(fn ($c) => $c['action'] ?? null, $events);

        $this->assertContains('patient_access', $eventKeys);
        $this->assertContains('order_update', $eventKeys);
        $this->assertContains('data_import', $eventKeys);
        $this->assertContains('consent_change', $eventKeys);
        $this->assertCount(4, $spy->entries);
    }

    // ---- FR-5.2 / AC-5 — spar_audit via the AuditLogger contract -----------

    public function test_audit_logger_contract_writes_to_spar_audit_channel(): void
    {
        config(['spar.host_mode' => 'integrated']);

        $spy = new SpyAuditChannel();
        Log::shouldReceive('channel')->with('spar_audit')->andReturn($spy);

        // Resolves to ChannelAuditLogger (integrated binding).
        $logger = app(AuditLogger::class);
        $logger->log('consent_granted', 'Patient #1 granted consent', ['spar_patient_id' => 1, 'channel' => 'web']);

        $this->assertCount(1, $spy->entries);
        $this->assertSame('consent_granted', $spy->entries[0]['context']['event']);
        $this->assertSame(1, $spy->entries[0]['context']['spar_patient_id']);
    }
}

/**
 * Captures anything written to a faked log channel.
 */
class SpyAuditChannel
{
    /** @var array<int, array{level:string, message:string, context:array}> */
    public array $entries = [];

    public function info(string $message, array $context = []): void
    {
        $this->entries[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $this->entries[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
}
