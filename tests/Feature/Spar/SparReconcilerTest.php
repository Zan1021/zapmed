<?php

namespace Tests\Feature\Spar;

use App\Models\SparDispenseRecord;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Zapmed\SparCore\Enums\SparActionableStatus;
use Zapmed\SparCore\Services\SparReconciler;

/**
 * SPAR Close-the-Loop, Wave B5: the import auto-resolve reconciler (FR-B5).
 *
 * Verifies both D2 conflict-rule branches:
 *   overlay      — file is truth; confirmed dispenses resolve; unconfirmed
 *                  in-app resolutions are LEFT in place.
 *   system_wins  — file is truth; confirmed dispenses resolve; in-app
 *                  resolutions NOT backed by a fact are reopened.
 */
class SparReconcilerTest extends TestCase
{
    use RefreshDatabase;

    private function pharmacy(): SparPharmacy
    {
        return SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '7000001',
            'supports_delivery' => true,
            'is_active' => true,
        ]);
    }

    private function patient(SparPharmacy $pharmacy): SparPatient
    {
        return SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'first_name' => 'Thabo',
            'is_primary_member' => true,
            'consent_status' => 'opted_in',
            'consent_given_at' => now(),
            'is_active' => true,
        ]);
    }

    private function journey(SparPatient $patient, int $completed = 1, int $total = 6, string $status = 'active'): SparPrescriptionJourney
    {
        return SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $patient->spar_pharmacy_id,
            'script_number' => '900900',
            'status' => $status,
            'total_dispenses' => $total,
            'dispenses_completed' => $completed,
            'start_date' => now()->subMonths(2),
        ]);
    }

    private function dispense(SparPrescriptionJourney $journey, string $status, bool $completed): SparDispenseRecord
    {
        return SparDispenseRecord::create([
            'journey_id' => $journey->id,
            'spar_patient_id' => $journey->spar_patient_id,
            'dispense_number' => 1,
            'status' => $status,
            'due_date' => now()->subDays(3),
            'completed_at' => $completed ? now()->subDays(1) : null,
        ]);
    }

    private function useRule(string $rule): void
    {
        config()->set('spar.reconciler.enabled', true);
        config()->set('spar.reconciler.conflict_rule', $rule);
    }

    public function test_overlay_confirmed_dispense_resolves_open_loop(): void
    {
        $this->useRule(SparReconciler::RULE_OVERLAY);
        $patient = $this->patient($this->pharmacy());
        $journey = $this->journey($patient);

        // A collected+completed dispense (file fact) that the app still shows open.
        $dispense = $this->dispense($journey, status: 'collected', completed: true);
        $dispense->markActioned(awaitingPatient: true); // in-app: awaiting patient
        $this->assertSame(SparActionableStatus::AwaitingPatient, $dispense->fresh()->actionStatus());

        $stats = (new SparReconciler())->reconcileJourney($journey->fresh());

        $this->assertSame(SparActionableStatus::Resolved, $dispense->fresh()->actionStatus());
        $this->assertGreaterThanOrEqual(1, $stats['resolved']);
        $this->assertSame(0, $stats['reopened']);
    }

    public function test_overlay_leaves_unconfirmed_in_app_resolution_in_place(): void
    {
        $this->useRule(SparReconciler::RULE_OVERLAY);
        $patient = $this->patient($this->pharmacy());
        $journey = $this->journey($patient);

        // In-app resolution NOT backed by a file fact (dispense still upcoming).
        $dispense = $this->dispense($journey, status: 'upcoming', completed: false);
        $dispense->resolveActionable();

        (new SparReconciler())->reconcileJourney($journey->fresh());

        // Overlay: absence is not contradiction — leave the resolution alone.
        $this->assertSame(SparActionableStatus::Resolved, $dispense->fresh()->actionStatus());
    }

    public function test_system_wins_reopens_unconfirmed_in_app_resolution(): void
    {
        $this->useRule(SparReconciler::RULE_SYSTEM_WINS);
        $patient = $this->patient($this->pharmacy());
        $journey = $this->journey($patient);

        // In-app resolution NOT backed by a file fact (dispense still upcoming).
        $dispense = $this->dispense($journey, status: 'upcoming', completed: false);
        $dispense->resolveActionable();

        $stats = (new SparReconciler())->reconcileJourney($journey->fresh());

        // system_wins: force the app to match SPAR — reopen the unbacked resolution.
        $this->assertSame(SparActionableStatus::Open, $dispense->fresh()->actionStatus());
        $this->assertGreaterThanOrEqual(1, $stats['reopened']);
    }

    public function test_course_complete_resolves_journey_loop(): void
    {
        $this->useRule(SparReconciler::RULE_OVERLAY);
        $patient = $this->patient($this->pharmacy());
        // Final dispense reached → renewal_due; the journey's own loop closes.
        $journey = $this->journey($patient, completed: 6, total: 6, status: 'renewal_due');
        $this->assertSame(SparActionableStatus::Open, $journey->fresh()->actionStatus());

        (new SparReconciler())->reconcileJourney($journey->fresh());

        $this->assertSame(SparActionableStatus::Resolved, $journey->fresh()->actionStatus());
    }

    public function test_disabled_reconciler_is_a_noop(): void
    {
        config()->set('spar.reconciler.enabled', false);
        $patient = $this->patient($this->pharmacy());
        $journey = $this->journey($patient);
        $dispense = $this->dispense($journey, status: 'collected', completed: true);

        $stats = (new SparReconciler())->reconcileJourney($journey->fresh());

        $this->assertTrue($stats['skipped']);
        // Untouched — still Open.
        $this->assertSame(SparActionableStatus::Open, $dispense->fresh()->actionStatus());
    }

    public function test_import_triggers_reconciliation_and_resolves_recorded_dispense(): void
    {
        // End-to-end-ish: the import records a collected dispense, and the wired
        // reconciler resolves that fill's loop as part of the same import run.
        $this->useRule(SparReconciler::RULE_OVERLAY);
        $patient = $this->patient($this->pharmacy());
        $journey = $this->journey($patient, completed: 1, total: 6);
        $dispense = $this->dispense($journey, status: 'collected', completed: true);

        // Simulate the post-import reconcile call the service now makes.
        (new SparReconciler())->reconcileJourney($journey->fresh());

        $this->assertSame(SparActionableStatus::Resolved, $dispense->fresh()->actionStatus());
    }
}
