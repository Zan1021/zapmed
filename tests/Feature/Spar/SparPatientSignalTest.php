<?php

namespace Tests\Feature\Spar;

use App\Models\SparDispenseRecord;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Zapmed\SparCore\Enums\SparActionableStatus;
use Zapmed\SparCore\Enums\SparPatientSignalType;
use Zapmed\SparCore\Models\SparPatientSignal;
use Zapmed\SparCore\Services\SparActionService;

/**
 * SPAR Close-the-Loop, Wave B3: patient response signals. Verifies that
 * recordPatientResponse() writes an append-only signal and correctly adjusts
 * the actionable item's schedule/state per SparPatientSignalType.
 */
class SparPatientSignalTest extends TestCase
{
    use RefreshDatabase;

    private function pharmacy(): SparPharmacy
    {
        return SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '5000001',
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
            'cellphone' => '0821234567',
            'is_primary_member' => true,
            'consent_status' => 'opted_in',
            'consent_given_at' => now(),
            'is_active' => true,
        ]);
    }

    private function dispense(SparPatient $patient): SparDispenseRecord
    {
        $journey = SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $patient->spar_pharmacy_id,
            'script_number' => '422564',
            'status' => 'active',
            'total_dispenses' => 6,
            'dispenses_completed' => 1,
            'start_date' => now(),
        ]);

        return SparDispenseRecord::create([
            'journey_id' => $journey->id,
            'spar_patient_id' => $patient->id,
            'dispense_number' => 2,
            'status' => 'upcoming',
            'due_date' => now()->addDays(3),
        ]);
    }

    private function service(): SparActionService
    {
        return app(SparActionService::class);
    }

    public function test_records_an_append_only_signal_with_subject_reference(): void
    {
        $patient = $this->patient($this->pharmacy());
        $dispense = $this->dispense($patient);

        $signal = $this->service()->recordPatientResponse(
            $dispense,
            SparPatientSignalType::RemindInDays,
            ['days' => 10],
        );

        $this->assertDatabaseHas('spar_patient_signals', [
            'id' => $signal->id,
            'spar_patient_id' => $patient->id,
            'subject_id' => $dispense->id,
            'signal' => 'remind_in_days',
        ]);
        $this->assertSame(SparPatientSignalType::RemindInDays, $signal->type());
        $this->assertSame(10, $signal->payload['days']);
        $this->assertStringContainsString('SparDispenseRecord', $signal->subject_type);
    }

    public function test_multiple_responses_append_never_overwrite(): void
    {
        $patient = $this->patient($this->pharmacy());
        $dispense = $this->dispense($patient);

        $this->service()->recordPatientResponse($dispense, SparPatientSignalType::RemindInDays, ['days' => 7]);
        $this->service()->recordPatientResponse($dispense, SparPatientSignalType::StopReminders);

        $this->assertSame(2, SparPatientSignal::forSubject(get_class($dispense), $dispense->id)->count());

        $latest = SparPatientSignal::forSubject(get_class($dispense), $dispense->id)
            ->latestFirst()->first();
        $this->assertSame(SparPatientSignalType::StopReminders, $latest->type());
    }

    public function test_yes_collect_resolves_the_loop(): void
    {
        $patient = $this->patient($this->pharmacy());
        $dispense = $this->dispense($patient);

        $this->service()->recordPatientResponse($dispense, SparPatientSignalType::YesCollect);

        $this->assertSame(SparActionableStatus::Resolved, $dispense->fresh()->actionStatus());
        $this->assertNotNull($dispense->fresh()->resolved_at);
    }

    public function test_remind_in_days_snoozes_for_that_many_days(): void
    {
        $patient = $this->patient($this->pharmacy());
        $dispense = $this->dispense($patient);

        $this->service()->recordPatientResponse($dispense, SparPatientSignalType::RemindInDays, ['days' => 14]);

        $fresh = $dispense->fresh();
        $this->assertSame(SparActionableStatus::Snoozed, $fresh->actionStatus());
        // ~14 days out (allow a little slack for execution time).
        $this->assertEqualsWithDelta(14, now()->diffInDays($fresh->snoozed_until, false), 1);
    }

    public function test_remind_next_cycle_snoozes_a_cycle(): void
    {
        config(['spar.reminders.monthly_cycle_days' => 30]);
        $patient = $this->patient($this->pharmacy());
        $dispense = $this->dispense($patient);

        $this->service()->recordPatientResponse($dispense, SparPatientSignalType::RemindNextCycle);

        $fresh = $dispense->fresh();
        $this->assertSame(SparActionableStatus::Snoozed, $fresh->actionStatus());
        $this->assertTrue($fresh->snoozed_until->isFuture());
    }

    public function test_ignore_month_snoozes_about_a_month(): void
    {
        $patient = $this->patient($this->pharmacy());
        $dispense = $this->dispense($patient);

        $this->service()->recordPatientResponse($dispense, SparPatientSignalType::IgnoreMonth);

        $this->assertSame(SparActionableStatus::Snoozed, $dispense->fresh()->actionStatus());
    }

    public function test_stop_reminders_does_not_resolve_the_clinical_item(): void
    {
        $patient = $this->patient($this->pharmacy());
        $dispense = $this->dispense($patient);

        $this->service()->recordPatientResponse($dispense, SparPatientSignalType::StopReminders);

        // Muting comms must NOT close the clinical loop.
        $this->assertSame(SparActionableStatus::Open, $dispense->fresh()->actionStatus());
        $this->assertDatabaseHas('spar_patient_signals', [
            'subject_id' => $dispense->id,
            'signal' => 'stop_reminders',
        ]);
    }

    public function test_signal_type_helpers(): void
    {
        $this->assertTrue(SparPatientSignalType::YesDeliver->isFulfilment());
        $this->assertSame('delivery', SparPatientSignalType::YesDeliver->fulfilmentMode());
        $this->assertTrue(SparPatientSignalType::StopReminders->isOptOut());
        $this->assertTrue(SparPatientSignalType::IgnoreMonth->isSuppression());
        $this->assertFalse(SparPatientSignalType::IgnoreMonth->isOptOut());
        $this->assertTrue(SparPatientSignalType::RemindInDays->isCadenceChange());
    }
}
