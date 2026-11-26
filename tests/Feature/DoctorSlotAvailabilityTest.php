<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\DoctorAvailability;
use App\Models\DoctorBlockedDate;
use App\Models\DoctorProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The times patients are offered must match what the doctor really has free:
 * their own hours, minus blocked days, minus anything already booked (a 45-minute
 * consultation holds three 15-minute slots). A booking that was abandoned (expired)
 * or cancelled must give its time back.
 */
class DoctorSlotAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->date = Carbon::today()->addDays(3)->format('Y-m-d');
    }

    /** Doctor with 15-minute slots from $from to $to (e.g. 09:00-10:00 = 4 slots) on the weekday of $this->date. */
    private function doctor(string $from = '09:00', string $to = '10:00'): DoctorProfile
    {
        $user = User::factory()->create(['role' => UserRole::Doctor]);
        $profile = DoctorProfile::create([
            'user_id' => $user->id, 'hpcsa_number' => 'MP' . random_int(100000, 999999), 'speciality' => 'General Practitioner',
            'qualification' => 'MBChB', 'doctor_type' => 'locum', 'is_verified' => true, 'accepts_new_patients' => true,
            'consultation_fee' => 45000, 'consultation_duration' => 30,
        ]);
        for ($t = strtotime($from); $t < strtotime($to); $t += 900) {
            DoctorAvailability::create([
                'doctor_profile_id' => $profile->id, 'day_of_week' => (int) Carbon::parse($this->date)->dayOfWeek,
                'start_time' => date('H:i', $t), 'end_time' => date('H:i', $t + 900), 'is_active' => true,
            ]);
        }

        return $profile;
    }

    private function book(DoctorProfile $p, string $status, string $start, string $end): Appointment
    {
        $patient = User::factory()->create(['role' => UserRole::Patient]);

        return Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $p->user_id, 'type' => 'weight-loss', 'status' => $status,
            'appointment_date' => $this->date, 'start_time' => $start, 'end_time' => $end, 'fee_amount' => 70000, 'is_paid' => false,
        ]);
    }

    public function test_offers_exactly_the_doctors_own_hours(): void
    {
        $p = $this->doctor('09:00', '10:00');

        $this->assertSame(['09:00', '09:15', '09:30', '09:45'], $p->getAvailableSlotsForDate($this->date));
        $this->assertSame([], $p->getAvailableSlotsForDate(Carbon::parse($this->date)->addDay()->format('Y-m-d')), 'a day the doctor does not work');
    }

    public function test_switched_off_slots_are_not_offered(): void
    {
        $p = $this->doctor('09:00', '10:00');
        DoctorAvailability::where('doctor_profile_id', $p->id)->where('start_time', '09:15')->update(['is_active' => false]);

        $this->assertSame(['09:00', '09:30', '09:45'], $p->getAvailableSlotsForDate($this->date));
    }

    public function test_a_blocked_day_offers_nothing(): void
    {
        $p = $this->doctor();
        DoctorBlockedDate::create(['doctor_profile_id' => $p->id, 'blocked_date' => $this->date, 'reason' => 'Leave']);

        $this->assertSame([], $p->getAvailableSlotsForDate($this->date));
        $this->assertSame([], $p->availableStartTimes($this->date, 45));
    }

    public function test_a_45_minute_consultation_needs_three_free_slots_in_a_row(): void
    {
        $p = $this->doctor('09:00', '10:00');

        $this->assertSame(['09:00', '09:15'], $p->availableStartTimes($this->date, 45));

        // 09:15 taken: neither 09:00 (needs 09:15) nor 09:15 itself can start a 45-minute consultation
        $this->book($p, 'confirmed', '09:15', '09:30');
        $this->assertSame([], $p->availableStartTimes($this->date, 45));
    }

    public function test_a_longer_booking_blocks_every_slot_it_covers_for_the_doctor_lookup_too(): void
    {
        $p = $this->doctor('09:00', '10:00');
        $this->book($p, 'confirmed', '09:00', '09:45');

        // 09:15 lies inside the 09:00-09:45 booking: the doctor must not be offered for it
        $this->assertSame([], DoctorProfile::getAvailableDoctorsForSlot($this->date, '09:15')->pluck('id')->all());
        $this->assertSame([], DoctorProfile::getAvailableDoctorsForSlot($this->date, '09:00')->pluck('id')->all());
        $this->assertSame([$p->id], DoctorProfile::getAvailableDoctorsForSlot($this->date, '09:45')->pluck('id')->all());
    }

    public function test_a_held_unpaid_booking_blocks_the_slot(): void
    {
        $p = $this->doctor('09:00', '09:30');
        $this->book($p, 'pending_payment', '09:00', '09:15');

        $this->assertSame(['09:15'], $p->getAvailableSlotsForDate($this->date));
    }

    public function test_cancelled_and_expired_bookings_give_the_slot_back(): void
    {
        $p = $this->doctor('09:00', '09:30');
        $this->book($p, 'cancelled', '09:00', '09:15');
        $this->book($p, 'expired', '09:15', '09:30');

        $this->assertSame(['09:00', '09:15'], $p->getAvailableSlotsForDate($this->date));
        $this->assertSame([$p->id], DoctorProfile::getAvailableDoctorsForSlot($this->date, '09:15')->pluck('id')->all());
    }

    public function test_unverified_or_closed_doctors_are_never_offered(): void
    {
        $p = $this->doctor();
        $p->update(['accepts_new_patients' => false]);
        $this->assertSame([], DoctorProfile::getAvailableDoctorsForSlot($this->date, '09:00')->pluck('id')->all());

        $p->update(['accepts_new_patients' => true, 'is_verified' => false]);
        $this->assertSame([], DoctorProfile::getAvailableDoctorsForSlot($this->date, '09:00')->pluck('id')->all());
    }
}
