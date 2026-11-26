<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Appointment times are South African local time but the server clock is UTC (SA is 2 hours ahead).
 * The reminder command must compare them against the local clock, or the "1 hour before" SMS
 * arrives an hour AFTER the consultation has started.
 */
class AppointmentRemindersClockTest extends TestCase
{
    use RefreshDatabase;

    private function appointment(string $startLocal): Appointment
    {
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $patient = User::factory()->create(['role' => UserRole::Patient]);

        return Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'weight-loss',
            'status' => 'confirmed', 'is_paid' => true, 'appointment_date' => '2026-12-01',
            'start_time' => $startLocal, 'end_time' => '10:45', 'fee_amount' => 70000,
        ]);
    }

    public function test_one_hour_reminder_goes_out_an_hour_before_the_local_start(): void
    {
        // 07:15 UTC = 09:15 in South Africa; the consultation starts at 10:00 local
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-12-01 07:15:00', 'UTC'));
        $a = $this->appointment('10:00');

        $this->artisan('appointments:send-reminders')->assertSuccessful();

        $this->assertNotNull($a->fresh()->reminder_1h_sent_at, 'the 1-hour reminder was due at 09:00 local');
        $this->assertNull($a->fresh()->reminder_15m_sent_at, 'not yet within 15 minutes');
    }

    public function test_no_reminder_goes_out_while_the_consultation_is_still_hours_away_locally(): void
    {
        // 05:00 UTC = 07:00 local; a 10:00 consultation is three hours away
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-12-01 05:00:00', 'UTC'));
        $a = $this->appointment('10:00');

        $this->artisan('appointments:send-reminders')->assertSuccessful();

        $this->assertNull($a->fresh()->reminder_1h_sent_at);
        $this->assertNull($a->fresh()->reminder_15m_sent_at);
    }

    public function test_fifteen_minute_reminder_goes_out_fifteen_minutes_before_the_local_start(): void
    {
        // 07:50 UTC = 09:50 local, ten minutes before a 10:00 start
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-12-01 07:50:00', 'UTC'));
        $a = $this->appointment('10:00');

        $this->artisan('appointments:send-reminders')->assertSuccessful();

        $this->assertNotNull($a->fresh()->reminder_15m_sent_at);
    }
}
