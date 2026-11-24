<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Patient\FollowUp;
use App\Models\Appointment;
use App\Models\DoctorAvailability;
use App\Models\DoctorProfile;
use App\Models\FollowUpRequest;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\PatientProfile;
use App\Models\Payment;
use App\Models\User;
use App\Services\Commerce\LiveOrderBridge;
use App\Services\Doctor\FollowUpService;
use App\Services\Patient\PatientFollowUps;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 3.3 Follow-Up tab (Craig's guide 3.1): book one of your own doctors for 15 or 30 minutes; a follow-up the doctor
 * asked for and marked free costs nothing, everything else is paid by card and held for a short time.
 */
class PatientFollowUpTabTest extends TestCase
{
    use RefreshDatabase;

    private User $patient;
    private User $doctor;
    private string $tomorrow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));   // Wednesday
        $this->tomorrow = '2026-11-26';

        $this->patient = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $this->patient->id, 'onboarding_complete' => true]);
        $this->doctor = $this->doctor();
        $this->seen($this->doctor);
    }

    private function doctor(int $fee = 40000, bool $verified = true): User
    {
        $user = User::factory()->create(['role' => UserRole::Doctor, 'first_name' => 'Gerhard', 'last_name' => 'Coetzer']);
        $profile = DoctorProfile::create([
            'user_id' => $user->id, 'hpcsa_number' => 'MP'.$user->id, 'speciality' => 'General Practice', 'qualification' => 'MBChB',
            'doctor_type' => 'locum', 'is_verified' => $verified, 'is_accepting_patients' => true,
            'consultation_fee' => 70000, 'followup_fee' => $fee, 'consultation_duration' => 30,
        ]);
        foreach (range(0, 15) as $i) {   // 09:00 to 13:00 in 15 minute slots, every day of the week
            foreach (range(0, 6) as $dow) {
                DoctorAvailability::create([
                    'doctor_profile_id' => $profile->id, 'day_of_week' => $dow,
                    'start_time' => Carbon::parse('09:00')->addMinutes($i * 15)->format('H:i'),
                    'end_time' => Carbon::parse('09:00')->addMinutes(($i + 1) * 15)->format('H:i'), 'is_active' => true,
                ]);
            }
        }

        return $user;
    }

    private function seen(User $doctor): void
    {
        Appointment::create([
            'patient_id' => $this->patient->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'completed',
            'appointment_date' => '2026-11-10', 'start_time' => '09:00', 'end_time' => '09:30', 'fee_amount' => 70000,
        ]);
    }

    private function svc(): PatientFollowUps
    {
        return app(PatientFollowUps::class);
    }

    private function openRequest(int $days = 14): FollowUpRequest
    {
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);

        return app(FollowUpService::class)->request($this->doctor, $order, $days);
    }

    public function test_only_doctors_the_patient_has_seen_can_be_chosen(): void
    {
        $stranger = $this->doctor();
        $unverified = $this->doctor(40000, false);
        $this->seen($unverified);

        $this->assertSame([$this->doctor->id], $this->svc()->doctors($this->patient)->pluck('id')->all());
        $this->assertSame([], $this->svc()->slots($this->patient, $stranger->id, $this->tomorrow, 30));
    }

    public function test_slots_respect_the_length_the_window_and_today(): void
    {
        $this->assertContains('09:00', $this->svc()->slots($this->patient, $this->doctor->id, $this->tomorrow, 15));
        $this->assertContains('12:30', $this->svc()->slots($this->patient, $this->doctor->id, $this->tomorrow, 30));
        $this->assertNotContains('12:45', $this->svc()->slots($this->patient, $this->doctor->id, $this->tomorrow, 30), 'A 30 minute slot needs two free quarters.');
        $this->assertContains('12:45', $this->svc()->slots($this->patient, $this->doctor->id, $this->tomorrow, 15));

        $this->assertSame([], $this->svc()->slots($this->patient, $this->doctor->id, $this->tomorrow, 45), 'Only 15 or 30.');
        $this->assertSame([], $this->svc()->slots($this->patient, $this->doctor->id, '2026-11-24', 15), 'Not in the past.');
        $this->assertSame([], $this->svc()->slots($this->patient, $this->doctor->id, '2026-12-20', 15), 'Beyond 14 days.');
        $today = $this->svc()->slots($this->patient, $this->doctor->id, '2026-11-25', 15);
        $this->assertNotContains('11:00', $today, 'Today: nothing at or before the current time (11:00).');
        $this->assertNotContains('09:00', $today);
        $this->assertContains('11:15', $today);

        $this->expectException(ValidationException::class);
        $this->svc()->slots($this->patient, $this->doctor->id, '2026-02-31', 15);
    }

    public function test_a_booked_slot_is_taken(): void
    {
        $this->svc()->book($this->patient, $this->doctor->id, $this->tomorrow, '09:00', 30, 'follow_up');

        $other = User::factory()->create(['role' => UserRole::Patient]);
        Appointment::create(['patient_id' => $other->id, 'doctor_id' => $this->doctor->id, 'type' => 'general', 'status' => 'completed', 'appointment_date' => '2026-11-01', 'start_time' => '09:00', 'end_time' => '09:30', 'fee_amount' => 1]);

        $slots = $this->svc()->slots($this->patient, $this->doctor->id, $this->tomorrow, 15);
        $this->assertNotContains('09:00', $slots);
        $this->assertNotContains('09:15', $slots);
        $this->assertContains('09:30', $slots);
    }

    public function test_a_paid_follow_up_is_held_and_sent_to_the_card_page(): void
    {
        $result = $this->svc()->book($this->patient, $this->doctor->id, $this->tomorrow, '10:00', 30, 'follow_up');

        $appt = $result['appointment'];
        $this->assertSame('pending_payment', $appt->status);
        $this->assertSame(40000, $appt->fee_amount);
        $this->assertFalse($appt->is_paid);
        $this->assertSame('10:30', substr((string) $appt->end_time, 0, 5));
        $this->assertNotNull($appt->reserved_until);
        $this->assertSame(40000, (int) $result['payment']->amount);
        $this->assertSame('pending', $result['payment']->status);
        $this->assertSame($this->patient->id, $result['payment']->patient_id);
        $this->assertSame(0, Order::where('patient_id', $this->patient->id)->count(), 'A follow-up is not an order.');
    }

    public function test_fifteen_minutes_costs_half_by_default(): void
    {
        $result = $this->svc()->book($this->patient, $this->doctor->id, $this->tomorrow, '09:00', 15, 'authorisation_check_in');

        $this->assertSame(20000, $result['appointment']->fee_amount);
        $this->assertSame('authorisation_check_in', $result['appointment']->type);
        $this->assertSame('Authorisation check-in', $result['appointment']->type_label);
    }

    public function test_a_free_follow_up_the_doctor_asked_for_is_confirmed_straight_away(): void
    {
        $request = $this->openRequest();
        $this->assertTrue($request->is_free);

        $result = $this->svc()->book($this->patient, $this->doctor->id, $this->tomorrow, '10:00', 30, 'follow_up', $request->id);

        $this->assertNull($result['payment']);
        $this->assertSame('confirmed', $result['appointment']->status);
        $this->assertTrue($result['appointment']->is_paid);
        $this->assertSame(0, $result['appointment']->fee_amount);
        $this->assertSame(FollowUpRequest::BOOKED, $request->fresh()->status);
        $this->assertSame($result['appointment']->id, $request->fresh()->appointment_id);
        $this->assertSame('PendingFollowUpProfileBook', $request->order->fresh()->status);
    }

    public function test_a_later_follow_up_is_paid_and_only_counts_as_booked_once_the_card_payment_is_in(): void
    {
        $first = $this->openRequest();
        $this->svc()->book($this->patient, $this->doctor->id, $this->tomorrow, '09:00', 30, 'follow_up', $first->id);
        app(FollowUpService::class)->complete($this->doctor, $first->fresh());

        $order = $first->order->fresh();
        $order->update(['status' => 'Completed']);
        $second = app(FollowUpService::class)->request($this->doctor, $order, 30);
        $this->assertFalse($second->is_free);

        $result = $this->svc()->book($this->patient, $this->doctor->id, '2026-11-27', '09:00', 30, 'follow_up', $second->id);
        $this->assertNotNull($result['payment']);
        $this->assertSame(FollowUpRequest::REQUESTED, $second->fresh()->status, 'Not booked until paid.');
        $this->assertSame('PendingFollowUpRequest', $order->fresh()->status);

        $result['payment']->update(['status' => 'completed']);
        $this->svc()->onPaymentCompleted($result['payment']->fresh());

        $this->assertSame(FollowUpRequest::BOOKED, $second->fresh()->status);
        $this->assertSame('PendingFollowUpProfileBook', $order->fresh()->status);
    }

    public function test_paying_a_follow_up_creates_no_stray_order_and_still_records_the_cash(): void
    {
        $result = $this->svc()->book($this->patient, $this->doctor->id, $this->tomorrow, '09:00', 30, 'follow_up');
        $result['payment']->update(['status' => 'completed']);

        app(LiveOrderBridge::class)->onPaymentCompleted($result['payment']->fresh());

        $this->assertSame(0, Order::where('patient_id', $this->patient->id)->count());
        $this->assertDatabaseHas('finance_revenue_entries', ['payment_id' => $result['payment']->id]);
    }

    public function test_paying_for_a_requested_follow_up_attaches_to_that_order_without_a_new_one(): void
    {
        $request = $this->openRequest();
        $request->update(['is_free' => false]);   // a paid one
        $result = $this->svc()->book($this->patient, $this->doctor->id, $this->tomorrow, '09:00', 30, 'follow_up', $request->id);
        $result['payment']->update(['status' => 'completed']);

        $before = Order::count();
        app(LiveOrderBridge::class)->onPaymentCompleted($result['payment']->fresh());

        $this->assertSame($before, Order::count());
    }

    public function test_an_ordinary_consultation_payment_still_makes_its_order(): void
    {
        $appt = Appointment::create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'type' => 'general', 'status' => 'pending_payment', 'appointment_date' => $this->tomorrow, 'start_time' => '11:00', 'end_time' => '11:30', 'fee_amount' => 70000]);
        $payment = Payment::create(['patient_id' => $this->patient->id, 'appointment_id' => $appt->id, 'amount' => 70000, 'status' => 'completed']);

        app(LiveOrderBridge::class)->onPaymentCompleted($payment);

        $this->assertSame(1, Order::where('patient_id', $this->patient->id)->count(), 'Unchanged behaviour for normal consultations.');
    }

    public function test_bad_bookings_are_refused(): void
    {
        $stranger = $this->doctor();
        $cases = [
            'type' => fn () => $this->svc()->book($this->patient, $this->doctor->id, $this->tomorrow, '09:00', 30, 'surgery'),
            'minutes' => fn () => $this->svc()->book($this->patient, $this->doctor->id, $this->tomorrow, '09:00', 20, 'follow_up'),
            'doctor' => fn () => $this->svc()->book($this->patient, $stranger->id, $this->tomorrow, '09:00', 30, 'follow_up'),
            'time' => fn () => $this->svc()->book($this->patient, $this->doctor->id, $this->tomorrow, '03:00', 30, 'follow_up'),
            'time ' => fn () => $this->svc()->book($this->patient, $this->doctor->id, '2026-11-24', '09:00', 30, 'follow_up'),
        ];
        foreach ($cases as $field => $try) {
            try {
                $try();
                $this->fail("Should be refused: $field");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(trim($field), $e->errors());
            }
        }
        $this->assertSame(0, Appointment::where('type', 'follow_up')->count());
    }

    public function test_a_patient_cannot_use_someone_elses_follow_up_request(): void
    {
        $request = $this->openRequest();
        $other = User::factory()->create(['role' => UserRole::Patient]);
        Appointment::create(['patient_id' => $other->id, 'doctor_id' => $this->doctor->id, 'type' => 'general', 'status' => 'completed', 'appointment_date' => '2026-11-01', 'start_time' => '09:00', 'end_time' => '09:30', 'fee_amount' => 1]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->svc()->book($other, $this->doctor->id, $this->tomorrow, '09:00', 30, 'follow_up', $request->id);
    }

    public function test_only_patients_can_book(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->svc()->book($this->doctor, $this->doctor->id, $this->tomorrow, '09:00', 30, 'follow_up');
    }

    public function test_a_free_doctor_fee_means_a_free_booking(): void
    {
        $free = $this->doctor(0);
        $this->seen($free);

        $result = $this->svc()->book($this->patient, $free->id, $this->tomorrow, '09:00', 30, 'follow_up');

        $this->assertNull($result['payment']);
        $this->assertSame('confirmed', $result['appointment']->status);
    }

    public function test_the_screen_books_a_paid_follow_up_and_goes_to_the_card_page(): void
    {
        $component = Livewire::actingAs($this->patient)->test(FollowUp::class)
            ->set('doctorId', $this->doctor->id)->set('date', $this->tomorrow)->set('minutes', 30)
            ->assertSeeHtml('data-slot="10:00"')->assertSee('R400.00')
            ->call('book')->assertHasErrors('time')
            ->set('time', '10:00')->call('book');

        $payment = Payment::where('patient_id', $this->patient->id)->firstOrFail();
        $component->assertRedirect(route('payment.checkout', ['reference' => $payment->reference]));
        $this->assertSame('pending_payment', $payment->appointment->status);
    }

    public function test_the_screen_books_a_free_requested_follow_up(): void
    {
        $request = $this->openRequest();

        Livewire::actingAs($this->patient)->test(FollowUp::class)
            ->assertSee('Your doctor asked for a follow-up')->assertSet('requestId', $request->id)->assertSet('doctorId', $this->doctor->id)
            ->set('date', $this->tomorrow)->assertSee('No charge')
            ->set('time', '09:00')->call('book')->assertHasNoErrors()
            ->assertSee('Your appointment is booked')->assertSee('Booked');

        $this->assertSame(FollowUpRequest::BOOKED, $request->fresh()->status);
    }

    public function test_changing_the_doctor_day_or_length_clears_the_chosen_time(): void
    {
        Livewire::actingAs($this->patient)->test(FollowUp::class)
            ->set('doctorId', $this->doctor->id)->set('time', '10:00')
            ->set('minutes', 15)->assertSet('time', '')
            ->set('time', '10:00')->set('date', '2026-11-27')->assertSet('time', '');
    }

    public function test_the_page_is_only_for_patients_and_a_new_patient_sees_the_hint(): void
    {
        $this->actingAs($this->patient)->get(route('patient.follow-up'))->assertOk();
        foreach ([UserRole::Doctor, UserRole::Admin, UserRole::HealthCoach] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('patient.follow-up'))->assertForbidden();
        }

        $newbie = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $newbie->id, 'onboarding_complete' => true]);
        Livewire::actingAs($newbie)->test(FollowUp::class)->assertSeeHtml('data-empty="doctors"');
    }

    public function test_no_messages_are_sent_by_booking(): void
    {
        $this->svc()->book($this->patient, $this->doctor->id, $this->tomorrow, '09:00', 30, 'follow_up');

        $this->assertSame(0, NotificationLog::count());
    }
}
