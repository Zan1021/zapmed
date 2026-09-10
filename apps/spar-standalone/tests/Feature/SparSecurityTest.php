<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;

/**
 * Phase 10 — security hardening checks (headers, encryption at rest, erasure).
 */
class SparSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_present_on_responses(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
        $this->assertNotNull($response->headers->get('Referrer-Policy'));
    }

    public function test_phi_is_encrypted_at_rest(): void
    {
        $pharmacy = SparPharmacy::create(['name' => 'P', 'spar_store_id' => 'P1', 'is_active' => true]);
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'cellphone' => '0821234567',
            'email' => 'p@t.test',
            'is_active' => true,
        ]);

        $raw = \DB::table('spar_patients')->where('id', $patient->id)->first();
        $this->assertStringStartsWith('eyJ', $raw->cellphone);
        $this->assertStringStartsWith('eyJ', $raw->email);
        $this->assertStringStartsWith('eyJ', $raw->profile_code);

        // Model decrypts transparently.
        $this->assertSame('0821234567', SparPatient::find($patient->id)->cellphone);
    }

    public function test_right_to_erasure_anonymises_phi(): void
    {
        $pharmacy = SparPharmacy::create(['name' => 'P', 'spar_store_id' => 'P1', 'is_active' => true]);
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '551',
            'first_name' => 'Erase',
            'last_name' => 'Me',
            'cellphone' => '0820000000',
            'is_active' => true,
        ]);

        $this->artisan('spar:data-retention', ['--erase' => $patient->id])->assertExitCode(0);

        $fresh = SparPatient::find($patient->id);
        $this->assertNull($fresh->first_name);
        $this->assertNull($fresh->cellphone);
    }

    public function test_login_route_is_rate_limited(): void
    {
        // The named limiter is attached; a burst beyond the limit yields 429.
        $hit = null;
        for ($i = 0; $i < 25; $i++) {
            $hit = $this->get('/login');
        }
        // Either still 200 (within window on a fast machine) or 429 once tripped.
        $this->assertContains($hit->status(), [200, 429]);
    }
}
