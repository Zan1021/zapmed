<?php

namespace Tests\Feature;

use App\Enums\FunnelStage;
use App\Enums\UserRole;
use App\Models\CrmLead;
use App\Models\User;
use App\Services\Crm\JourneyCapture;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 6 — CRM capture: the live journey must land in the CRM funnel + analytics event log.
 *
 * Before this, nothing in the patient journey created a CrmLead or emitted analytics funnel events;
 * LiveOrderBridge only mirrored Orders/finance. JourneyCapture + the Registered listener close that gap.
 */
class JourneyCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): User
    {
        return User::factory()->create(['role' => UserRole::Patient]);
    }

    public function test_registering_a_patient_creates_a_lead_at_signed_up(): void
    {
        $patient = $this->patient();

        event(new Registered($patient));

        $lead = CrmLead::where('patient_id', $patient->id)->first();
        $this->assertNotNull($lead, 'A CRM lead should be created on patient registration.');
        $this->assertSame(FunnelStage::SignedUp, $lead->current_stage);

        $this->assertDatabaseHas('analytics_funnel_events', [
            'principal_id' => $patient->id,
            'kind' => 'sign_up_complete',
        ]);
    }

    public function test_registering_a_doctor_does_not_create_a_lead(): void
    {
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);

        event(new Registered($doctor));

        $this->assertDatabaseMissing('crm_leads', ['patient_id' => $doctor->id]);
    }

    public function test_journey_milestones_advance_the_funnel_forward_only(): void
    {
        $patient = $this->patient();
        $journey = app(JourneyCapture::class);

        $journey->signedUp($patient);
        $journey->intakeComplete($patient, 'weight-loss');
        $journey->consultBooked($patient, 'weight-loss', 123);
        $journey->consultComplete($patient, 'weight-loss', 123);
        $journey->scriptIssued($patient, 'weight-loss', 456);
        $journey->paid($patient, 'weight-loss');

        $lead = CrmLead::where('patient_id', $patient->id)->first();
        $this->assertSame(FunnelStage::Paid, $lead->current_stage);

        // Each milestone recorded a funnel event (immutable log) — expect a chain of transitions.
        $this->assertGreaterThanOrEqual(6, $lead->funnelEvents()->count());

        // Analytics events fired for the acquisition milestones.
        foreach (['sign_up_complete', 'intake_complete', 'consult_booked', 'consult_complete', 'first_payment'] as $kind) {
            $this->assertDatabaseHas('analytics_funnel_events', [
                'principal_id' => $patient->id,
                'kind' => $kind,
            ]);
        }
    }

    public function test_capture_is_idempotent_and_does_not_rewind(): void
    {
        $patient = $this->patient();
        $journey = app(JourneyCapture::class);

        $journey->consultBooked($patient, 'weight-loss', 1);
        $stageAfterBooked = CrmLead::where('patient_id', $patient->id)->first()->current_stage;

        // A later, earlier-in-order milestone must NOT rewind the lead.
        $journey->signedUp($patient);
        $stageAfterSignup = CrmLead::where('patient_id', $patient->id)->first()->current_stage;

        $this->assertSame(FunnelStage::ConsultBooked, $stageAfterBooked);
        $this->assertSame(FunnelStage::ConsultBooked, $stageAfterSignup, 'Earlier milestone must not rewind the funnel.');
    }
}
