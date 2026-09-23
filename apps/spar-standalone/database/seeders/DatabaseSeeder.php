<?php

namespace Database\Seeders;

use App\Models\PharmacyUser;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Standalone seed data (tasks 4.5/4.6 + Phase 7 hierarchy).
 *
 * Creates a complete FOUR-TIER login set so every management screen and scope
 * boundary can be exercised by hand, plus a group with two pharmacies and one
 * patient per onboarding state. All idempotent (updateOrCreate/firstOrCreate)
 * so `db:seed` and `migrate:fresh --seed` always converge to the same set.
 *
 * Logins (all password: Testing123!):
 *   super_admin     superadmin@sparmeds.test   → sees ALL groups/pharmacies
 *   group_admin     groupadmin@sparmeds.test   → scoped to the demo group
 *   pharmacy_admin  pharmadmin@sparmeds.test   → scoped to pharmacy A
 *   pharmacy_staff  staff@sparmeds.test        → scoped to pharmacy A
 *
 * The legacy admin@sparmeds.test (role 'admin' = super_admin) is retained for
 * back-compat with anything that referenced it.
 */
class DatabaseSeeder extends Seeder
{
    private const PASSWORD = 'Testing123!';

    public function run(): void
    {
        // --- Group (tier 1 container) ------------------------------------
        $group = SparPharmacyGroup::firstOrCreate(
            ['slug' => 'spar-western-cape'],
            [
                'name' => 'SPAR',
                'region' => 'Gauteng',
                'contact_name' => 'Regional Manager',
                'contact_email' => 'region@sparmeds.test',
                'contact_phone' => '0121230000',
                'is_active' => true,
            ]
        );
        // Keep the group name/region current even if the row already existed.
        $group->update(['name' => 'SPAR', 'region' => 'Gauteng']);

        // --- Single demo pharmacy: SPAR Wapadrand (the branch we're pitching) -
        // BHF 9900010 matches the Store/Bhf in the Wapadrand demo import so all
        // imported patients + dispenses attach to THIS pharmacy.
        $pharmacyA = SparPharmacy::updateOrCreate(
            ['spar_store_id' => 'SB-STANDALONE-01'],
            [
                'group_id' => $group->id,
                'name' => 'SPAR Pharmacy — Wapadrand',
                'bhf_code' => '9900010',
                'city' => 'Wapadrand, Pretoria',
                'province' => 'Gauteng',
                'supports_delivery' => true,
                'delivery_fee' => 5000,
                'is_active' => true,
            ]
        );

        // Remove the old demo pharmacies (Plettenberg Bay / Knysna) so Wapadrand
        // is the only branch in the demo. Any patients/journeys they still hold
        // are repointed to Wapadrand first so nothing is orphaned.
        $stale = SparPharmacy::whereIn('spar_store_id', ['SB-STANDALONE-02'])
            ->orWhere('name', 'like', '%Plettenberg%')
            ->orWhere('name', 'like', '%Knysna%')
            ->where('id', '!=', $pharmacyA->id)
            ->get();
        foreach ($stale as $old) {
            SparPatient::where('spar_pharmacy_id', $old->id)->update(['spar_pharmacy_id' => $pharmacyA->id]);
            SparPatient::where('onboarding_pharmacy_id', $old->id)->update(['onboarding_pharmacy_id' => $pharmacyA->id]);
            SparPrescriptionJourney::where('spar_pharmacy_id', $old->id)->update(['spar_pharmacy_id' => $pharmacyA->id]);
            \Zapmed\SparCore\Models\SparOrder::where('spar_pharmacy_id', $old->id)->update(['spar_pharmacy_id' => $pharmacyA->id]);
            $old->delete();
        }

        // --- Four-tier staff logins --------------------------------------
        // Tier 1: super admin (global — no group/pharmacy scope).
        PharmacyUser::updateOrCreate(
            ['email' => 'superadmin@sparmeds.test'],
            [
                'name' => 'Platform Super Admin',
                'password' => Hash::make(self::PASSWORD),
                'role' => 'super_admin',
                'spar_pharmacy_id' => null,
                'group_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Legacy super-admin alias kept for back-compat (role 'admin').
        PharmacyUser::updateOrCreate(
            ['email' => 'admin@sparmeds.test'],
            [
                'name' => 'Demo Admin (legacy)',
                'password' => Hash::make(self::PASSWORD),
                'role' => 'admin',
                'spar_pharmacy_id' => null,
                'group_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Tier 2: group admin (scoped to the group, no single pharmacy).
        PharmacyUser::updateOrCreate(
            ['email' => 'groupadmin@sparmeds.test'],
            [
                'name' => 'Group Admin — SPAR',
                'password' => Hash::make(self::PASSWORD),
                'role' => 'group_admin',
                'spar_pharmacy_id' => null,
                'group_id' => $group->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Tier 3: pharmacy admin (scoped to pharmacy A).
        PharmacyUser::updateOrCreate(
            ['email' => 'pharmadmin@sparmeds.test'],
            [
                'name' => 'Pharmacy Admin — Wapadrand',
                'password' => Hash::make(self::PASSWORD),
                'role' => 'pharmacy_admin',
                'spar_pharmacy_id' => $pharmacyA->id,
                'group_id' => $group->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Tier 4: pharmacy staff (scoped to pharmacy A).
        PharmacyUser::updateOrCreate(
            ['email' => 'staff@sparmeds.test'],
            [
                'name' => 'Demo Pharmacist',
                'password' => Hash::make(self::PASSWORD),
                'role' => 'pharmacy_staff',
                'spar_pharmacy_id' => $pharmacyA->id,
                'group_id' => $group->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info('Seeded 4-tier logins (all password Testing123!):');
        $this->command?->table(
            ['Role', 'Email', 'Scope'],
            [
                ['super_admin', 'superadmin@sparmeds.test', 'all'],
                ['admin (legacy super)', 'admin@sparmeds.test', 'all'],
                ['group_admin', 'groupadmin@sparmeds.test', 'SPAR'],
                ['pharmacy_admin', 'pharmadmin@sparmeds.test', 'Wapadrand'],
                ['pharmacy_staff', 'staff@sparmeds.test', 'Wapadrand'],
            ]
        );

        // Full-system demo data: real import + lifecycle states so every screen
        // has content. Skips gracefully if the demo files aren't present.
        $this->call(DemoSeeder::class);
    }
}
