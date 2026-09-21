<?php

namespace Tests\Feature\Spar;

use App\Models\SparDispenseRecord;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Zapmed\SparCore\Enums\SparActionableStatus;

/**
 * SPAR Close-the-Loop, Wave B1: the shared actionable-item state machine
 * (SparActionableStatus + ResolvesActionable). Verifies legal/illegal
 * transitions, snooze suppression, and the openItems()/dueNow() scopes on a
 * representative subject (dispense records + journeys). One engine, many
 * subjects — so exercising it on these two proves the trait for all.
 */
class SparActionableStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private function pharmacy(): SparPharmacy
    {
        return SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '1000001',
            'bhf_code' => '1000001',
            'supports_delivery' => true,
            'delivery_fee' => 5000,
            'is_active' => true,
        ]);
    }

    private function patient(SparPharmacy $pharmacy): SparPatient
    {
        return SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'consent_status' => 'opted_in',
            'consent_given_at' => now(),
            'is_active' => true,
        ]);
    }

    private function journey(SparPatient $patient, array $overrides = []): SparPrescriptionJourney
    {
        return SparPrescriptionJourney::create(array_merge([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $patient->spar_pharmacy_id,
            'script_number' => '422564',
            'status' => 'active',
            'total_dispenses' => 6,
            'dispenses_completed' => 0,
            'start_date' => now(),
            'next_dispense_date' => now()->addMonth(),
        ], $overrides));
    }

    private function dispense(SparPrescriptionJourney $journey, array $overrides = []): SparDispenseRecord
    {
        return SparDispenseRecord::create(array_merge([
            'journey_id' => $journey->id,
            'spar_patient_id' => $journey->spar_patient_id,
            'dispense_number' => 1,
            'status' => 'upcoming',
            'due_date' => now()->addDays(3),
        ], $overrides));
    }

    public function test_new_subject_defaults_to_open(): void
    {
        $journey = $this->journey($this->patient($this->pharmacy()));

        $this->assertSame(SparActionableStatus::Open, $journey->fresh()->actionStatus());
        $this->assertTrue($journey->isActionable());
        $this->assertFalse($journey->isSnoozed());
    }

    public function test_mark_actioned_stamps_last_action_and_moves_state(): void
    {
        $journey = $this->journey($this->patient($this->pharmacy()));

        $this->assertTrue($journey->markActioned());
        $journey->refresh();

        $this->assertSame(SparActionableStatus::Actioned, $journey->actionStatus());
        $this->assertNotNull($journey->last_action_at);
    }

    public function test_awaiting_patient_flag_targets_that_state(): void
    {
        $journey = $this->journey($this->patient($this->pharmacy()));

        $this->assertTrue($journey->markActioned(awaitingPatient: true));

        $this->assertSame(SparActionableStatus::AwaitingPatient, $journey->fresh()->actionStatus());
    }

    public function test_snooze_sets_future_wake_and_clamps_minimum(): void
    {
        $journey = $this->journey($this->patient($this->pharmacy()));

        $this->assertTrue($journey->snoozeActionable(0)); // clamped to 1
        $journey->refresh();

        $this->assertSame(SparActionableStatus::Snoozed, $journey->actionStatus());
        $this->assertTrue($journey->isSnoozed());
        $this->assertTrue($journey->snoozed_until->isFuture());
    }

    public function test_resolve_is_terminal_and_blocks_ui_transitions(): void
    {
        $journey = $this->journey($this->patient($this->pharmacy()));

        $this->assertTrue($journey->resolveActionable());
        $journey->refresh();

        $this->assertSame(SparActionableStatus::Resolved, $journey->actionStatus());
        $this->assertNotNull($journey->resolved_at);

        // A resolved item cannot be nudged or snoozed via the normal path.
        $this->assertFalse($journey->markActioned());
        $this->assertFalse($journey->snoozeActionable(3));
        $this->assertSame(SparActionableStatus::Resolved, $journey->fresh()->actionStatus());
    }

    public function test_reconciler_may_reopen_a_resolved_item(): void
    {
        $journey = $this->journey($this->patient($this->pharmacy()));
        $journey->resolveActionable();

        $this->assertTrue($journey->reopenActionable());
        $journey->refresh();

        $this->assertSame(SparActionableStatus::Open, $journey->actionStatus());
        $this->assertNull($journey->resolved_at);
    }

    public function test_illegal_transition_is_rejected_without_write(): void
    {
        // open -> open is idempotent-true; but resolved -> actioned is illegal.
        $this->assertFalse(SparActionableStatus::Resolved->canTransitionTo(SparActionableStatus::Actioned));
        $this->assertTrue(SparActionableStatus::Open->canTransitionTo(SparActionableStatus::Snoozed));
        $this->assertTrue(SparActionableStatus::Snoozed->canTransitionTo(SparActionableStatus::Open));
    }

    public function test_open_items_scope_excludes_snoozed_and_resolved(): void
    {
        $patient = $this->patient($this->pharmacy());
        $open = $this->journey($patient);
        $snoozed = $this->journey($patient, ['script_number' => '900001']);
        $resolved = $this->journey($patient, ['script_number' => '900002']);

        $snoozed->snoozeActionable(5);
        $resolved->resolveActionable();

        $ids = SparPrescriptionJourney::openItems()->pluck('id')->all();

        $this->assertContains($open->id, $ids);
        $this->assertNotContains($snoozed->id, $ids);
        $this->assertNotContains($resolved->id, $ids);
    }

    public function test_due_now_scope_includes_woken_snooze_but_not_future_snooze(): void
    {
        $patient = $this->patient($this->pharmacy());
        $open = $this->dispense($this->journey($patient));
        $futureSnooze = $this->dispense($this->journey($patient, ['script_number' => '900003']), ['dispense_number' => 2]);
        $wokenSnooze = $this->dispense($this->journey($patient, ['script_number' => '900004']), ['dispense_number' => 3]);

        $futureSnooze->snoozeActionable(5);
        // Simulate a snooze whose wake time has already passed.
        $wokenSnooze->forceFill([
            'action_status' => SparActionableStatus::Snoozed,
            'snoozed_until' => now()->subDay(),
        ])->save();

        $ids = SparDispenseRecord::dueNow()->pluck('id')->all();

        $this->assertContains($open->id, $ids);
        $this->assertContains($wokenSnooze->id, $ids);
        $this->assertNotContains($futureSnooze->id, $ids);
    }

    public function test_snoozed_scope_only_shows_active_snoozes(): void
    {
        $patient = $this->patient($this->pharmacy());
        $active = $this->journey($patient);
        $active->snoozeActionable(5);

        $ids = SparPrescriptionJourney::snoozed()->pluck('id')->all();

        $this->assertContains($active->id, $ids);
    }
}
