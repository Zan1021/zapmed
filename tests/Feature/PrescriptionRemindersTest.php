<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Prescription;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Subscriptions\PrescriptionRepeats;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The daily "time to refill" reminder: only prescriptions that are due, once a week at most, never for an automatic repeat order. */
class PrescriptionRemindersTest extends TestCase
{
    use RefreshDatabase;

    private User $patient;
    private int $sent = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 09:00', 'Africa/Johannesburg'));
        $this->patient = User::factory()->create(['role' => UserRole::Patient, 'phone' => '+27821234567']);

        $this->sent = 0;
        $this->mock(NotificationDispatcher::class, fn ($m) => $m->shouldReceive('sendVia')->andReturnUsing(function () {
            $this->sent++;

            return new \App\Models\NotificationLog();
        }));
    }

    private function rx(array $a = []): Prescription
    {
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $appt = Appointment::create(['patient_id' => $this->patient->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'completed', 'appointment_date' => '2026-10-01', 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 1]);
        $c = Consultation::create(['appointment_id' => $appt->id, 'patient_id' => $this->patient->id, 'doctor_id' => $doctor->id, 'status' => 'completed']);

        return Prescription::create($a + [
            'consultation_id' => $c->id, 'patient_id' => $this->patient->id, 'doctor_id' => $doctor->id, 'status' => 'dispensed', 'total_amount' => 1,
            'is_chronic' => true, 'repeats' => 3, 'repeats_used' => 0, 'dispatched_at' => now()->subDays(27), 'signed_at' => now()->subDays(27),
        ]);
    }

    private function remind(): void
    {
        $this->artisan('prescriptions:remind')->assertSuccessful();
    }

    public function test_a_chronic_prescription_that_is_due_gets_a_text_and_an_email(): void
    {
        $this->rx();

        $this->remind();

        $this->assertSame(2, $this->sent);
    }

    public function test_a_prescription_that_is_not_due_is_left_alone_even_with_an_expiry_date_in_the_future(): void
    {
        // This is the old bug: the misplaced "or" matched every prescription that had a future expiry date.
        $this->rx(['is_chronic' => false, 'valid_until' => now()->addMonths(3)]);
        $this->rx(['dispatched_at' => now()->subDays(2), 'valid_until' => now()->addMonths(3)]);
        $this->rx(['status' => 'signed', 'valid_until' => now()->addMonths(3)]);
        $this->rx(['repeats_used' => 3, 'valid_until' => now()->addMonths(3)]);

        $this->remind();

        $this->assertSame(0, $this->sent);
    }

    public function test_an_expired_prescription_is_left_alone(): void
    {
        $this->rx(['valid_until' => now()->subDay()]);

        $this->remind();

        $this->assertSame(0, $this->sent);
    }

    public function test_one_reminder_a_week_at_most(): void
    {
        $rx = $this->rx();

        $this->remind();
        $this->remind();
        $this->assertSame(2, $this->sent, 'The same day twice sends nothing more.');

        $this->travel(3)->days();
        $rx->update(['dispatched_at' => now()->subDays(26)]);
        $this->remind();
        $this->assertSame(2, $this->sent, 'Three days later is still inside the week.');

        $this->travel(5)->days();
        $rx->update(['dispatched_at' => now()->subDays(27)]);
        $this->remind();
        $this->assertSame(4, $this->sent, 'A week later it may remind again.');
    }

    public function test_a_prescription_on_an_automatic_repeat_order_is_skipped(): void
    {
        $rx = $this->rx();
        Subscription::create([
            'user_id' => $this->patient->id, 'subscription_plan_id' => app(PrescriptionRepeats::class)->plan()->id, 'status' => 'active',
            'charge_mode' => 'adhoc', 'metadata' => ['prescription_id' => $rx->id],
        ]);

        $this->remind();

        $this->assertSame(0, $this->sent);
    }

    public function test_a_refill_copy_is_never_reminded(): void
    {
        $original = $this->rx(['repeats' => 3]);
        $this->rx(['refill_of_id' => $original->id, 'refill_no' => 1]);
        $original->update(['dispatched_at' => now()->subDays(2)]);   // the original is not due, only the copy would match

        $this->remind();

        $this->assertSame(0, $this->sent);
    }
}