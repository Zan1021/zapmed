<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use Database\Seeders\PartnerPractitionersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerPractitionersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_six_are_onboarded_and_selectable_for_a_weekday_slot(): void
    {
        $this->seed(PartnerPractitionersSeeder::class);

        $this->assertSame(6, DoctorProfile::verified()->acceptingPatients()->count());

        // Next Monday 10:00 — every practitioner must be offered for it.
        $date = now()->next('Monday')->format('Y-m-d');
        $available = DoctorProfile::getAvailableDoctorsForSlot($date, '10:00:00');
        $this->assertCount(6, $available);

        $avatar = $available->first()->user->avatar_url;
        $this->assertStringContainsString('/images/doctors/', $avatar);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(PartnerPractitionersSeeder::class);
        $this->seed(PartnerPractitionersSeeder::class);

        $this->assertSame(6, DoctorProfile::count());
    }
}
