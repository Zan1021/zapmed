<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Patient\BookingJourney;
use App\Models\Appointment;
use App\Models\AssessmentCategory;
use App\Models\DoctorAvailability;
use App\Models\DoctorProfile;
use App\Models\Payment;
use App\Models\User;
use App\Services\Assessments\QuestionBankService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Charge-at-booking onboarding journey (spec: .kiro/specs/charge-at-booking).
 * Drives the component Assessment -> Review -> Your details (OTP) -> Book -> Pay and asserts the
 * Appointment + Payment are created correctly (fee bound SERVER-SIDE, status pending_payment), then
 * simulates a PayFast COMPLETE ITN and asserts the booking is confirmed + paid.
 */
class OnboardingJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('onboarding.enabled_slugs', ['weight-loss']);
        config()->set('payfast.test_mode', true);

        // Capture the OTP via the SMS service instead of reading it from (now removed) public page state.
        $this->smsFake = new class extends \App\Services\SmsService {
            public ?string $lastCode = null;
            public function __construct() {}
            public function sendOtp(string $phoneNumber, string $code): bool { $this->lastCode = $code; return true; }
        };
        $this->app->instance(\App\Services\SmsService::class, $this->smsFake);
    }

    private ?\App\Services\SmsService $smsFake = null;

    private function captureOtp(): string
    {
        return (string) $this->smsFake->lastCode;
    }

    /** A live weight-loss category with two simple questions. */
    private function liveWeightLossCategory(): void
    {
        $bank = app(QuestionBankService::class);
        $cat = $bank->createCategory('weight-loss', 'Weight Loss');
        $q1 = $bank->createQuestion(['scope' => 'category', 'question_key' => 'WL_TAKEN'],
            ['text' => 'Have you ever taken weight loss medications?', 'type' => 'single',
             'options' => [['code' => 'yes', 'label' => 'Yes'], ['code' => 'no', 'label' => 'No']], 'required' => true], null);
        $q2 = $bank->createQuestion(['scope' => 'category', 'question_key' => 'WL_WEIGHT'],
            ['text' => 'Current weight? (kg)', 'type' => 'number', 'required' => true], null);
        foreach ([$q1, $q2] as $q) {
            $bank->addQuestionToCategory($cat->refresh(), $q, null);
            $bank->publish($q, $q->versions()->latest('version')->first(), 'test', null);
        }
        $bank->publishCategory($cat->refresh(), null);
    }

    private function bookableDoctor(string $date, string $time): User
    {
        $user = User::factory()->create(['role' => UserRole::Doctor, 'first_name' => 'Sarah', 'last_name' => 'Naidoo']);
        $profile = DoctorProfile::create([
            'user_id' => $user->id,
            'hpcsa_number' => 'MP123456',
            'speciality' => 'General Practice',
            'qualification' => 'MBChB',
            'doctor_type' => 'locum',
            'is_verified' => true,
            'is_accepting_patients' => true,
            'consultation_fee' => 45000,
            'consultation_duration' => 30,
        ]);
        DoctorAvailability::create([
            'doctor_profile_id' => $profile->id,
            'day_of_week' => (int) Carbon::parse($date)->dayOfWeek,
            'start_time' => $time,
            'end_time' => Carbon::parse($time)->addHour()->format('H:i'),
            'is_active' => true,
        ]);

        return $user;
    }

    public function test_full_journey_creates_pending_payment_then_itn_confirms(): void
    {
        $this->liveWeightLossCategory();
        $date = Carbon::today()->addDays(2)->format('Y-m-d');
        $time = '09:00';
        $this->bookableDoctor($date, $time);

        $c = Livewire::test(BookingJourney::class, ['slug' => 'weight-loss']);

        // fee disclosed, starts on assessment
        $c->assertSet('stage', 'assessment')->assertSet('feeCents', 45000);

        // answer the two questions
        $c->set('answers.WL_TAKEN', 'yes')->call('nextQuestion')
          ->set('answers.WL_WEIGHT', '90')->call('nextQuestion')
          ->assertSet('stage', 'review');

        // review -> details
        $c->call('toDetails')->assertSet('stage', 'details');

        // details + OTP (the code is never exposed in page state; capture it via the SMS fake)
        $c->set('firstName', 'Thabo')->set('lastName', 'Mokoena')
          ->set('email', 'thabo@example.com')->set('cell', '0820000000')
          ->set('consentCare', true)->call('sendOtp')->assertSet('otpSent', true);
        $code = $this->captureOtp();
        $c->set('otp', $code)->call('verifyOtp')
          ->assertSet('verified', true)->assertSet('stage', 'book');

        // a user + assessment now exist
        $this->assertDatabaseHas('users', ['email' => 'thabo@example.com', 'role' => UserRole::Patient->value]);
        $this->assertNotNull($c->get('assessmentId'));

        // book: pick slot (doctor auto-resolves), continue to pay
        $c->set('selectedDate', $date)->call('selectTime', $time)->set('mode', 'video')
          ->call('toPay')->assertSet('stage', 'pay');

        // confirm & pay -> redirect to payment.checkout; Appointment + Payment created, UNPAID
        $c->call('confirmAndPay')->assertRedirect();

        $appt = Appointment::first();
        $this->assertNotNull($appt);
        $this->assertFalse((bool) $appt->is_paid);
        $this->assertSame('pending_payment', $appt->status);
        $this->assertSame(45000, (int) $appt->fee_amount); // fee bound server-side

        $payment = Payment::first();
        $this->assertSame('pending', $payment->status);
        $this->assertSame(45000, (int) $payment->amount);
        $this->assertSame($appt->id, $payment->appointment_id);

        // simulate a PayFast COMPLETE ITN (test_mode skips IP/server checks; signature still required)
        $itn = [
            'm_payment_id' => $payment->reference,
            'pf_payment_id' => '111222',
            'payment_status' => 'COMPLETE',
            'amount_gross' => '450.00',
        ];
        $itn['signature'] = app(\App\Services\PayFastService::class)->generateSignature($itn);
        $this->post(route('payment.notify'), $itn)->assertOk();

        $appt->refresh();
        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertTrue((bool) $appt->is_paid);
        $this->assertSame('confirmed', $appt->status);
    }

    public function test_client_cannot_tamper_the_fee(): void
    {
        $this->liveWeightLossCategory();
        $date = Carbon::today()->addDays(2)->format('Y-m-d');
        $this->bookableDoctor($date, '09:00');

        $c = Livewire::test(BookingJourney::class, ['slug' => 'weight-loss']);
        $c->set('answers.WL_TAKEN', 'no')->call('nextQuestion')
          ->set('answers.WL_WEIGHT', '80')->call('nextQuestion')->call('toDetails')
          ->set('firstName', 'A')->set('lastName', 'B')->set('email', 'a@b.com')
          ->set('cell', '0820000001')->set('consentCare', true)->call('sendOtp');
        $c->set('otp', $this->captureOtp())->call('verifyOtp');

        // tamper the client-side fee
        $c->set('feeCents', 1)
          ->set('selectedDate', $date)->call('selectTime', '09:00')->set('mode', 'video')
          ->call('toPay')->call('confirmAndPay');

        // server bound the REAL doctor fee, ignoring the tampered value
        $this->assertSame(45000, (int) Payment::first()->amount);
    }

    public function test_disabled_slug_is_404(): void
    {
        $this->get('/start/acne-treatment')->assertNotFound();
    }
}
