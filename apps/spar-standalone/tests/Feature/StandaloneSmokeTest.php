<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Services\SparPatientSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 4.6 — smoke every standalone screen + AC-1/AC-4 assertions.
 * Boots the standalone host (host_mode=standalone via phpunit.xml), so all
 * SPAR contracts resolve to the standalone/null implementations.
 */
class StandaloneSmokeTest extends TestCase
{
    use RefreshDatabase;

    private SparPharmacy $pharmacy;
    private PharmacyUser $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pharmacy = SparPharmacy::create([
            'name' => 'Test SPAR', 'spar_store_id' => 'T-1', 'is_active' => true, 'supports_delivery' => true,
        ]);

        $this->staff = PharmacyUser::create([
            'name' => 'Staff', 'email' => 'staff@test.test', 'password' => Hash::make('secret'),
            'role' => 'pharmacy_staff', 'spar_pharmacy_id' => $this->pharmacy->id, 'is_active' => true,
        ]);
    }

    public function test_standalone_binds_null_telehealth_and_no_online_consult(): void
    {
        $bridge = app(\Zapmed\SparCore\Contracts\TelehealthBridge::class);
        $this->assertInstanceOf(\Zapmed\SparCore\Services\Telehealth\NullTelehealthBridge::class, $bridge);
        $this->assertFalse($bridge->offersOnlineConsult()); // AC-4
    }

    public function test_standalone_identity_provider_is_bound(): void
    {
        $this->assertInstanceOf(
            \App\Spar\StandaloneSparIdentityProvider::class,
            app(\Zapmed\SparCore\Contracts\SparIdentityProvider::class)
        );
    }

    public function test_guest_is_redirected_to_staff_login(): void
    {
        $this->get('/')->assertRedirect(route('staff.login'));
        $this->get('/login')->assertOk();
    }

    public function test_staff_screens_render_when_authenticated(): void
    {
        $this->actingAs($this->staff);

        $this->get(route('spar.dashboard'))->assertOk();
        $this->get(route('spar.patients'))->assertOk();
        $this->get(route('spar.capture'))->assertOk();
    }

    public function test_admin_screens_render_when_authenticated(): void
    {
        $admin = PharmacyUser::create([
            'name' => 'Admin', 'email' => 'admin@test.test', 'password' => Hash::make('secret'),
            'role' => 'admin', 'spar_pharmacy_id' => null, 'is_active' => true,
        ]);
        $this->actingAs($admin);

        $this->get(route('admin.spar.dashboard'))->assertOk();
        $this->get(route('admin.spar.pharmacies'))->assertOk();
        $this->get(route('admin.spar.imports'))->assertOk();
        $this->get(route('admin.spar.exceptions'))->assertOk();
        $this->get(route('admin.spar.consent'))->assertOk();
        $this->get(route('admin.spar.reporting'))->assertOk();
    }

    public function test_patient_tracker_consent_gate_and_dependant_rollup(): void
    {
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $this->pharmacy->id,
            'profile_code' => 'P-1', 'dependent_code' => '0',
            'first_name' => 'Sam', 'last_name' => 'Test',
            'cellphone' => '0820000000',
            'is_primary_member' => true, 'is_active' => true,
            'onboarding_status' => 'active', 'consent_status' => 'opted_in',
            'consent_given_at' => now(),
        ]);

        // Establish the no-login patient session (as the signed link would).
        app(SparPatientSession::class)->establish($patient);
        app(SparPatientSession::class)->markVerified();

        $this->get(route('my-meds.track'))->assertOk();
    }
}
