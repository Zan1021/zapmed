<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Doctor\RecordsPanel;
use App\Livewire\Patient\HealthStats;
use App\Models\Appointment;
use App\Models\BloodTestRequest;
use App\Models\CoachFlag;
use App\Models\HealthRecord;
use App\Models\Order;
use App\Models\PatientProfile;
use App\Models\SideEffectEntry;
use App\Models\User;
use App\Models\WeightEntry;
use App\Services\Doctor\BloodTestService;
use App\Services\Doctor\PatientNoteService;
use App\Services\Patient\HealthRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 3.2 Health Stats tab (Craig's guide 3.1): weight, side effects for weight-loss patients, records and results,
 * tests the doctor asked for, and the doctor's summary. Records are private to the patient and treating doctors.
 */
class PatientHealthStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $patient;
    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['patient.health.records.disk' => 'local', 'doctor.blood_tests.disk' => 'local']);
        Storage::fake('local');

        $this->patient = $this->newPatient();
        $this->doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $this->treat($this->doctor, $this->patient);
    }

    private function newPatient(): User
    {
        $p = User::factory()->create(['role' => UserRole::Patient]);
        PatientProfile::create(['user_id' => $p->id, 'onboarding_complete' => true]);

        return $p;
    }

    private function treat(User $doctor, User $patient): void
    {
        Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'completed',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
        ]);
    }

    private function weightLossOrder(?User $patient = null): Order
    {
        return Order::create(['patient_id' => ($patient ?? $this->patient)->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now(), 'service_category' => 'Weight Loss']);
    }

    private function pdf(string $name = 'scan.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 30, 'application/pdf');
    }

    private function svc(): HealthRecords
    {
        return app(HealthRecords::class);
    }

    public function test_a_patient_logs_their_weight_and_sees_the_numbers(): void
    {
        Livewire::actingAs($this->patient)->test(HealthStats::class)
            ->assertSee('No weight logged yet')
            ->set('weight', '84.5')->call('logWeight')->assertHasNoErrors()
            ->assertSee('84.5 kg');

        $this->assertSame('84.5', (string) WeightEntry::where('user_id', $this->patient->id)->first()->weight_kg);
    }

    public function test_weight_is_checked(): void
    {
        foreach (['abc', '5', '999', ''] as $bad) {
            Livewire::actingAs($this->patient)->test(HealthStats::class)->set('weight', $bad)->call('logWeight')->assertHasErrors('weight');
        }
        $this->assertSame(0, WeightEntry::count());
    }

    public function test_the_chart_draws_once_there_is_data(): void
    {
        Livewire::actingAs($this->patient)->test(HealthStats::class)->assertDontSeeHtml('data-chart');

        WeightEntry::create(['user_id' => $this->patient->id, 'logged_on' => now()->subDays(10)->toDateString(), 'weight_kg' => 90]);
        WeightEntry::create(['user_id' => $this->patient->id, 'logged_on' => now()->toDateString(), 'weight_kg' => 88]);

        Livewire::actingAs($this->patient)->test(HealthStats::class)->assertSeeHtml('data-chart')->assertSee('88.0 kg');
    }

    public function test_coach_entered_weight_shows_up_too(): void
    {
        WeightEntry::create(['user_id' => $this->patient->id, 'logged_on' => now()->toDateString(), 'weight_kg' => 77.3]);

        Livewire::actingAs($this->patient)->test(HealthStats::class)->assertSee('77.3 kg');
    }

    public function test_height_is_checked_and_saved(): void
    {
        Livewire::actingAs($this->patient)->test(HealthStats::class)
            ->set('height', '50')->call('saveHeight')->assertHasErrors('height')
            ->set('height', 'tall')->call('saveHeight')->assertHasErrors('height')
            ->set('height', '172.5')->call('saveHeight')->assertHasNoErrors();

        $this->assertSame('172.5', (string) PatientProfile::where('user_id', $this->patient->id)->first()->height_cm);
    }

    public function test_side_effect_logging_is_only_for_weight_loss_patients(): void
    {
        Livewire::actingAs($this->patient)->test(HealthStats::class)->assertDontSeeHtml('data-section="side-effects"');
        try {
            Livewire::actingAs($this->patient)->test(HealthStats::class)->set('scores', ['Nausea' => 3])->call('saveSideEffects')->assertForbidden();
        } finally {
            $this->assertSame(0, SideEffectEntry::count());
        }

        $this->weightLossOrder();
        Livewire::actingAs($this->patient)->test(HealthStats::class)->assertSeeHtml('data-section="side-effects"')->assertSee('never advice to change your treatment');
    }

    public function test_a_weight_loss_patient_logs_side_effects_and_a_serious_one_raises_a_flag(): void
    {
        $this->weightLossOrder();
        $names = config('coach.default_symptoms');
        $this->assertNotEmpty($names);

        Livewire::actingAs($this->patient)->test(HealthStats::class)
            ->call('saveSideEffects')->assertHasErrors('scores')
            ->set('scores', [$names[0] => 2])->call('saveSideEffects')->assertHasNoErrors()
            ->assertSee($names[0].' 2/10');

        $this->assertSame(0, CoachFlag::count(), 'A mild symptom raises nothing.');

        $abdominal = collect($names)->first(fn ($n) => stripos($n, 'abdominal') !== false);
        $this->assertNotNull($abdominal, 'The default list has abdominal pain.');
        Livewire::actingAs($this->patient)->test(HealthStats::class)
            ->set('scores', [$abdominal => 8])->call('saveSideEffects')->assertHasNoErrors()
            ->assertSeeHtml('data-flagged');

        $this->assertSame(1, CoachFlag::count());
    }

    public function test_the_coach_add_on_also_counts_as_weight_loss(): void
    {
        $coach = User::factory()->create(['role' => UserRole::HealthCoach]);
        \App\Models\CoachingAssignment::create(['patient_id' => $this->patient->id, 'coach_id' => $coach->id, 'started_at' => now()]);

        Livewire::actingAs($this->patient)->test(HealthStats::class)->assertSeeHtml('data-section="side-effects"');
    }

    public function test_a_patient_uploads_a_record_and_only_they_and_treating_doctors_can_open_it(): void
    {
        Livewire::actingAs($this->patient)->test(HealthStats::class)
            ->set('recordFile', $this->pdf())->call('uploadRecord')->assertHasErrors('title')
            ->set('recordTitle', 'Old blood results')->set('recordFile', $this->pdf())->call('uploadRecord')->assertHasNoErrors()
            ->assertSee('Old blood results')->assertSee('uploaded by you');

        $r = HealthRecord::firstOrFail();
        $this->assertSame('record', $r->category);
        $this->assertSame('patient', $r->uploader_role);
        $this->assertNotSame('Old blood results', \DB::table('health_records')->value('title'), 'Title is encrypted.');
        Storage::disk('local')->assertExists($r->path);
        $this->assertStringNotContainsString('scan', $r->path);

        $this->actingAs($this->patient)->get(route('health-records.show', $r))->assertOk();
        $this->actingAs($this->doctor)->get(route('health-records.show', $r))->assertOk();
        foreach ([User::factory()->create(['role' => UserRole::Doctor]), $this->newPatient(), User::factory()->create(['role' => UserRole::Admin]), User::factory()->create(['role' => UserRole::HealthCoach])] as $no) {
            $this->actingAs($no)->get(route('health-records.show', $r))->assertForbidden();
        }
    }

    public function test_bad_files_are_refused(): void
    {
        foreach ([UploadedFile::fake()->create('run.exe', 5), UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'), UploadedFile::fake()->create('doc.docx', 5)] as $bad) {
            try {
                $this->svc()->upload($this->patient, $this->patient, $bad, 'x');
                $this->fail('Should be refused');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('file', $e->errors());
            }
        }
        $this->assertSame(0, HealthRecord::count());
    }

    public function test_a_treating_doctor_uploads_a_result_the_patient_can_read(): void
    {
        $r = $this->svc()->upload($this->doctor, $this->patient, $this->pdf('ecg.pdf'), 'ECG result');

        $this->assertSame('result', $r->category);
        $this->assertSame('doctor', $r->uploader_role);
        Livewire::actingAs($this->patient)->test(HealthStats::class)->assertSee('ECG result')->assertSee('from your doctor');
    }

    public function test_only_the_patient_or_a_treating_doctor_can_upload(): void
    {
        $other = $this->newPatient();
        foreach ([User::factory()->create(['role' => UserRole::Doctor]), User::factory()->create(['role' => UserRole::Admin]), User::factory()->create(['role' => UserRole::HealthCoach]), $other] as $who) {
            try {
                $this->svc()->upload($who, $this->patient, $this->pdf(), 'x');
                $this->fail('Should be forbidden');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->assertSame(0, HealthRecord::count());
    }

    public function test_a_patient_sees_only_their_own_records(): void
    {
        $other = $this->newPatient();
        $this->svc()->upload($other, $other, $this->pdf(), 'Someone elses secret');
        $mine = $this->svc()->upload($this->patient, $this->patient, $this->pdf(), 'Mine');

        Livewire::actingAs($this->patient)->test(HealthStats::class)->assertSee('Mine')->assertDontSee('Someone elses secret');
        $this->assertSame([$mine->id], $this->svc()->listFor($this->patient, $this->patient)->pluck('id')->all());
    }

    public function test_the_doctor_summary_shows_but_confidential_and_staff_notes_never_do(): void
    {
        $notes = app(PatientNoteService::class);
        $notes->create($this->doctor, $this->patient, 'clinical', 'CLINICAL SECRET');
        $notes->create($this->doctor, $this->patient, 'operations', 'STAFF SECRET', [['role' => 'admin']]);
        $notes->create($this->doctor, $this->patient, 'patient_summary', 'Older summary');
        $notes->create($this->doctor, $this->patient, 'patient_summary', 'Your blood pressure is on track.');

        Livewire::actingAs($this->patient)->test(HealthStats::class)
            ->assertSee('Your blood pressure is on track.')->assertDontSee('Older summary')
            ->assertDontSee('CLINICAL SECRET')->assertDontSee('STAFF SECRET');
    }

    public function test_tests_the_doctor_asked_for_show_and_the_patient_can_send_results(): void
    {
        $r = app(BloodTestService::class)->request($this->doctor, $this->patient, ['hba1c'], null, 'pathcare');

        Livewire::actingAs($this->patient)->test(HealthStats::class)
            ->assertSee('Waiting for your results')
            ->call('uploadTestResult', $r->id)->assertHasErrors('test_'.$r->id)
            ->set('testUploads.'.$r->id, $this->pdf('hba1c.pdf'))->call('uploadTestResult', $r->id)->assertHasNoErrors()
            ->assertSee('Results received');

        $this->assertSame(BloodTestRequest::RESULTS_RECEIVED, $r->fresh()->status);
        $this->assertSame('patient', $r->results()->first()->uploader_role);
    }

    public function test_a_patient_cannot_send_results_to_someone_elses_request(): void
    {
        $other = $this->newPatient();
        $this->treat($this->doctor, $other);
        $r = app(BloodTestService::class)->request($this->doctor, $other, ['fbc']);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($this->patient)->test(HealthStats::class)
            ->set('testUploads.'.$r->id, $this->pdf())->call('uploadTestResult', $r->id);
    }

    public function test_the_page_is_for_patients_and_the_doctor_panel_works(): void
    {
        $this->actingAs($this->patient)->get(route('patient.health'))->assertOk();
        foreach ([UserRole::Doctor, UserRole::Admin, UserRole::HealthCoach] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('patient.health'))->assertForbidden();
        }

        Livewire::actingAs($this->doctor)->test(RecordsPanel::class, ['patientId' => $this->patient->id])
            ->set('file', $this->pdf())->call('upload')->assertHasErrors('title')
            ->set('title', 'Spirometry')->call('upload')->assertHasNoErrors()->assertSee('Spirometry');

        $appt = Appointment::where('doctor_id', $this->doctor->id)->firstOrFail();
        $this->actingAs($this->doctor)->get(route('doctor.consultation', $appt))->assertOk()->assertSee('data-panel="records"', false);
    }
}
