<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Doctor\ManageAvailability;
use App\Models\Appointment;
use App\Models\DoctorAvailability;
use App\Models\DoctorBlockedDate;
use App\Models\DoctorProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 3 — doctor onboarding + availability -> bookability.
 *
 * Covers the chain proven in the Chrome E2E audit: a doctor with no availability
 * is gated to the availability page; adding a time range auto-generates 15-minute
 * slots; those slots make the doctor bookable; blocked dates and existing bookings
 * are correctly excluded from availability.
 */
class DoctorAvailabilityOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function makeDoctor(): array
    {
        $user = User::factory()->create(['role' => UserRole::Doctor]);
        $profile = DoctorProfile::create([
            'user_id' => $user->id,
            'hpcsa_number' => 'MP' . fake()->numerify('######'),
            'speciality' => 'General Practice',
            'qualification' => 'MBChB',
            'doctor_type' => 'locum',
            'is_verified' => true,
        ]);

        return [$user, $profile];
    }

    private function nextDayOfWeek(int $dow): string
    {
        $d = Carbon::today();
        while ((int) $d->dayOfWeek !== $dow) {
            $d->addDay();
        }

        return $d->format('Y-m-d');
    }

    public function test_doctor_without_availability_is_redirected_to_availability_page(): void
    {
        [$user] = $this->makeDoctor();

        $this->actingAs($user)
            ->get('/doctor/dashboard')
            ->assertRedirect(route('doctor.availability'));
    }

    public function test_add_time_range_generates_15_minute_slots(): void
    {
        [$user, $profile] = $this->makeDoctor();

        Livewire::actingAs($user)
            ->test(ManageAvailability::class)
            ->set('selectedDay', 3) // Wednesday
            ->set('rangeStartTime', '09:00')
            ->set('rangeEndTime', '10:00')
            ->call('addTimeRange');

        $slots = DoctorAvailability::where('doctor_profile_id', $profile->id)
            ->where('day_of_week', 3)
            ->orderBy('start_time')
            ->pluck('start_time')
            ->map(fn ($t) => substr($t, 0, 5))
            ->toArray();

        $this->assertSame(['09:00', '09:15', '09:30', '09:45'], $slots);
    }

    public function test_available_slots_returned_for_configured_day_and_empty_for_others(): void
    {
        [$user, $profile] = $this->makeDoctor();

        Livewire::actingAs($user)
            ->test(ManageAvailability::class)
            ->set('selectedDay', 3)
            ->set('rangeStartTime', '09:00')
            ->set('rangeEndTime', '10:00')
            ->call('addTimeRange');

        $wed = $this->nextDayOfWeek(3);
        $mon = $this->nextDayOfWeek(1);

        $this->assertSame(['09:00', '09:15', '09:30', '09:45'], $profile->getAvailableSlotsForDate($wed));
        $this->assertSame([], $profile->getAvailableSlotsForDate($mon));
        $this->assertTrue($profile->isAvailableOnDate($wed));
        $this->assertFalse($profile->isAvailableOnDate($mon));
    }

    public function test_blocked_date_removes_all_slots_for_that_date(): void
    {
        [$user, $profile] = $this->makeDoctor();

        Livewire::actingAs($user)
            ->test(ManageAvailability::class)
            ->set('selectedDay', 3)
            ->set('rangeStartTime', '09:00')
            ->set('rangeEndTime', '10:00')
            ->call('addTimeRange');

        $wed = $this->nextDayOfWeek(3);

        DoctorBlockedDate::create([
            'doctor_profile_id' => $profile->id,
            'blocked_date' => $wed,
            'reason' => 'Leave',
        ]);

        $this->assertSame([], $profile->getAvailableSlotsForDate($wed));
        $this->assertFalse($profile->isAvailableOnDate($wed));
    }

    public function test_booked_time_is_excluded_no_double_booking(): void
    {
        [$user, $profile] = $this->makeDoctor();

        Livewire::actingAs($user)
            ->test(ManageAvailability::class)
            ->set('selectedDay', 3)
            ->set('rangeStartTime', '09:00')
            ->set('rangeEndTime', '10:00')
            ->call('addTimeRange');

        $wed = $this->nextDayOfWeek(3);
        $patient = User::factory()->create(['role' => UserRole::Patient]);

        Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $user->id,
            'type' => 'consultation',
            'status' => 'pending',
            'appointment_date' => $wed,
            'start_time' => '09:15',
            'end_time' => '09:30',
            'fee_amount' => 0,
        ]);

        $slots = $profile->getAvailableSlotsForDate($wed);

        $this->assertNotContains('09:15', $slots, 'Booked slot must not be offered again.');
        $this->assertSame(['09:00', '09:30', '09:45'], $slots);
    }
}
