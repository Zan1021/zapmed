<?php

namespace Tests\Feature\Spar;

use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 1.2 backfill — populate SPAR identity from a linked ZapMed User.
 * Integrated-host only; never overwrites captured data; refreshes onboarding.
 */
class SparBackfillIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['spar.host_mode' => 'integrated']);
    }

    private function pharmacy(): SparPharmacy
    {
        return SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'is_active' => true,
        ]);
    }

    public function test_backfills_empty_identity_from_linked_user_and_onboards(): void
    {
        $user = User::factory()->create([
            'role' => 'patient',
            'first_name' => 'Thabo',
            'last_name' => 'Mokoena',
            'phone' => '0821234567',
            'email' => 'thabo@example.co.za',
        ]);

        $patient = SparPatient::create([
            'user_id' => $user->id,
            'spar_pharmacy_id' => $this->pharmacy()->id,
            'profile_code' => '550',
            'consent_status' => 'pending',
            'is_active' => true,
        ]);

        // No identity yet.
        $this->assertFalse($patient->hasCompleteIdentity());

        $this->artisan('spar:backfill-identity')->assertExitCode(0);

        $patient->refresh();
        $this->assertSame('Thabo', $patient->first_name);
        $this->assertSame('Mokoena', $patient->last_name);
        $this->assertSame('0821234567', $patient->cellphone);
        $this->assertSame('thabo@example.co.za', $patient->email);
        // Complete identity + still pending consent => pending_consent.
        $this->assertSame('pending_consent', $patient->onboarding_status);
    }

    public function test_never_overwrites_already_captured_identity(): void
    {
        $user = User::factory()->create([
            'role' => 'patient',
            'first_name' => 'FromUser',
            'phone' => '0820000000',
            'email' => 'fromuser@example.co.za',
        ]);

        $patient = SparPatient::create([
            'user_id' => $user->id,
            'spar_pharmacy_id' => $this->pharmacy()->id,
            'profile_code' => '551',
            'first_name' => 'Captured',        // pharmacist already entered this
            'cellphone' => '0829998888',       // and this
            'consent_status' => 'pending',
            'is_active' => true,
        ]);

        $this->artisan('spar:backfill-identity')->assertExitCode(0);

        $patient->refresh();
        // Captured values are preserved...
        $this->assertSame('Captured', $patient->first_name);
        $this->assertSame('0829998888', $patient->cellphone);
        // ...but empty fields ARE filled from the user.
        $this->assertSame('fromuser@example.co.za', $patient->email);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $user = User::factory()->create([
            'role' => 'patient',
            'first_name' => 'Thabo',
            'phone' => '0821234567',
        ]);

        $patient = SparPatient::create([
            'user_id' => $user->id,
            'spar_pharmacy_id' => $this->pharmacy()->id,
            'profile_code' => '552',
            'consent_status' => 'pending',
            'is_active' => true,
        ]);

        $this->artisan('spar:backfill-identity --dry-run')->assertExitCode(0);

        $patient->refresh();
        $this->assertNull($patient->first_name);
        $this->assertNull($patient->cellphone);
    }

    public function test_standalone_mode_is_a_noop(): void
    {
        config(['spar.host_mode' => 'standalone']);

        $this->artisan('spar:backfill-identity')
            ->expectsOutputToContain('not "integrated"')
            ->assertExitCode(0);
    }
}
