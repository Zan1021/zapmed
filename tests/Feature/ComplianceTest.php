<?php

namespace Tests\Feature;

use App\Enums\ConsentPurpose;
use App\Enums\ConsentState;
use App\Enums\DsarKind;
use App\Enums\DsarStatus;
use App\Enums\RetentionStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\ComplianceConsole;
use App\Models\ComplianceConsent;
use App\Models\ComplianceConsentRecord;
use App\Models\ComplianceDsar;
use App\Models\CrmAuditEvent;
use App\Models\RetentionScheduleItem;
use App\Models\User;
use App\Services\Compliance\AuditTrail;
use App\Services\Compliance\ComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Task 9 — Compliance/audit (POPIA): consent versioning, DSAR lifecycle + SLA, retention + legal hold,
 * and the append-only tamper-evident audit trail.
 */
class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    private function svc(): ComplianceService
    {
        return app(ComplianceService::class);
    }

    private function patient(): User
    {
        return User::factory()->create(['role' => UserRole::Patient]);
    }

    // ---- retention policy seed ------------------------------------------------------------------

    public function test_default_retention_policies_are_seeded(): void
    {
        $this->assertDatabaseHas('compliance_retention_policies', ['data_class' => 'orders_order']);
        $this->assertDatabaseHas('compliance_retention_policies', ['data_class' => 'audit_trail']);
    }

    // ---- consent --------------------------------------------------------------------------------

    public function test_set_consent_upserts_and_appends_immutable_record(): void
    {
        $patient = $this->patient();

        $this->svc()->setConsent($patient, ConsentPurpose::Marketing, ConsentState::Granted, 'opted in');
        $this->svc()->setConsent($patient, ConsentPurpose::Marketing, ConsentState::Withdrawn, 'changed mind');

        $consent = ComplianceConsent::where('principal_id', $patient->id)->where('purpose', 'marketing')->first();
        $this->assertSame(ConsentState::Withdrawn, $consent->state);
        // Both transitions recorded immutably.
        $this->assertSame(2, ComplianceConsentRecord::where('consent_id', $consent->id)->count());
    }

    public function test_ensure_defaults_grants_service_purposes_and_pends_opt_ins(): void
    {
        $patient = $this->patient();

        $this->svc()->ensureDefaults($patient);

        $this->assertTrue($this->svc()->hasConsent($patient, ConsentPurpose::Transactional));
        $this->assertTrue($this->svc()->hasConsent($patient, ConsentPurpose::ClinicalShare));
        $this->assertFalse($this->svc()->hasConsent($patient, ConsentPurpose::Marketing));

        $marketing = ComplianceConsent::where('principal_id', $patient->id)->where('purpose', 'marketing')->first();
        $this->assertSame(ConsentState::PendingReconsent, $marketing->state);
    }

    public function test_policy_bump_requires_reconsent(): void
    {
        config(['compliance.policy_version' => 'v1.0']);
        $patient = $this->patient();
        $this->svc()->setConsent($patient, ConsentPurpose::Marketing, ConsentState::Granted);

        config(['compliance.policy_version' => 'v2.0']);
        $flipped = $this->svc()->requireReconsentForNewPolicy();

        $this->assertSame(1, $flipped);
        $this->assertFalse($this->svc()->hasConsent($patient, ConsentPurpose::Marketing));
    }

    // ---- DSAR lifecycle -------------------------------------------------------------------------

    public function test_file_dsar_sets_number_and_30_day_due(): void
    {
        $dsar = $this->svc()->fileDsar($this->patient(), DsarKind::Access, 'what do you have');

        $this->assertStringStartsWith('DSAR-', $dsar->dsar_number);
        $this->assertSame(DsarStatus::Received, $dsar->status);
        $this->assertEqualsWithDelta(30, now()->diffInDays($dsar->due_at), 1);
    }

    public function test_dsar_happy_path_transitions(): void
    {
        $dsar = $this->svc()->fileDsar($this->patient(), DsarKind::Access);

        $this->svc()->acknowledgeDsar($dsar);
        $this->assertSame(DsarStatus::Acknowledged, $dsar->fresh()->status);

        $this->svc()->startDsar($dsar->fresh());
        $this->assertSame(DsarStatus::InProgress, $dsar->fresh()->status);

        $this->svc()->completeDsar($dsar->fresh());
        $this->assertSame(DsarStatus::Completed, $dsar->fresh()->status);
        $this->assertNotNull($dsar->fresh()->completed_at);
    }

    public function test_cannot_complete_a_freshly_received_dsar(): void
    {
        $dsar = $this->svc()->fileDsar($this->patient(), DsarKind::Access);

        $this->expectException(RuntimeException::class);
        $this->svc()->completeDsar($dsar); // received → completed is illegal
    }

    public function test_overdue_detection(): void
    {
        $dsar = $this->svc()->fileDsar($this->patient(), DsarKind::Access);
        $dsar->forceFill(['due_at' => now()->subDay()])->save();

        $this->assertTrue($dsar->fresh()->isOverdue());
    }

    // ---- retention + legal hold -----------------------------------------------------------------

    public function test_schedule_retention_is_idempotent_per_record(): void
    {
        $a = $this->svc()->scheduleRetention('orders_order', 123);
        $b = $this->svc()->scheduleRetention('orders_order', 123);

        $this->assertTrue($a->is($b));
        $this->assertSame(1, RetentionScheduleItem::where('data_class', 'orders_order')->where('aggregate_id', 123)->count());
    }

    public function test_legal_hold_blocks_execution(): void
    {
        $item = $this->svc()->scheduleRetention('orders_order', 55, dueAt: now()->subDay());
        $this->svc()->placeLegalHold($item, 'litigation pending');

        $this->expectException(RuntimeException::class);
        $this->svc()->completeRetention($item->fresh());
    }

    public function test_erasure_dsar_schedules_retention_and_links_it(): void
    {
        $patient = $this->patient();
        $dsar = $this->svc()->fileDsar($patient, DsarKind::Erasure);

        $item = $this->svc()->scheduleErasureForDsar($dsar, 'orders_order', 77);

        $this->assertSame($item->id, $dsar->fresh()->retention_schedule_id);
        $this->assertSame(RetentionStatus::Scheduled, $item->status);
    }

    public function test_scheduling_erasure_on_a_non_erasure_dsar_is_refused(): void
    {
        $dsar = $this->svc()->fileDsar($this->patient(), DsarKind::Access);

        $this->expectException(RuntimeException::class);
        $this->svc()->scheduleErasureForDsar($dsar, 'orders_order', 88);
    }

    // ---- append-only audit trail ----------------------------------------------------------------

    public function test_audit_events_are_written_for_consent_and_dsar(): void
    {
        $patient = $this->patient();
        $this->svc()->setConsent($patient, ConsentPurpose::Marketing, ConsentState::Granted);
        $this->svc()->fileDsar($patient, DsarKind::Access);

        $this->assertGreaterThanOrEqual(1, CrmAuditEvent::domain('consent')->count());
        $this->assertGreaterThanOrEqual(1, CrmAuditEvent::domain('dsar')->count());
    }

    public function test_audit_events_are_append_only(): void
    {
        app(AuditTrail::class)->record('test', 'test.event');
        $event = CrmAuditEvent::first();

        $this->expectException(RuntimeException::class);
        $event->update(['action' => 'tampered']);
    }

    public function test_audit_events_cannot_be_deleted(): void
    {
        app(AuditTrail::class)->record('test', 'test.event');
        $event = CrmAuditEvent::first();

        $this->expectException(RuntimeException::class);
        $event->delete();
    }

    public function test_hash_chain_verifies_intact(): void
    {
        $trail = app(AuditTrail::class);
        $trail->record('test', 'a');
        $trail->record('test', 'b');
        $trail->record('test', 'c');

        $this->assertNull($trail->verifyChain()); // null = intact
    }

    // ---- UI -------------------------------------------------------------------------------------

    public function test_admin_can_view_compliance_console(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get(route('admin.compliance'))
            ->assertOk()
            ->assertSeeLivewire(ComplianceConsole::class);
    }

    public function test_non_admin_forbidden(): void
    {
        $this->actingAs($this->patient())->get(route('admin.compliance'))->assertForbidden();
    }

    public function test_ui_acknowledge_dsar(): void
    {
        $dsar = $this->svc()->fileDsar($this->patient(), DsarKind::Access);

        Livewire::actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->test(ComplianceConsole::class)
            ->call('acknowledge', $dsar->id);

        $this->assertSame(DsarStatus::Acknowledged, $dsar->fresh()->status);
    }
}
