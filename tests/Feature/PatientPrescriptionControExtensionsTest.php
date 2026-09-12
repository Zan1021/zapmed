<?php

namespace Tests\Feature;

use App\Models\PatientProfile;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task 5 — patient_profile + prescription extensions for Contro (blueprint §2.1 / §2.7).
 */
class PatientPrescriptionControExtensionsTest extends TestCase
{
    use RefreshDatabase;

    private function profile(): PatientProfile
    {
        return PatientProfile::create(['user_id' => User::factory()->create()->id]);
    }

    public function test_patient_profile_holds_contro_fields(): void
    {
        $profile = $this->profile();
        $profile->update([
            'medical_aid_name' => 'Discovery',
            'medical_aid_plan' => 'Classic Saver',
            'payment_type' => 'medical_aid',
            'mobile_msisdn' => '+27821234567',
            'upstream_id' => 'hash_abc',
        ]);

        $fresh = $profile->fresh();
        $this->assertSame('medical_aid', $fresh->payment_type);
        $this->assertSame('+27821234567', $fresh->mobile_msisdn);
        $this->assertSame('contro', $fresh->upstream_source);
    }

    public function test_patient_can_have_multiple_labelled_addresses(): void
    {
        $profile = $this->profile();
        $profile->addresses()->create([
            'label' => 'delivery', 'address_line1' => '1 Main Rd', 'city' => 'Cape Town',
            'province' => 'WC', 'postal_code' => '8001', 'is_primary' => true, 'upstream_id' => 'addr_1',
        ]);
        $profile->addresses()->create([
            'label' => 'billing', 'address_line1' => 'PO Box 5', 'city' => 'Cape Town',
        ]);

        $this->assertSame(2, $profile->addresses()->count());
        $delivery = $profile->addresses()->where('label', 'delivery')->first();
        $this->assertTrue($delivery->is_primary);
        $this->assertSame('ZA', $delivery->country); // default
    }

    public function test_address_geocode_fields(): void
    {
        $profile = $this->profile();
        $addr = $profile->addresses()->create([
            'label' => 'home', 'latitude' => -33.9248690, 'longitude' => 18.4240760,
        ]);

        $this->assertSame('-33.9248690', (string) $addr->fresh()->latitude);
        $this->assertSame('18.4240760', (string) $addr->fresh()->longitude);
    }

    public function test_prescription_holds_contro_repeat_and_pricing_fields(): void
    {
        $patient = User::factory()->create();
        $doctor = User::factory()->create();
        $rx = Prescription::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'status' => 'issued',
            'repeat_cycle_days' => 30,
            'next_repeat_date' => now()->addMonth()->toDateString(),
            'pharmacy_script_ref' => 'CONTRO-SCRIPT-123',
            'total_medication_cost_minor' => 45000,
            'service_fee_minor' => 5000,
            'delivery_method' => 'courier',
            'upstream_id' => 'rx_1',
        ]);

        $fresh = $rx->fresh();
        $this->assertSame(30, $fresh->repeat_cycle_days);
        $this->assertSame(45000, $fresh->total_medication_cost_minor);
        $this->assertSame(5000, $fresh->service_fee_minor);
        $this->assertSame('CONTRO-SCRIPT-123', $fresh->pharmacy_script_ref);
        // pharmacy_script_ref (Contro) is DISTINCT from our own pharmacy_reference.
        $this->assertNull($fresh->pharmacy_reference);
        $this->assertSame('contro', $fresh->upstream_source);
    }

    public function test_address_crosswalk_is_idempotent(): void
    {
        $profile = $this->profile();
        $profile->addresses()->create(['label' => 'delivery', 'upstream_id' => 'addr_x', 'upstream_source' => 'contro']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $profile->addresses()->create(['label' => 'billing', 'upstream_id' => 'addr_x', 'upstream_source' => 'contro']);
    }

    public function test_recipient_tables_have_crosswalk(): void
    {
        foreach (['patient_profiles', 'patient_addresses', 'prescriptions'] as $table) {
            $this->assertTrue(
                Schema::hasColumns($table, ['upstream_id', 'upstream_source', 'upstream_synced_at']),
                "{$table} missing crosswalk columns"
            );
        }
    }
}
