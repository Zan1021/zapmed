<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Charge-at-booking: a slot held for payment is freed when the hold runs out and nothing was paid.
 */
class ReleaseExpiredReservationsTest extends TestCase
{
    use RefreshDatabase;

    private function appointment(array $attrs): Appointment
    {
        static $n = 0;
        $n++;
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $patient = User::factory()->create(['role' => UserRole::Patient]);

        return Appointment::create(array_merge([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'general',
            'status' => 'pending_payment', 'is_paid' => false,
            'appointment_date' => now()->addDays(2)->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45',
            'fee_amount' => 70000, 'reserved_until' => now()->subMinute(),
        ], $attrs));
    }

    public function test_an_unpaid_booking_whose_hold_ran_out_is_expired_and_the_hold_cleared(): void
    {
        $a = $this->appointment([]);

        $this->artisan('onboarding:release-expired-reservations')->expectsOutputToContain('Released 1')->assertSuccessful();

        $a->refresh();
        $this->assertSame('expired', $a->status);
        $this->assertNull($a->reserved_until);
    }

    public function test_holds_that_are_still_running_paid_or_confirmed_bookings_are_never_touched(): void
    {
        $running = $this->appointment(['reserved_until' => now()->addMinutes(5)]);
        $paid = $this->appointment(['is_paid' => true]);
        $confirmed = $this->appointment(['status' => 'confirmed', 'is_paid' => true, 'reserved_until' => null]);
        $noHold = $this->appointment(['reserved_until' => null]);

        $this->artisan('onboarding:release-expired-reservations')->expectsOutputToContain('Released 0')->assertSuccessful();

        $this->assertSame('pending_payment', $running->fresh()->status);
        $this->assertSame('pending_payment', $paid->fresh()->status);
        $this->assertSame('confirmed', $confirmed->fresh()->status);
        $this->assertSame('pending_payment', $noHold->fresh()->status);
    }

    public function test_only_the_expired_one_is_released_when_several_exist(): void
    {
        $old = $this->appointment(['reserved_until' => now()->subHour()]);
        $fresh = $this->appointment(['reserved_until' => now()->addMinutes(10)]);

        $this->artisan('onboarding:release-expired-reservations')->assertSuccessful();

        $this->assertSame('expired', $old->fresh()->status);
        $this->assertSame('pending_payment', $fresh->fresh()->status);
    }

    public function test_the_job_runs_every_five_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'onboarding:release-expired-reservations'));

        $this->assertNotNull($event, 'The job is not scheduled');
        $this->assertSame('*/5 * * * *', $event->expression);
    }
}
