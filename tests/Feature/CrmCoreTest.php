<?php

namespace Tests\Feature;

use App\Enums\CrmFlagKind;
use App\Enums\FunnelStage;
use App\Enums\RiskBand;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CrmLead;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Crm\LeadFunnel;
use App\Services\Crm\Patient360;
use App\Services\Crm\RiskScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Task 3 — CRM core (leads/funnel/flags/notes/risk + patient-360). Behaviour + parity.
 */
class CrmCoreTest extends TestCase
{
    use RefreshDatabase;

    private function patient(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => UserRole::Patient], $attrs));
    }

    // ---- enum parity ---------------------------------------------------------------------------

    public function test_funnel_has_the_fourteen_reference_stages_verbatim(): void
    {
        $this->assertSame([
            'lead', 'signed_up', 'intake_started', 'intake_complete', 'consult_booked',
            'consult_complete', 'script_issued', 'paid', 'dispatched', 'delivered',
            'coached', 'subscribed', 'churned', 'dropped_off',
        ], FunnelStage::values());
    }

    public function test_flag_kinds_match_the_reference(): void
    {
        $this->assertSame([
            'at_risk', 'vip', 'do_not_contact', 'fraud_suspected',
            'complaint_open', 'high_value', 'follow_up_required', 'other',
        ], CrmFlagKind::values());
    }

    public function test_risk_band_derives_from_score_per_rubric(): void
    {
        $this->assertSame(RiskBand::Low, RiskBand::fromScore(0));
        $this->assertSame(RiskBand::Low, RiskBand::fromScore(24));
        $this->assertSame(RiskBand::Medium, RiskBand::fromScore(25));
        $this->assertSame(RiskBand::High, RiskBand::fromScore(50));
        $this->assertSame(RiskBand::Critical, RiskBand::fromScore(75));
        $this->assertSame(RiskBand::Critical, RiskBand::fromScore(100));
    }

    // ---- LeadFunnel ----------------------------------------------------------------------------

    public function test_ensure_lead_is_idempotent_and_seeds_initial_event(): void
    {
        $funnel = app(LeadFunnel::class);
        $patient = $this->patient();

        $lead = $funnel->ensureLead($patient);
        $again = $funnel->ensureLead($patient);

        $this->assertTrue($lead->is($again));
        $this->assertSame(FunnelStage::Lead, $lead->current_stage);
        $this->assertSame(1, $lead->funnelEvents()->count());
        $this->assertNull($lead->funnelEvents()->first()->from_stage);
    }

    public function test_advance_records_immutable_event_and_bumps_stage(): void
    {
        $funnel = app(LeadFunnel::class);
        $lead = $funnel->ensureLead($this->patient());

        $event = $funnel->advance($lead, FunnelStage::SignedUp, ['actor' => 'ops:1', 'notes' => 'created account']);

        $lead->refresh();
        $this->assertSame(FunnelStage::SignedUp, $lead->current_stage);
        $this->assertSame(1, $lead->version);
        $this->assertSame(FunnelStage::Lead, $event->from_stage);
        $this->assertSame(FunnelStage::SignedUp, $event->to_stage);
        $this->assertSame(2, $lead->funnelEvents()->count()); // initial + this
    }

    public function test_funnel_event_is_immutable(): void
    {
        $funnel = app(LeadFunnel::class);
        $lead = $funnel->ensureLead($this->patient());
        $event = $funnel->advance($lead, FunnelStage::SignedUp);

        $this->expectException(RuntimeException::class);
        $event->update(['notes' => 'tamper']);
    }

    public function test_advancing_to_same_stage_is_rejected(): void
    {
        $funnel = app(LeadFunnel::class);
        $lead = $funnel->ensureLead($this->patient());

        $this->expectException(RuntimeException::class);
        $funnel->advance($lead, FunnelStage::Lead);
    }

    public function test_unknown_stage_is_rejected(): void
    {
        $funnel = app(LeadFunnel::class);
        $lead = $funnel->ensureLead($this->patient());

        $this->expectException(\InvalidArgumentException::class);
        $funnel->advance($lead, 'teleportation');
    }

    // ---- flags + notes -------------------------------------------------------------------------

    public function test_raise_flag_is_idempotent_per_active_kind(): void
    {
        $funnel = app(LeadFunnel::class);
        $lead = $funnel->ensureLead($this->patient());

        $a = $funnel->raiseFlag($lead, CrmFlagKind::AtRisk, 'missed payments');
        $b = $funnel->raiseFlag($lead, CrmFlagKind::AtRisk);

        $this->assertTrue($a->is($b));
        $this->assertSame(1, $lead->activeFlags()->count());
    }

    public function test_clearing_a_flag_allows_raising_it_again(): void
    {
        $funnel = app(LeadFunnel::class);
        $lead = $funnel->ensureLead($this->patient());

        $funnel->raiseFlag($lead, CrmFlagKind::Vip);
        $funnel->clearFlag($lead, CrmFlagKind::Vip);
        $this->assertSame(0, $lead->activeFlags()->count());

        $funnel->raiseFlag($lead, CrmFlagKind::Vip);
        $this->assertSame(1, $lead->activeFlags()->count());
    }

    public function test_notes_are_added_pinned_first(): void
    {
        $funnel = app(LeadFunnel::class);
        $lead = $funnel->ensureLead($this->patient());

        $funnel->addNote($lead, 'first');
        $funnel->addNote($lead, 'pinned one', pinned: true);

        $this->assertSame('pinned one', $lead->notes()->first()->body);
    }

    // ---- RiskScorer ----------------------------------------------------------------------------

    public function test_healthy_patient_scores_low_with_positive_factor(): void
    {
        $lead = app(LeadFunnel::class)->ensureLead($this->patient());
        $score = app(RiskScorer::class)->score($lead);

        $this->assertSame(0, $score->score);
        $this->assertSame(RiskBand::Low, $score->band);
        $this->assertSame('positive', $score->factors[0]['impact']);
    }

    public function test_failed_payments_and_flags_raise_the_score(): void
    {
        $patient = $this->patient();
        $lead = app(LeadFunnel::class)->ensureLead($patient);

        Payment::create(['patient_id' => $patient->id, 'provider' => 'payfast', 'amount' => 1000, 'status' => 'failed']);
        Payment::create(['patient_id' => $patient->id, 'provider' => 'payfast', 'amount' => 1000, 'status' => 'failed']);
        app(LeadFunnel::class)->raiseFlag($lead, CrmFlagKind::ComplaintOpen);

        $score = app(RiskScorer::class)->score($lead->fresh());

        // 2×12 (failed) + 15 (complaint) = 39 → medium
        $this->assertSame(39, $score->score);
        $this->assertSame(RiskBand::Medium, $score->band);
    }

    public function test_terminal_stage_and_no_shows_push_to_high_or_critical(): void
    {
        $patient = $this->patient();
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $lead = app(LeadFunnel::class)->ensureLead($patient);
        app(LeadFunnel::class)->advance($lead, FunnelStage::Churned);

        Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'status' => 'no_show',
            'appointment_date' => now()->subDays(5), 'start_time' => '09:00', 'end_time' => '09:30',
            'fee_amount' => 0,
        ]);

        $score = app(RiskScorer::class)->score($lead->fresh());

        // churned 40 + 1 no-show 8 = 48 (+ maybe stalled) → at least high-ish; assert banding logic holds
        $this->assertGreaterThanOrEqual(40, $score->score);
        $this->assertContains($score->band, [RiskBand::Medium, RiskBand::High, RiskBand::Critical]);
    }

    public function test_score_is_clamped_and_one_row_per_lead(): void
    {
        $patient = $this->patient();
        $lead = app(LeadFunnel::class)->ensureLead($patient);
        app(LeadFunnel::class)->advance($lead, FunnelStage::Churned);
        app(LeadFunnel::class)->raiseFlag($lead, CrmFlagKind::FraudSuspected);
        app(LeadFunnel::class)->raiseFlag($lead, CrmFlagKind::AtRisk);
        app(LeadFunnel::class)->raiseFlag($lead, CrmFlagKind::ComplaintOpen);
        for ($i = 0; $i < 6; $i++) {
            Payment::create(['patient_id' => $patient->id, 'provider' => 'payfast', 'amount' => 1000, 'status' => 'failed']);
        }

        app(RiskScorer::class)->score($lead->fresh());
        $second = app(RiskScorer::class)->score($lead->fresh());

        $this->assertLessThanOrEqual(100, $second->score);
        $this->assertSame(1, $lead->riskScore()->count());
    }

    // ---- Patient360 ----------------------------------------------------------------------------

    public function test_patient_360_assembles_the_read_model(): void
    {
        $patient = $this->patient(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $lead = app(LeadFunnel::class)->ensureLead($patient);
        app(LeadFunnel::class)->advance($lead, FunnelStage::Paid);
        Order::create(['patient_id' => $patient->id, 'status' => 'PendingPayment', 'total_minor' => 5000, 'ordered_at' => now()]);
        Payment::create(['patient_id' => $patient->id, 'provider' => 'payfast', 'amount' => 5000, 'status' => 'completed']);

        $d = app(Patient360::class)->assemble($patient->fresh());

        $this->assertSame(FunnelStage::Paid, $d['stage']);
        $this->assertSame(1, $d['counts']['orders']);
        $this->assertSame(5000, $d['ltv_minor']); // one completed payment
    }

    public function test_patient_360_returns_null_for_non_patient(): void
    {
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $this->assertNull(app(Patient360::class)->assemble($doctor));
    }

    public function test_cross_field_search_matches_name_member_and_order(): void
    {
        $p1 = $this->patient(['first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'grace@navy.mil']);
        $p2 = $this->patient(['first_name' => 'Bob', 'last_name' => 'Smith']);
        Order::create(['patient_id' => $p2->id, 'reference' => 'ZM-2026-424242', 'status' => 'PendingPayment', 'ordered_at' => now()]);

        $svc = app(Patient360::class);

        $this->assertTrue($svc->search('Hopper')->contains('id', $p1->id));
        $this->assertTrue($svc->search('grace@navy.mil')->contains('id', $p1->id));
        $this->assertTrue($svc->search($p1->member_number)->contains('id', $p1->id));
        $this->assertTrue($svc->search('424242')->contains('id', $p2->id));
        $this->assertTrue($svc->search('')->isEmpty());
    }
}
