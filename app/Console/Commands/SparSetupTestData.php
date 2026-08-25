<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\SparDispenseRecord;
use App\Models\SparOrder;
use App\Models\SparPatient;
use App\Models\SparPharmacy;
use App\Models\SparPrescriptionJourney;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SparSetupTestData extends Command
{
    protected $signature = 'spar:setup-test-data';
    protected $description = 'Create SPAR test pharmacies, patients, and a pharmacy staff login';

    public function handle(): int
    {
        $this->info('Setting up SPAR test data...');

        // Create test pharmacies
        $megaCity = SparPharmacy::updateOrCreate(
            ['spar_store_id' => '1184679'],
            [
                'name' => 'Pharmacy at SPAR - Mega City',
                'bhf_code' => '1184679',
                'phone' => '012 345 6789',
                'city' => 'Johannesburg',
                'province' => 'Gauteng',
                'supports_delivery' => true,
                'delivery_fee' => 5000,
                'is_active' => true,
            ]
        );

        $robberg = SparPharmacy::updateOrCreate(
            ['spar_store_id' => '6067549'],
            [
                'name' => 'Pharmacy at SPAR - Robberg',
                'bhf_code' => '6067549',
                'phone' => '044 533 1234',
                'city' => 'Plettenberg Bay',
                'province' => 'Western Cape',
                'supports_delivery' => true,
                'delivery_fee' => 6000,
                'is_active' => true,
            ]
        );

        $this->info("  Created pharmacies: Mega City, Robberg");

        // Create pharmacy staff user
        $staffUser = User::updateOrCreate(
            ['email' => 'spar@zapmed.co.za'],
            [
                'first_name' => 'SPAR',
                'last_name' => 'Pharmacist',
                'phone' => '0821234567',
                'role' => UserRole::PharmacyStaff,
                'password' => Hash::make('Testing123!'),
                'is_active' => true,
                'spar_pharmacy_id' => $megaCity->id,
                'email_verified_at' => now(),
            ]
        );
        $this->info("  Created pharmacy staff: spar@zapmed.co.za / Testing123!");

        // Create test patients
        $patients = [];
        $names = [
            ['first_name' => 'Maria', 'last_name' => 'Ndlovu', 'profile_code' => '550'],
            ['first_name' => 'Johan', 'last_name' => 'van der Merwe', 'profile_code' => '25751'],
            ['first_name' => 'Thabo', 'last_name' => 'Mokoena', 'profile_code' => '1842'],
            ['first_name' => 'Fatima', 'last_name' => 'Adams', 'profile_code' => '9923'],
            ['first_name' => 'Peter', 'last_name' => 'Smith', 'profile_code' => '3301'],
        ];

        foreach ($names as $data) {
            $user = User::updateOrCreate(
                ['email' => strtolower($data['first_name']) . '.spar@test.co.za'],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'phone' => '08' . rand(10000000, 99999999),
                    'role' => UserRole::Patient,
                    'password' => Hash::make('Testing123!'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $pharmacy = rand(0, 1) ? $megaCity : $robberg;

            $sparPatient = SparPatient::updateOrCreate(
                ['profile_code' => $data['profile_code'], 'spar_pharmacy_id' => $pharmacy->id, 'dependent_code' => '0'],
                [
                    'user_id' => $user->id,
                    'medical_aid_name' => collect(['GEMSP', 'Discovery', 'Bonitas', 'Momentum'])->random(),
                    'medical_aid_option' => 'Standard',
                    'consent_status' => collect(['opted_in', 'opted_in', 'opted_in', 'pending'])->random(),
                    'consent_given_at' => now()->subDays(rand(1, 60)),
                    'consent_channel' => 'whatsapp',
                    'is_primary_member' => true,
                    'is_active' => true,
                ]
            );

            $patients[] = $sparPatient;
        }

        $this->info("  Created 5 test patients");

        // Create journeys with dispense records
        foreach ($patients as $patient) {
            $journey = SparPrescriptionJourney::updateOrCreate(
                ['spar_patient_id' => $patient->id, 'script_number' => 'SC-' . rand(100000, 999999)],
                [
                    'spar_pharmacy_id' => $patient->spar_pharmacy_id,
                    'status' => collect(['active', 'active', 'active', 'renewal_due'])->random(),
                    'total_dispenses' => 6,
                    'dispenses_completed' => $completed = rand(1, 5),
                    'start_date' => now()->subMonths($completed),
                    'next_dispense_date' => now()->addDays(rand(-5, 14)),
                    'renewal_due_date' => now()->addMonths(6 - $completed),
                    'doctor_name' => collect(['Dr. Ngcobo SR', 'Dr. Nel H', 'Dr. Patel A'])->random(),
                    'doctor_bhf' => '0' . rand(100000, 999999),
                    'medications' => [
                        ['name' => 'CO-COPALIA 10MG/160MG/12.5MG TAB 28', 'nappi_code' => '3001675001', 'quantity' => 1, 'value' => 24238],
                        ['name' => 'ASPAVOR 10MG TAB 30', 'nappi_code' => '708121001', 'quantity' => 1, 'value' => 3484],
                    ],
                ]
            );

            // Create dispense records for completed months
            for ($i = 1; $i <= $completed; $i++) {
                SparDispenseRecord::updateOrCreate(
                    ['journey_id' => $journey->id, 'dispense_number' => $i],
                    [
                        'spar_patient_id' => $patient->id,
                        'status' => 'collected',
                        'due_date' => $journey->start_date->copy()->addMonths($i - 1),
                        'completed_at' => $journey->start_date->copy()->addMonths($i - 1)->addDays(rand(0, 5)),
                        'fulfillment_type' => collect(['collection', 'collection', 'delivery'])->random(),
                        'document_number' => (string) rand(400000, 500000),
                        'sales_value' => rand(10000, 50000),
                        'items' => $journey->medications,
                    ]
                );
            }

            // Create upcoming dispense
            if ($journey->status === 'active') {
                SparDispenseRecord::updateOrCreate(
                    ['journey_id' => $journey->id, 'dispense_number' => $completed + 1],
                    [
                        'spar_patient_id' => $patient->id,
                        'status' => rand(0, 1) ? 'upcoming' : 'reminded',
                        'due_date' => now()->addDays(rand(-3, 10)),
                        'reminded_at' => rand(0, 1) ? now()->subDays(2) : null,
                    ]
                );
            }
        }

        $this->info("  Created journeys + dispense records");

        // Create a couple orders
        SparOrder::updateOrCreate(
            ['reference' => 'SP-TEST0001'],
            [
                'spar_patient_id' => $patients[0]->id,
                'spar_pharmacy_id' => $megaCity->id,
                'type' => 'collection',
                'status' => 'requested',
            ]
        );

        SparOrder::updateOrCreate(
            ['reference' => 'SP-TEST0002'],
            [
                'spar_patient_id' => $patients[1]->id,
                'spar_pharmacy_id' => $megaCity->id,
                'type' => 'delivery',
                'status' => 'preparing',
                'delivery_address' => '45 Long Street',
                'delivery_city' => 'Johannesburg',
                'delivery_postal_code' => '2001',
                'delivery_phone' => '0829876543',
                'prepared_at' => now()->subHours(2),
            ]
        );

        $this->info("  Created 2 test orders");

        $this->newLine();
        $this->info('=== SPAR Test Logins ===');
        $this->table(
            ['Role', 'Email', 'Password', 'URL'],
            [
                ['Admin', 'admin@zapmed.co.za', 'Testing123!', '/admin/spar'],
                ['Pharmacy Staff', 'spar@zapmed.co.za', 'Testing123!', '/spar/dashboard'],
            ]
        );

        $this->newLine();
        $this->info('Done! You can now log in and see the SPAR dashboards.');

        return self::SUCCESS;
    }
}
