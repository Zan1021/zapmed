<?php

namespace Tests\Feature\Spar;

use Zapmed\SparCore\Livewire\PharmacistCapture;
use App\Models\SparConsent;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SPAR standalone Phase 1.6 — pharmacist capture (Mode B onboarding).
 */
class SparPharmacistCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function staffFor(SparPharmacy $pharmacy): User
    {
        return User::factory()->create([
            'role' => 'pharmacy_staff',
            'spar_pharmacy_id' => $pharmacy->id,
        ]);
    }

    private function pharmacy(): SparPharmacy
    {
        return SparPharmacy::create([
            'name' => 'Pharmacy at SPAR - Test',
            'spar_store_id' => '3000001',
            'is_active' => true,
        ]);
    }

    public function test_capture_populates_identity_and_records_consent_and_onboards(): void
    {
        $pharmacy = $this->pharmacy();
        $staff = $this->staffFor($pharmacy);

        // Imported (Mode B) with no contact.
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '550',
            'onboarding_status' => 'awaiting_contact',
            'is_primary_member' => true,
            'is_active' => true,
        ]);

        Livewire::actingAs($staff)
            ->test(PharmacistCapture::class)
            ->call('edit', $patient->id)
            ->set('firstName', 'Thabo')
            ->set('lastName', 'Mokoena')
            ->set('cellphone', '0821234567')
            ->set('consentConfirmed', true)
            ->set('consentChannel', 'in_store')
            ->call('save')
            ->assertHasNoErrors();

        $patient->refresh();
        $this->assertSame('Thabo', $patient->first_name);
        $this->assertSame('0821234567', $patient->cellphone);
        $this->assertTrue($patient->hasConsented());
        $this->assertSame('active', $patient->onboarding_status);

        // Consent evidence recorded with pharmacist source.
        $consent = SparConsent::where('spar_patient_id', $patient->id)->granted()->first();
        $this->assertNotNull($consent);
        $this->assertStringStartsWith('pharmacist:', $consent->source);
        $this->assertSame('in_store', $consent->channel);
    }

    public function test_capture_requires_at_least_one_contact_channel(): void
    {
        $pharmacy = $this->pharmacy();
        $staff = $this->staffFor($pharmacy);

        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => '551',
            'onboarding_status' => 'awaiting_contact',
            'is_primary_member' => true,
            'is_active' => true,
        ]);

        Livewire::actingAs($staff)
            ->test(PharmacistCapture::class)
            ->call('edit', $patient->id)
            ->set('firstName', 'No')
            ->set('lastName', 'Contact')
            ->set('cellphone', '')
            ->set('email', '')
            ->call('save')
            ->assertHasErrors('cellphone');

        $this->assertSame('awaiting_contact', $patient->fresh()->onboarding_status);
    }

    public function test_staff_cannot_capture_another_stores_patient(): void
    {
        $mine = $this->pharmacy();
        $other = SparPharmacy::create(['name' => 'Other SPAR', 'spar_store_id' => '9', 'is_active' => true]);
        $staff = $this->staffFor($mine);

        $otherPatient = SparPatient::create([
            'spar_pharmacy_id' => $other->id,
            'profile_code' => '999',
            'onboarding_status' => 'awaiting_contact',
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($staff)
            ->test(PharmacistCapture::class)
            ->call('edit', $otherPatient->id);
    }
}
