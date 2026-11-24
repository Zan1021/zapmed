<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Operations\StaffAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 4 leftovers: the doctor's uploaded signature is printed on their prescription PDF. The file stays private: the PDF
 * embeds it, nobody gets a link to it.
 */
class OperationsSignatureOnPrescriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;
    private User $doctor;
    private User $patient;
    private Prescription $rx;

    protected function setUp(): void
    {
        parent::setUp();
        config(['operations.staff.signature.disk' => 'local']);
        Storage::fake('local');

        $this->ops = User::factory()->create(['role' => UserRole::Admin]);
        $this->doctor = User::factory()->create(['role' => UserRole::Doctor]);
        DoctorProfile::create(['user_id' => $this->doctor->id, 'hpcsa_number' => 'MP1', 'qualification' => 'MBChB', 'is_verified' => true]);
        $this->patient = User::factory()->create(['role' => UserRole::Patient]);

        $appt = Appointment::create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'type' => 'general', 'status' => 'completed', 'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:15', 'fee_amount' => 1]);
        $c = Consultation::create(['appointment_id' => $appt->id, 'patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'status' => 'completed']);
        $this->rx = Prescription::create(['consultation_id' => $c->id, 'patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'status' => 'signed', 'total_amount' => 1, 'payment_status' => 'paid', 'pharmacy_status' => 'pending', 'signed_at' => now()]);
    }

    private function svc(): StaffAdmin
    {
        return app(StaffAdmin::class);
    }

    public function test_without_an_upload_there_is_no_image(): void
    {
        $this->assertNull($this->svc()->signatureDataUri($this->doctor));

        $html = view('pdf.prescription', ['prescription' => $this->rx->load(['items', 'doctor.doctorProfile', 'patient', 'consultation']), 'signatureImage' => null])->render();
        $this->assertStringNotContainsString('alt="Signature"', $html);
    }

    public function test_an_uploaded_signature_becomes_an_inline_image_of_the_right_type(): void
    {
        $this->svc()->uploadSignature($this->ops, $this->doctor, UploadedFile::fake()->image('sig.png', 200, 80));

        $uri = $this->svc()->signatureDataUri($this->doctor);

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $this->assertNotFalse(base64_decode(substr($uri, strlen('data:image/png;base64,')), true));

        $html = view('pdf.prescription', ['prescription' => $this->rx->load(['items', 'doctor.doctorProfile', 'patient', 'consultation']), 'signatureImage' => $uri])->render();
        $this->assertStringContainsString('alt="Signature"', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
    }

    public function test_a_missing_file_or_a_doctor_with_no_profile_gives_null_not_an_error(): void
    {
        $this->svc()->uploadSignature($this->ops, $this->doctor, UploadedFile::fake()->image('sig.png', 100, 40));
        Storage::disk('local')->deleteDirectory('staff-signatures');

        $this->assertNull($this->svc()->signatureDataUri($this->doctor));
        $this->assertNull($this->svc()->signatureDataUri(User::factory()->create(['role' => UserRole::Doctor])));
    }

    public function test_the_pdf_still_downloads_with_and_without_a_signature_and_is_still_limited_to_the_two_people(): void
    {
        $this->actingAs($this->patient)->get(route('pdf.prescription', $this->rx))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->svc()->uploadSignature($this->ops, $this->doctor, UploadedFile::fake()->image('sig.png', 200, 80));
        $this->actingAs($this->doctor)->get(route('pdf.prescription', $this->rx))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs(User::factory()->create(['role' => UserRole::Patient]))->get(route('pdf.prescription', $this->rx))->assertForbidden();
        $this->actingAs($this->ops)->get(route('pdf.prescription', $this->rx))->assertForbidden();
    }
}
