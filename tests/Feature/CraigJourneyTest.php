<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Patient\BookingJourney;
use App\Models\ConsentRecord;
use App\Models\DoctorAvailability;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Craig's final weight-loss journey (26 Nov handoff): 14 screens, follow-ups on the parent's screen,
 * auto-advance on a single choice, medicines skip, measurements, consent log, safe account handling.
 */
class CraigJourneyTest extends TestCase
{
    use RefreshDatabase;

    private ?\App\Services\SmsService $sms = null;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('onboarding.enabled_slugs', ['weight-loss']);
        $this->artisan('questionbank:import-craig-weight-loss')->assertSuccessful();

        $this->sms = new class extends \App\Services\SmsService {
            public ?string $lastCode = null;
            public function __construct() {}
            public function sendOtp(string $phoneNumber, string $code): bool { $this->lastCode = $code; return true; }
        };
        $this->app->instance(\App\Services\SmsService::class, $this->sms);
    }

    private function doctor(string $date, string $time = '09:00'): User
    {
        $user = User::factory()->create(['role' => UserRole::Doctor, 'first_name' => 'Gerhard', 'last_name' => 'Coetzer']);
        $profile = DoctorProfile::create([
            'user_id' => $user->id, 'hpcsa_number' => 'MP1', 'speciality' => 'General Practice', 'qualification' => 'MBChB',
            'doctor_type' => 'locum', 'is_verified' => true, 'is_accepting_patients' => true,
            'consultation_fee' => 45000, 'consultation_duration' => 30,
        ]);
        foreach (range(0, 7) as $i) { // eight 15-minute slots, as the real availability table is built
            DoctorAvailability::create([
                'doctor_profile_id' => $profile->id, 'day_of_week' => (int) Carbon::parse($date)->dayOfWeek,
                'start_time' => Carbon::parse($time)->addMinutes($i * 15)->format('H:i'),
                'end_time' => Carbon::parse($time)->addMinutes(($i + 1) * 15)->format('H:i'), 'is_active' => true,
            ]);
        }
        return $user;
    }

    /** Answer every screen the quick way, landing on review. */
    private function answerEverything($c)
    {
        $c->call('selectAnswer', 'CWL_CONDITIONS', 'No');                       // 1 auto-advances
        $c->call('skipQuestion');                                                // 2 medicines skipped
        $c->call('selectAnswer', 'CWL_ALLERGIES', 'No');                         // 3
        $c->call('nextQuestion');                                                // 4 measurements (defaults)
        $c->call('selectAnswer', 'CWL_ALCOHOL', 'Never');                        // 5
        $c->call('selectAnswer', 'CWL_PAST_MEDICATION', 'No');                   // 6
        $c->set('answers.CWL_CHECKLIST', ['None of these'])->call('nextQuestion');           // 7
        $c->set('answers.CWL_HISTORY_PERSONAL', ['None of these'])->set('answers.CWL_HISTORY_FAMILY', ['None of these'])->call('nextQuestion'); // 8
        $c->call('selectAnswer', 'CWL_PREGNANCY', 'No');                         // 9
        $c->call('selectAnswer', 'CWL_ACTIVITY', 'Rarely or never');             // 10
        $c->set('answers.CWL_EATING', ['I usually eat regular meals.'])->call('nextQuestion'); // 11
        $c->call('nextQuestion');                                                // 12 wellbeing (optional)
        $c->call('selectAnswer', 'CWL_BLOOD_TESTS_12M', 'No');                   // 13
        return $c->call('nextQuestion');                                         // 14 final notes (optional)
    }

    public function test_it_is_14_screens_and_walks_to_review(): void
    {
        $c = Livewire::test(BookingJourney::class, ['slug' => 'weight-loss'])
            ->assertSee('Question 1 of 14')
            ->assertSee('Do you have any existing medical conditions');
        $this->assertSame(14, $c->instance()->progress['total']);

        $this->answerEverything($c)->assertSet('stage', 'review')->assertSee('Check your answers');
        $this->assertSame('85 kg, 170 cm', $c->get('answers')['CWL_MEASUREMENTS']);
        $this->assertSame('Skipped', $c->get('answers')['CWL_MEDICINES']);
    }

    public function test_the_next_screen_is_actually_rendered_after_a_single_choice(): void
    {
        // regression: a memoised "current screen" kept painting question 1 after the cursor had moved on
        Livewire::test(BookingJourney::class, ['slug' => 'weight-loss'])
            ->call('selectAnswer', 'CWL_CONDITIONS', 'No')
            ->assertSee('Question 2 of 14')
            ->assertSee('What medicines or supplements do you take now?')
            ->assertDontSee('Do you have any existing medical conditions');
    }

    public function test_a_45_minute_booking_blocks_the_slots_it_overlaps(): void
    {
        $date = Carbon::today()->addDays(2)->format('Y-m-d');
        $doctor = $this->doctor($date, '09:00');
        $patient = User::factory()->create();
        \App\Models\Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'weight-loss', 'status' => 'confirmed',
            'appointment_date' => $date, 'start_time' => '09:00', 'end_time' => '09:45', 'duration_minutes' => 45,
            'reason' => 'x', 'communication_preference' => 'video', 'fee_amount' => 70000, 'is_paid' => true,
        ]);
        $p = $doctor->doctorProfile;

        // 15-minute slots 09:00, 09:15 and 09:30 are all held by the 45-minute booking
        $this->assertSame(['09:45', '10:00', '10:15', '10:30', '10:45'], $p->getAvailableSlotsForDate($date));
        // a NEW 45-minute consultation needs three free slots in a row
        $this->assertSame(['09:45', '10:00', '10:15'], $p->availableStartTimes($date, 45));
    }

    public function test_a_yes_shows_the_follow_up_on_the_same_screen_and_needs_an_answer(): void
    {
        $c = Livewire::test(BookingJourney::class, ['slug' => 'weight-loss'])
            ->call('selectAnswer', 'CWL_CONDITIONS', 'Yes')
            ->assertSet('cursor', 0)                                  // did NOT auto-advance
            ->assertSee('Please tell us about any conditions or surgeries.')
            ->call('nextQuestion')->assertHasErrors('answer')         // follow-up is required
            ->set('answers.CWL_CONDITIONS_DETAIL', 'Hypertension')
            ->call('nextQuestion')->assertSet('cursor', 1);
    }

    public function test_none_of_these_cannot_be_combined(): void
    {
        $c = Livewire::test(BookingJourney::class, ['slug' => 'weight-loss']);
        $c->set('cursor', 6)
            ->set('answers.CWL_CHECKLIST', ['None of these', 'PCOS'])
            ->call('nextQuestion')->assertHasErrors('answer')->assertSet('cursor', 6);
    }

    public function test_medicines_need_an_entry_or_skip(): void
    {
        Livewire::test(BookingJourney::class, ['slug' => 'weight-loss'])
            ->call('selectAnswer', 'CWL_CONDITIONS', 'No')
            ->call('nextQuestion')->assertHasErrors('answer')
            ->set('answers.CWL_MEDICINES.0', 'Metformin 500mg')
            ->call('nextQuestion')->assertSet('cursor', 2);
    }

    public function test_an_existing_account_is_never_signed_in_from_the_public_form(): void
    {
        User::factory()->create(['email' => 'victim@example.com']);
        $c = Livewire::test(BookingJourney::class, ['slug' => 'weight-loss']);
        $this->answerEverything($c)
            ->set('reviewTruth', true)->set('reviewSideEffects', true)->call('toDetails')
            ->set('name', 'Mallory X')->set('email', 'victim@example.com')->set('cell', '82 000 0009')
            ->set('idNumber', '8001015009087')->set('password', 'CorrectHorse99')->set('passwordConfirm', 'CorrectHorse99')
            ->set('consentCare', true)->call('sendOtp')
            ->assertHasErrors('email')->assertSet('otpSent', false);
        $this->assertGuest();
    }

    public function test_consents_are_logged_with_their_exact_wording(): void
    {
        $date = Carbon::today()->addDays(2)->format('Y-m-d');
        $this->doctor($date);

        $c = Livewire::test(BookingJourney::class, ['slug' => 'weight-loss']);
        $this->answerEverything($c)
            ->set('reviewTruth', true)->set('reviewSideEffects', true)->call('toDetails')
            ->set('name', 'Thabo Mokoena')->set('email', 'thabo@example.com')->set('cell', '82 000 0000')
            ->set('idNumber', '8001015009087')->set('password', 'CorrectHorse99')->set('passwordConfirm', 'CorrectHorse99')
            ->set('consentCare', true)->set('consentMarketing', true)->call('sendOtp')->assertSet('otpSent', true);
        $c->set('otp', (string) $this->sms->lastCode)->call('verifyOtp')->assertSet('stage', 'book');

        $user = User::where('email', 'thabo@example.com')->firstOrFail();
        $this->assertSame('Thabo', $user->first_name);
        $this->assertSame('Mokoena', $user->last_name);
        $this->assertSame('0820000000', $user->phone);
        $this->assertSame('whatsapp', $user->preferred_contact);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('CorrectHorse99', $user->password));

        $types = ConsentRecord::where('user_id', $user->id)->pluck('wording', 'consent_type');
        $this->assertEqualsCanonicalizing(
            ['treatment_self_true', 'medicine_side_effects', 'terms_and_privacy', 'marketing'],
            $types->keys()->all()
        );
        $this->assertStringContainsString('Terms and Conditions', $types['terms_and_privacy']);
    }

    public function test_book_screen_lists_doctors_with_slots_and_soonest_goes_to_pay(): void
    {
        $date = Carbon::today()->addDays(2)->format('Y-m-d');
        $this->doctor($date, '09:00');

        $c = Livewire::test(BookingJourney::class, ['slug' => 'weight-loss']);
        $this->answerEverything($c)
            ->set('reviewTruth', true)->set('reviewSideEffects', true)->call('toDetails')
            ->set('name', 'Thabo Mokoena')->set('email', 'thabo2@example.com')->set('cell', '82 000 0002')
            ->set('idNumber', '8001015009087')->set('password', 'CorrectHorse99')->set('passwordConfirm', 'CorrectHorse99')
            ->set('consentCare', true)->call('sendOtp');
        $c->set('otp', (string) $this->sms->lastCode)->call('verifyOtp')
            ->assertSee('Dr Gerhard Coetzer')->assertSee('09:00')
            ->call('bookSoonest')
            ->assertSet('stage', 'pay')->assertSet('selectedDate', $date)->assertSet('selectedTime', '09:00')
            ->assertSee('R700')->assertSee('45 minutes');
    }
}
