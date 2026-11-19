<?php

namespace Tests\Feature;

use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\Admin\SparExceptions;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPrescriptionJourney;

/**
 * Exceptions screen: pharmacy staff see only their own store's renewals, and a
 * snoozed renewal drops off the list until it wakes.
 */
class SparExceptionsScopeTest extends TestCase
{
    use RefreshDatabase;

    private function journeyAt(SparPharmacy $pharmacy, string $profile): SparPrescriptionJourney
    {
        $patient = SparPatient::create([
            'spar_pharmacy_id' => $pharmacy->id,
            'profile_code' => $profile,
            'dependent_code' => '0',
            'is_primary_member' => true,
        ]);

        return SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => 'S-' . $profile,
            'status' => 'renewal_due',
            'total_dispenses' => 6,
            'dispenses_completed' => 6,
            'start_date' => now()->subMonths(7),
            'renewal_due_date' => now()->subDays(10),
        ]);
    }

    public function test_staff_see_only_their_pharmacy_and_snoozed_items_drop_off(): void
    {
        $mine = SparPharmacy::create(['name' => 'Store A', 'spar_store_id' => 'A1', 'is_active' => true]);
        $other = SparPharmacy::create(['name' => 'Store B', 'spar_store_id' => 'B1', 'is_active' => true]);

        $ownJourney = $this->journeyAt($mine, '1001');
        $this->journeyAt($other, '2002');

        $staff = PharmacyUser::create([
            'name' => 'Store A staff',
            'email' => 'a@sparmeds.test',
            'password' => bcrypt('Testing123!'),
            'role' => 'pharmacy_staff',
            'spar_pharmacy_id' => $mine->id,
            'is_active' => true,
        ]);
        $this->actingAs($staff);

        $component = Livewire::test(SparExceptions::class);
        $ids = $component->instance()->missingRenewals->pluck('id')->all();
        $this->assertSame([$ownJourney->id], $ids);

        $component->call('snoozeItem', SparPrescriptionJourney::class, $ownJourney->id, 7);
        $this->assertSame([], Livewire::test(SparExceptions::class)->instance()->missingRenewals->pluck('id')->all());
    }
}
