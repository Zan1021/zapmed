<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Doctor\ConsultationScreen;
use App\Livewire\Doctor\PrescriptionBuilder;
use App\Livewire\Patient\BookingJourney;
use App\Models\Appointment;
use App\Models\Assessment;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use App\Models\PatientProfile;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Doctor\BodyMeasurements;
use App\Services\Assessments\QuestionBankService;
use App\Support\SaIdNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Small fixes from the tasklist: injection route + once weekly on the prescription form; age, gender, height and BMI reach the doctor. */
class ConsultSmallFixesTest extends TestCase
{
    use RefreshDatabase;

    /** A valid 13 digit South African ID number for a date of birth and a sex, with its check digit worked out. */
    private function saId(string $yymmdd, int $seq, int $race = 8): string
    {
        $base = $yymmdd.str_pad((string) $seq, 4, '0', STR_PAD_LEFT).'0'.$race;
        $sum = 0;
        foreach (array_reverse(str_split($base)) as $i => $d) {   // the check digit is still to be added, so positions shift by one
            $n = (int) $d;
            if ($i % 2 === 0) {
                $n *= 2;
                $n = $n > 9 ? $n - 9 : $n;
            }
            $sum += $n;
        }

        return $base.((10 - $sum % 10) % 10);
    }

    // --- SA ID number ----------------------------------------------------------------------------------------------

    public function test_a_valid_id_gives_date_of_birth_and_gender(): void
    {
        $male = $this->saId('800101', 5009);
        $female = $this->saId('920220', 4720);

        $this->assertTrue(SaIdNumber::isValid($male));
        $this->assertSame('1980-01-01', SaIdNumber::dateOfBirth($male)->toDateString());
        $this->assertSame('male', SaIdNumber::gender($male));
        $this->assertSame('1992-02-20', SaIdNumber::dateOfBirth($female)->toDateString());
        $this->assertSame('female', SaIdNumber::gender($female));
        $this->assertTrue(SaIdNumber::isValid(substr($male, 0, 6).' '.substr($male, 6)), 'Spaces are ignored.');
    }

    public function test_the_century_follows_this_year(): void
    {
        $yy = now()->format('y');
        $this->assertSame('20'.$yy, SaIdNumber::dateOfBirth($this->saId($yy.'0101', 1000))->format('Y'));
        $this->assertSame('1999', SaIdNumber::dateOfBirth($this->saId('990101', 1000))->format('Y'));
    }

    public function test_a_wrong_id_gives_nothing(): void
    {
        $good = $this->saId('800101', 5009);
        $badCheck = substr($good, 0, 12).((int) substr($good, 12) + 1) % 10;

        foreach ([null, '', '123', $badCheck, '8013015009087', 'abcdefghijklm'] as $bad) {
            $this->assertFalse(SaIdNumber::isValid($bad), (string) $bad);
            $this->assertNull(SaIdNumber::gender($bad));
        }
        $this->assertNull(SaIdNumber::dateOfBirth('8002305009087'), '30 February is not a date.');
    }

    // --- body measurements -------------------------------------------------------------------------------------------

    public function test_the_measurements_answer_becomes_numbers_and_a_bmi(): void
    {
        $this->assertSame(['weight_kg' => 85.0, 'height_cm' => 170.0, 'bmi' => 29.4, 'category' => 'Overweight'], BodyMeasurements::fromAnswer('85 kg, 170 cm'));
        $this->assertSame('Healthy weight', BodyMeasurements::fromAnswer('60.5 kg, 170.5 cm')['category']);
        $this->assertSame('Obesity class 2', BodyMeasurements::fromAnswer('115 kg, 170 cm')['category']);
        $this->assertSame('Underweight', BodyMeasurements::category(18.4));
        foreach ([null, 'n/a', '85 kg', '10 kg, 170 cm', '85 kg, 20 cm', ['85 kg, 170 cm']] as $bad) {
            $this->assertNull(BodyMeasurements::fromAnswer($bad));
        }
    }

    // --- the journey fills in the profile ----------------------------------------------------------------------------

    public function test_the_journey_saves_date_of_birth_gender_height_and_weight(): void
    {
        config()->set('onboarding.enabled_slugs', ['weight-loss']);
        $sms = new class extends \App\Services\SmsService {
            public ?string $lastCode = null;
            public function __construct() {}
            public function sendOtp(string $phoneNumber, string $code): bool { $this->lastCode = $code; return true; }
        };
        $this->app->instance(\App\Services\SmsService::class, $sms);

        $bank = app(QuestionBankService::class);
        $cat = $bank->createCategory('weight-loss', 'Weight Loss');
        $q = $bank->createQuestion(['scope' => 'category', 'question_key' => 'WL_MEAS'],
            ['text' => 'Your current measurements', 'type' => 'measurements', 'required' => true], null);
        $bank->addQuestionToCategory($cat->refresh(), $q, null);
        $bank->publish($q, $q->versions()->latest('version')->first(), 'test', null);
        $bank->publishCategory($cat->refresh(), null);

        $id = $this->saId('850315', 5123);
        $c = Livewire::test(BookingJourney::class, ['slug' => 'weight-loss']);
        $c->set('answers.WL_MEAS_weight', 92)->set('answers.WL_MEAS_height', 175)->call('nextQuestion')->assertSet('stage', 'review');
        $c->set('reviewTruth', true)->set('reviewSideEffects', true)->call('toDetails')->assertSet('stage', 'details');
        $c->set('name', 'Thabo Mokoena')->set('email', 'thabo@example.com')->set('cell', '82 000 0000')
            ->set('idNumber', $id)->set('communication', 'WhatsApp')
            ->set('password', 'CorrectHorse99')->set('passwordConfirm', 'CorrectHorse99')->set('consentCare', true)->call('sendOtp');
        $c->set('otp', (string) $sms->lastCode)->call('verifyOtp')->assertSet('verified', true);

        $user = User::where('email', 'thabo@example.com')->firstOrFail();
        $this->assertSame('1985-03-15', $user->date_of_birth->toDateString());
        $this->assertSame('male', $user->gender);
        $profile = PatientProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame([92.0, 175.0, 30.0], [(float) $profile->weight_kg, (float) $profile->height_cm, (float) $profile->bmi]);
    }

    // --- the consult screen ------------------------------------------------------------------------------------------

    private function consult(array $assessmentAnswers, ?PatientProfile $profileData = null): array
    {
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        DoctorProfile::create(['user_id' => $doctor->id, 'hpcsa_number' => 'MP'.uniqid(), 'speciality' => 'GP', 'qualification' => 'MBChB', 'doctor_type' => 'full_time', 'is_verified' => true]);
        $patient = User::factory()->create(['role' => UserRole::Patient]);
        $appt = Appointment::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'general-health', 'status' => 'confirmed', 'appointment_date' => now()->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '10:45', 'communication_preference' => 'video', 'fee_amount' => 70000, 'is_paid' => true]);
        Assessment::create(['user_id' => $patient->id, 'appointment_id' => $appt->id, 'treatment_slug' => 'weight-loss', 'treatment_name' => 'Weight loss', 'answers' => $assessmentAnswers, 'status' => 'completed']);

        return [$doctor, $patient, $appt];
    }

    public function test_the_consult_screen_shows_the_bmi_from_the_assessment_when_the_profile_has_none(): void
    {
        [$doctor, , $appt] = $this->consult([['question' => 'Your current measurements', 'type' => 'measurements', 'answer' => '85 kg, 170 cm']]);

        Livewire::actingAs($doctor)->test(ConsultationScreen::class, ['appointment' => $appt])
            ->assertSeeHtml('data-reported-measurements')->assertSee('29.4 (Overweight)');
    }

    public function test_the_assessment_bmi_is_not_shown_twice_when_the_profile_already_has_one(): void
    {
        [$doctor, $patient, $appt] = $this->consult([['question' => 'Your current measurements', 'type' => 'measurements', 'answer' => '85 kg, 170 cm']]);
        PatientProfile::create(['user_id' => $patient->id, 'height_cm' => 170, 'weight_kg' => 85]);

        Livewire::actingAs($doctor)->test(ConsultationScreen::class, ['appointment' => $appt])
            ->assertDontSeeHtml('data-reported-measurements')->assertSee('29.4');
    }

    public function test_no_measurements_means_no_extra_card(): void
    {
        [$doctor, , $appt] = $this->consult([['question' => 'Anything else?', 'type' => 'text', 'answer' => 'No']]);

        Livewire::actingAs($doctor)->test(ConsultationScreen::class, ['appointment' => $appt])->assertDontSeeHtml('data-reported-measurements');
    }

    // --- the prescription form ---------------------------------------------------------------------------------------

    public function test_a_once_weekly_subcutaneous_prescription_can_be_written(): void
    {
        Mail::fake();
        [$doctor, $patient, $appt] = $this->consult([]);
        $consultation = Consultation::create(['appointment_id' => $appt->id, 'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'status' => 'in_progress', 'started_at' => now(), 'diagnosis' => 'Obesity']);

        Livewire::actingAs($doctor)->test(PrescriptionBuilder::class, ['consultation' => $consultation])->call('addCustomMedication')
            ->assertSeeHtml('<option value="subcutaneous">Subcutaneous</option>')->assertSeeHtml('<option value="once weekly">Once weekly</option>')
            ->set('medicationName', 'Test pen')->set('medicationForm', 'injection')->set('medicationStrength', '1 mg')->set('dosage', '0.25 mg')
            ->set('frequency', 'once weekly')->set('route', 'subcutaneous')->set('quantity', 4)->call('addItem')->call('signPrescription')
            ->assertSet('prescriptionSigned', true);

        $item = Prescription::where('consultation_id', $consultation->id)->firstOrFail()->items()->firstOrFail();
        $this->assertSame(['once weekly', 'subcutaneous'], [$item->frequency, $item->route]);
    }

    // --- patients with no usable ID number --------------------------------------------------------------------------

    /** The journey up to the details step, with a question bank that has only the measurements question. */
    private function journeyAtDetails(): array
    {
        config()->set('onboarding.enabled_slugs', ['weight-loss']);
        $sms = new class extends \App\Services\SmsService {
            public ?string $lastCode = null;
            public function __construct() {}
            public function sendOtp(string $phoneNumber, string $code): bool { $this->lastCode = $code; return true; }
        };
        $this->app->instance(\App\Services\SmsService::class, $sms);

        $bank = app(QuestionBankService::class);
        $cat = $bank->createCategory('weight-loss', 'Weight Loss');
        $q = $bank->createQuestion(['scope' => 'category', 'question_key' => 'WL_MEAS'], ['text' => 'Your current measurements', 'type' => 'measurements', 'required' => true], null);
        $bank->addQuestionToCategory($cat->refresh(), $q, null);
        $bank->publish($q, $q->versions()->latest('version')->first(), 'test', null);
        $bank->publishCategory($cat->refresh(), null);

        $c = Livewire::test(BookingJourney::class, ['slug' => 'weight-loss']);
        $c->set('answers.WL_MEAS_weight', 80)->set('answers.WL_MEAS_height', 170)->call('nextQuestion');
        $c->set('reviewTruth', true)->set('reviewSideEffects', true)->call('toDetails')->assertSet('stage', 'details');
        $c->set('name', 'Lerato Dlamini')->set('email', 'lerato@example.com')->set('cell', '82 000 0000')->set('communication', 'email')
            ->set('password', 'CorrectHorse99')->set('passwordConfirm', 'CorrectHorse99')->set('consentCare', true);

        return [$c, $sms];
    }

    public function test_a_patient_with_no_id_is_asked_for_date_of_birth_and_gender_and_they_are_saved(): void
    {
        [$c, $sms] = $this->journeyAtDetails();
        $c->set('noId', true)->assertSeeHtml('data-personal-details');

        $c->call('sendOtp')->assertHasErrors(['dateOfBirth', 'gender'])->assertSet('otpSent', false);
        $c->set('dateOfBirth', now()->addDay()->format('Y-m-d'))->set('gender', 'female')->call('sendOtp')->assertHasErrors('dateOfBirth');
        $c->set('dateOfBirth', '1991-07-04')->set('gender', 'robot')->call('sendOtp')->assertHasErrors('gender');

        $c->set('gender', 'female')->call('sendOtp')->assertHasNoErrors()->assertSet('otpSent', true);
        $c->set('otp', (string) $sms->lastCode)->call('verifyOtp')->assertSet('verified', true);

        $user = User::where('email', 'lerato@example.com')->firstOrFail();
        $this->assertSame(['1991-07-04', 'female', null], [$user->date_of_birth->toDateString(), $user->gender, $user->id_number]);
        $this->assertSame(80.0, (float) PatientProfile::where('user_id', $user->id)->value('weight_kg'));
    }

    public function test_a_13_digit_number_that_is_not_a_real_id_also_asks_for_the_two_details(): void
    {
        [$c] = $this->journeyAtDetails();
        $good = $this->saId('850315', 5123);
        $bad = substr($good, 0, 12).((int) substr($good, 12) + 1) % 10;

        $c->set('idNumber', $bad)->assertSeeHtml('data-personal-details')->call('sendOtp')->assertHasErrors(['dateOfBirth', 'gender']);
        $c->set('idNumber', $good)->assertDontSeeHtml('data-personal-details');
    }

    public function test_a_real_id_needs_no_extra_details(): void
    {
        [$c] = $this->journeyAtDetails();

        $c->set('idNumber', $this->saId('850315', 5123))->call('sendOtp')->assertHasNoErrors()->assertSet('otpSent', true);
    }

    // --- the consult screen does not start an appointment that is not due ------------------------------------------

    public function test_opening_the_consult_screen_only_starts_a_confirmed_appointment_that_is_due(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-25 10:00', 'Africa/Johannesburg'));
        [$doctor, , $today] = $this->consult([]);
        [, , $later] = $this->consult([]);
        [, , $unpaid] = $this->consult([]);
        $later->update(['appointment_date' => '2026-11-28', 'doctor_id' => $doctor->id]);
        $today->update(['appointment_date' => '2026-11-25', 'doctor_id' => $doctor->id]);
        $unpaid->update(['appointment_date' => '2026-11-25', 'status' => 'pending_payment', 'doctor_id' => $doctor->id]);

        Livewire::actingAs($doctor)->test(ConsultationScreen::class, ['appointment' => $later]);
        Livewire::actingAs($doctor)->test(ConsultationScreen::class, ['appointment' => $unpaid]);
        $this->assertSame('confirmed', $later->fresh()->status, 'A future appointment is not started by looking at it.');
        $this->assertSame('pending_payment', $unpaid->fresh()->status, 'An unpaid appointment is not started.');

        Livewire::actingAs($doctor)->test(ConsultationScreen::class, ['appointment' => $today]);
        $this->assertSame('in_progress', $today->fresh()->status);
    }

    public function test_the_video_call_is_refused_for_an_appointment_that_is_not_due(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-25 10:00', 'Africa/Johannesburg'));
        [$doctor, , $later] = $this->consult([]);
        $later->update(['appointment_date' => '2026-11-28', 'doctor_id' => $doctor->id]);
        $this->mock(\App\Services\DailyService::class, fn ($m) => $m->shouldNotReceive('createRoom'));

        Livewire::actingAs($doctor)->test(ConsultationScreen::class, ['appointment' => $later])->call('startVideoCall')->assertSee('28 Nov 2026');

        $this->assertSame('confirmed', $later->fresh()->status);
        $this->assertSame(0, \App\Models\VideoSession::count());
    }
}
