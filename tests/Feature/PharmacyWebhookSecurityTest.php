<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Item 17 — pharmacy status webhook must verify an HMAC-SHA256 signature
 * before changing any prescription state. Unsigned / wrong-signature requests
 * are rejected (401); correctly-signed ones are processed.
 */
class PharmacyWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function prescription(): Prescription
    {
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $patient = User::factory()->create(['role' => UserRole::Patient]);

        return Prescription::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => 'signed',
            'total_amount' => 5000,
            'payment_status' => 'paid',
            'pharmacy_status' => 'pending',
            'signed_at' => now(),
        ]);
    }

    private function payload(Prescription $p): array
    {
        return [
            'prescription_reference' => $p->reference,
            'status' => 'dispatched',
            'tracking_number' => 'TRK123',
            'courier' => 'Aramex',
        ];
    }

    public function test_unsigned_request_is_rejected(): void
    {
        config(['services.pharmacy.webhook_secret' => 'test-secret']);
        $p = $this->prescription();

        $this->postJson(route('pharmacy.webhook'), $this->payload($p))
            ->assertStatus(401);

        $this->assertSame('pending', $p->fresh()->pharmacy_status);
    }

    public function test_wrong_signature_is_rejected(): void
    {
        config(['services.pharmacy.webhook_secret' => 'test-secret']);
        $p = $this->prescription();

        $this->withHeaders(['X-Pharmacy-Signature' => 'deadbeef'])
            ->postJson(route('pharmacy.webhook'), $this->payload($p))
            ->assertStatus(401);

        $this->assertSame('pending', $p->fresh()->pharmacy_status);
    }

    public function test_valid_signature_is_accepted(): void
    {
        config(['services.pharmacy.webhook_secret' => 'test-secret']);
        $p = $this->prescription();
        $payload = $this->payload($p);

        // Sign the exact JSON body we will send.
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'test-secret');

        $this->call(
            'POST',
            route('pharmacy.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_PHARMACY_SIGNATURE' => $signature,
            ],
            $body
        )->assertOk();

        $this->assertSame('in_transit', $p->fresh()->pharmacy_status);
    }

    public function test_missing_secret_fails_closed(): void
    {
        config(['services.pharmacy.webhook_secret' => null]);
        $p = $this->prescription();

        // Even a "signed" request is rejected when no secret is configured.
        $this->withHeaders(['X-Pharmacy-Signature' => 'anything'])
            ->postJson(route('pharmacy.webhook'), $this->payload($p))
            ->assertStatus(401);
    }
}
