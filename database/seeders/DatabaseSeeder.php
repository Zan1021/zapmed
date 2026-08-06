<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        User::factory()->create([
            'first_name' => 'Zan',
            'last_name' => 'Admin',
            'email' => 'admin@zapmed.co.za',
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Create test patient
        User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'email' => 'patient@zapmed.co.za',
            'role' => UserRole::Patient,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Seed doctors with profiles
        $this->call(DoctorSeeder::class);

        // Seed medications reference table
        $this->call(MedicationSeeder::class);
    }
}
