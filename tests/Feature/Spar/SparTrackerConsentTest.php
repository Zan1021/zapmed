<?php

namespace Tests\Feature\Spar;

use Zapmed\SparCore\Livewire\MyMedsTracker;
use App\Models\SparConsent;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use Zapmed\SparCore\Services\SparPatientSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SPAR standalone Phase 1.7 + 1.8 — no-login tokenised tracker + consent gate.
 */
class SparTrackerConsentTest extends TestCase
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
            'consent_status' => 'pending',
            'is_primary_member' => true,
            'is_active' => true,
        ], $overrides));
    }

    public function test_signed_link_establishes_session_and_redirects_to_tracker(): void
    {
        // No OTP re-verify for this assertion path.
        config(['spar.link.require_otp_reverify' => false]);

        $patient = $this->patient();

        $url = URL::temporarySignedRoute('spar.track', now()->addDay(), ['patient' => $patient->id]);

        $this->get($url)->assertRedirect(route('my-meds.track'));
        $this->assertSame($patient->id, app(SparPatientSession::class)->patientId());
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $patient = $this->patient();

        $this->get(route('spar.track', ['patient' => $patient->id]))->assertForbidden();
    }

    public function test_consent_gate_blocks_then_grants_and_records_evidence(): void
    {
        config(['spar.link.require_otp_reverify' => false]);

        $patient = $this->patient();
        app(SparPatientSession::class)->establish($patient);

        Livewire::test(MyMedsTracker::class)
            ->assertSet('step', 'consent')            // gate first — no PHI
            ->set('consentAccepted', true)
            ->call('grantConsent')
            ->assertSet('step', 'dashboard');

        $patient->refresh();
        $this->assertTrue($patient->hasConsented());
        $this->assertSame('active', $patient->onboarding_status);

        // POPIA evidence row written with version + channel + source.
        $consent = SparConsent::where('spar_patient_id', $patient->id)->granted()->first();
        $this->assertNotNull($consent);
        $this->assertSame('web', $consent->channel);
        $this->assertSame('patient', $consent->source);
        $this->assertNotNull($consent->granted_at);
    }

    public function test_consent_required_before_continue(): void
    {
        config(['spar.link.require_otp_reverify' => false]);

        $patient = $this->patient();
        app(SparPatientSession::class)->establish($patient);

        Livewire::test(MyMedsTracker::class)
            ->call('grantConsent')            // box not ticked
            ->assertSet('step', 'consent')
            ->assertSet('error', 'Please tick the box to give consent before continuing.');

        $this->assertFalse($patient->fresh()->hasConsented());
    }

    public function test_history_route_blocked_without_consent(): void
    {
        config(['spar.link.require_otp_reverify' => false]);

        $patient = $this->patient();
        app(SparPatientSession::class)->establish($patient);

        // No consent yet -> middleware bounces to tracker.
        $this->get(route('my-meds.history'))->assertRedirect(route('my-meds.track'));
    }
}
