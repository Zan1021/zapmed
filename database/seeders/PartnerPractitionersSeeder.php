<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\DoctorAvailability;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Onboards the partner doctors and registered dietitians shown on the marketing site
 * so every one of them appears in the booking journey's "Choose your doctor" step.
 *
 * Idempotent: safe to re-run (matches on email / profile / availability slot).
 *
 * NOTE: HPCSA/registration numbers and emails below are PLACEHOLDERS (marked PENDING /
 * @example.com) until the real details are supplied. Replace them in the admin area.
 */
class PartnerPractitionersSeeder extends Seeder
{
    /** Weekday availability, 15-minute slots (matches the existing doctor's pattern). */
    private const DAYS = [1, 2, 3, 4, 5];
    private const START = '09:00';
    private const END = '17:00';

    public function run(): void
    {
        foreach ($this->practitioners() as $p) {
            $user = User::updateOrCreate(
                ['email' => $p['email']],
                [
                    'first_name' => $p['first_name'],
                    'last_name' => $p['last_name'],
                    'role' => UserRole::Doctor,
                    // Random, unknown password: they sign in via "forgot password" once real emails are set.
                    'password' => bcrypt(Str::random(32)),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'avatar_path' => 'images/doctors/' . $p['photo'],
                ]
            );

            $profile = DoctorProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'hpcsa_number' => $p['reg'],
                    'speciality' => $p['speciality'],
                    'qualification' => $p['qualification'],
                    'consultation_fee' => 45000,
                    'followup_fee' => 30000,
                    'consultation_duration' => 30,
                    'available_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                    'available_from' => self::START,
                    'available_to' => self::END,
                    'accepts_new_patients' => true,
                    'is_verified' => true,
                ]
            );

            $this->seedAvailability($profile);
        }
    }

    private function seedAvailability(DoctorProfile $profile): void
    {
        foreach (self::DAYS as $day) {
            $t = strtotime(self::START);
            $end = strtotime(self::END);
            while ($t < $end) {
                DoctorAvailability::updateOrCreate(
                    [
                        'doctor_profile_id' => $profile->id,
                        'day_of_week' => $day,
                        'start_time' => date('H:i:s', $t),
                    ],
                    [
                        'end_time' => date('H:i:s', $t + 900),
                        'is_active' => true,
                    ]
                );
                $t += 900;
            }
        }
    }

    private function practitioners(): array
    {
        $gp = 'General Practitioner';
        $rd = 'Registered Dietitian';

        return [
            ['first_name' => 'Bontle', 'last_name' => 'Ntshole', 'email' => 'bontle.ntshole@example.com',
                'photo' => 'bontle-ntshole.jpg', 'reg' => 'PENDING-NTSHOLE', 'speciality' => $gp,
                'qualification' => 'MBChB (Limpopo) | MBA'],
            ['first_name' => 'Gerhard', 'last_name' => 'Coetzer', 'email' => 'gerhard.coetzer@example.com',
                'photo' => 'gerhard-coetzer.jpg', 'reg' => 'PENDING-COETZER', 'speciality' => $gp,
                'qualification' => 'MBChB'],
            ['first_name' => 'Frances', 'last_name' => 'Earle', 'email' => 'frances.earle@example.com',
                'photo' => 'frances-earle.jpg', 'reg' => 'PENDING-EARLE', 'speciality' => $gp,
                'qualification' => 'MBChB (UP) | DipHIVMan (CMSA) | DipPEC (CMSA)'],
            ['first_name' => 'Anke', 'last_name' => 'van Zyl', 'email' => 'anke.vanzyl@example.com',
                'photo' => 'anke-van-zyl.jpg', 'reg' => 'PENDING-VANZYL', 'speciality' => $gp,
                'qualification' => 'MBChB (Stell) | DipObs (CMSA)'],
            ['first_name' => 'Amy', 'last_name' => 'Burger', 'email' => 'amy.burger@example.com',
                'photo' => 'amy-burger.jpg', 'reg' => 'PENDING-DT-BURGER', 'speciality' => $rd,
                'qualification' => 'RD(SA)'],
            ['first_name' => 'Angelique', 'last_name' => 'Smith', 'email' => 'angelique.smith@example.com',
                'photo' => 'angelique-smith.jpg', 'reg' => 'PENDING-DT-SMITH', 'speciality' => $rd,
                'qualification' => 'RD(SA) | BSc Dietetics (Stellenbosch)'],
        ];
    }
}
