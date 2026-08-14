<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetupTestAccounts extends Command
{
    protected $signature = 'zapmed:setup-test-accounts';

    protected $description = 'Create or reset demo accounts for testing (admin, doctor, patient)';

    public function handle(): int
    {
        $password = 'Testing123!';

        // Admin
        $admin = $this->upsertUser('admin@zapmed.co.za', [
            'first_name' => 'Zan',
            'last_name' => 'Admin',
            'role' => UserRole::Admin,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->info("✓ Admin: admin@zapmed.co.za");

        // Patient
        $patient = $this->upsertUser('patient@zapmed.co.za', [
            'first_name' => 'Demo',
            'last_name' => 'Patient',
            'role' => UserRole::Patient,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->info("✓ Patient: patient@zapmed.co.za");

        // Doctor
        $doctor = $this->upsertUser('doctor@zapmed.co.za', [
            'first_name' => 'Sarah',
            'last_name' => 'Naidoo',
            'role' => UserRole::Doctor,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Ensure doctor profile exists
        DoctorProfile::updateOrCreate(
            ['user_id' => $doctor->id],
            [
                'hpcsa_number' => 'MP0345678',
                'speciality' => 'General Practitioner',
                'qualification' => 'MBChB (UCT)',
                'university' => 'University of Cape Town',
                'year_qualified' => 2015,
                'bio' => 'Experienced GP with a focus on preventative care and chronic disease management.',
                'consultation_fee' => 45000,
                'followup_fee' => 30000,
                'consultation_duration' => 30,
                'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                'available_from' => '08:00',
                'available_to' => '17:00',
                'accepts_new_patients' => true,
                'is_verified' => true,
            ]
        );
        $this->info("✓ Doctor: doctor@zapmed.co.za (Dr. Sarah Naidoo, GP)");

        $this->newLine();
        $this->info("All accounts set with password: {$password}");
        $this->info("Login at: /login");

        return Command::SUCCESS;
    }

    /**
     * Create or update a user by email, handling member_number conflicts.
     */
    private function upsertUser(string $email, array $attributes): User
    {
        $user = User::withTrashed()->where('email', $email)->first();

        if ($user) {
            // Restore if soft-deleted
            if ($user->trashed()) {
                $user->restore();
            }
            $user->update($attributes);

            return $user->fresh();
        }

        // Generate a unique member_number manually to avoid boot hook collision
        $maxNumber = User::withTrashed()
            ->whereNotNull('member_number')
            ->get()
            ->map(fn ($u) => (int) substr($u->member_number, 3))
            ->max() ?? 0;

        $attributes['member_number'] = 'ZM-' . str_pad($maxNumber + 1, 6, '0', STR_PAD_LEFT);

        // Create new with explicit member_number (boot hook skips when not empty)
        return User::create(array_merge(['email' => $email], $attributes));
    }
}
