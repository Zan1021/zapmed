<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    public function run(): void
    {
        $doctors = [
            [
                'first_name' => 'Sarah',
                'last_name' => 'Naidoo',
                'email' => 'doctor@zapmed.co.za',
                'profile' => [
                    'hpcsa_number' => 'MP0345678',
                    'speciality' => 'General Practitioner',
                    'qualification' => 'MBChB (UCT)',
                    'university' => 'University of Cape Town',
                    'year_qualified' => 2015,
                    'bio' => 'Experienced GP with a focus on preventative care and chronic disease management. Passionate about accessible healthcare.',
                    'consultation_fee' => 45000,
                    'followup_fee' => 30000,
                    'consultation_duration' => 30,
                    'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                    'available_from' => '08:00',
                    'available_to' => '17:00',
                    'accepts_new_patients' => true,
                    'is_verified' => true,
                ],
            ],
            [
                'first_name' => 'Raj',
                'last_name' => 'Patel',
                'email' => 'dr.patel@zapmed.co.za',
                'profile' => [
                    'hpcsa_number' => 'MP0456789',
                    'speciality' => 'General Practitioner',
                    'qualification' => 'MBChB (Wits), DipPEC',
                    'university' => 'University of the Witwatersrand',
                    'year_qualified' => 2012,
                    'bio' => 'Emergency medicine background with 12 years of clinical experience. Specialises in acute conditions and occupational health.',
                    'consultation_fee' => 50000,
                    'followup_fee' => 35000,
                    'consultation_duration' => 20,
                    'available_days' => ['mon', 'tue', 'wed', 'fri'],
                    'available_from' => '07:00',
                    'available_to' => '15:00',
                    'accepts_new_patients' => true,
                    'is_verified' => true,
                ],
            ],
            [
                'first_name' => 'Andile',
                'last_name' => 'Botha',
                'email' => 'dr.botha@zapmed.co.za',
                'profile' => [
                    'hpcsa_number' => 'MP0567890',
                    'speciality' => 'Family Medicine',
                    'qualification' => 'MBChB (Stellenbosch), MMed (Fam Med)',
                    'university' => 'Stellenbosch University',
                    'year_qualified' => 2010,
                    'bio' => 'Family medicine specialist with a holistic approach to patient care. Special interest in mental health and paediatrics.',
                    'consultation_fee' => 55000,
                    'followup_fee' => 35000,
                    'consultation_duration' => 30,
                    'available_days' => ['mon', 'wed', 'thu', 'fri', 'sat'],
                    'available_from' => '09:00',
                    'available_to' => '18:00',
                    'accepts_new_patients' => true,
                    'is_verified' => true,
                ],
            ],
            [
                'first_name' => 'Fatima',
                'last_name' => 'Moosa',
                'email' => 'dr.moosa@zapmed.co.za',
                'profile' => [
                    'hpcsa_number' => 'MP0678901',
                    'speciality' => 'Internal Medicine',
                    'qualification' => 'MBChB (UFS), FCP(SA)',
                    'university' => 'University of the Free State',
                    'year_qualified' => 2008,
                    'bio' => 'Internal medicine specialist managing complex chronic diseases. Expert in diabetes, hypertension, and metabolic disorders.',
                    'consultation_fee' => 65000,
                    'followup_fee' => 45000,
                    'consultation_duration' => 30,
                    'available_days' => ['tue', 'wed', 'thu'],
                    'available_from' => '10:00',
                    'available_to' => '16:00',
                    'accepts_new_patients' => false,
                    'is_verified' => true,
                ],
            ],
        ];

        foreach ($doctors as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'role' => UserRole::Doctor,
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );

            DoctorProfile::updateOrCreate(
                ['user_id' => $user->id],
                $data['profile']
            );
        }
    }
}
