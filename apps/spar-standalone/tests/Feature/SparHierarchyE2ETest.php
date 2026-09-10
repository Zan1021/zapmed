<?php

namespace Tests\Feature;

use App\Livewire\Admin\StaffManagement;
use App\Models\PharmacyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;
use Zapmed\SparCore\Livewire\Admin\SparGroups;
use Zapmed\SparCore\Livewire\Admin\SparPharmacies;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;

/**
 * Phase 7.7 — end-to-end hierarchy + scope isolation (spec AC-11, AC-12, AC-13).
 * Super-admin builds group → pharmacy → staff → login; new staff logs in and
 * sees ONLY their store.
 */
class SparHierarchyE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_ac11_super_admin_full_create_chain_then_staff_scoped_login(): void
    {
        // --- Super-admin builds the hierarchy ---------------------------------
        $super = PharmacyUser::create([
            'name' => 'Super', 'email' => 'super@t.test', 'password' => Hash::make('secret1234'),
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $this->actingAs($super);

        // 1) create a group
        Livewire::test(SparGroups::class)
            ->call('create')->set('name', 'SPAR Coastal')->call('save')->assertHasNoErrors();
        $group = SparPharmacyGroup::where('name', 'SPAR Coastal')->firstOrFail();

        // 2) create a pharmacy under it
        Livewire::test(SparPharmacies::class)
            ->call('create')
            ->set('group_id', $group->id)
            ->set('name', 'Coastal Central')
            ->set('spar_store_id', 'CC-1')
            ->call('save')->assertHasNoErrors();
        $pharmacy = SparPharmacy::where('spar_store_id', 'CC-1')->firstOrFail();

        // 3) create a pharmacy_staff login for that pharmacy
        Livewire::test(StaffManagement::class)
            ->call('create')
            ->set('name', 'Clerk')
            ->set('email', 'clerk@coastal.test')
            ->set('password', 'secret1234')
            ->set('role', 'pharmacy_staff')
            ->set('spar_pharmacy_id', $pharmacy->id)
            ->call('save')->assertHasNoErrors();

        $clerk = PharmacyUser::where('email', 'clerk@coastal.test')->firstOrFail();
        $this->assertSame($pharmacy->id, $clerk->spar_pharmacy_id);

        // --- A patient at this pharmacy and one elsewhere ---------------------
        $otherGroup = SparPharmacyGroup::create(['name' => 'Inland']);
        $otherPharm = SparPharmacy::create(['group_id' => $otherGroup->id, 'name' => 'Inland', 'spar_store_id' => 'IN-1', 'is_active' => true]);
        $mine = SparPatient::create(['spar_pharmacy_id' => $pharmacy->id, 'profile_code' => 'MINE', 'first_name' => 'Coastal', 'last_name' => 'Patient', 'is_active' => true, 'consent_status' => 'opted_in']);
        $other = SparPatient::create(['spar_pharmacy_id' => $otherPharm->id, 'profile_code' => 'OTHER', 'first_name' => 'Inland', 'last_name' => 'Patient', 'is_active' => true, 'consent_status' => 'opted_in']);
        // National model: associate each to its store via a journey.
        foreach ([[$mine, $pharmacy], [$other, $otherPharm]] as [$pt, $ph]) {
            \Zapmed\SparCore\Models\SparPrescriptionJourney::create([
                'spar_patient_id' => $pt->id, 'spar_pharmacy_id' => $ph->id,
                'script_number' => 'S-' . $pt->profile_code, 'status' => 'active',
                'total_dispenses' => 6, 'dispenses_completed' => 0, 'start_date' => now(), 'medications' => [],
            ]);
        }

        // --- New staff logs in → scoped to their store -----------------------
        $this->actingAs($clerk);

        $this->assertSame(1, SparPatient::visibleToCurrentActor()->count());
        $this->assertSame('MINE', SparPatient::visibleToCurrentActor()->first()->profile_code);

        // And cannot reach management screens.
        Livewire::test(StaffManagement::class)->assertForbidden();
        Livewire::test(SparGroups::class)->assertForbidden();
        Livewire::test(SparPharmacies::class)->assertForbidden();
    }
}
