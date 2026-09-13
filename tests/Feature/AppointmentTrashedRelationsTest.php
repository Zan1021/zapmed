<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the admin-dashboard "Unknown / ??" defect.
 *
 * An appointment's patient/doctor/cancelledBy references must remain resolvable
 * even after the referenced User account is soft-deleted, so historical records
 * stay auditable in admin/CRM screens. The relations use withTrashed() to
 * guarantee this. These tests fail if that guard is ever removed.
 */
class AppointmentTrashedRelationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAppointment(User $patient, User $doctor): Appointment
    {
        return Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'consultation',
            'status' => 'pending',
            'appointment_date' => now()->addDay(),
            'start_time' => '09:00',
            'end_time' => '09:30',
            'fee_amount' => 0,
        ]);
    }

    public function test_patient_relation_resolves_after_patient_soft_deleted(): void
    {
        $patient = User::factory()->create([
            'role' => UserRole::Patient,
            'first_name' => 'Soft',
            'last_name' => 'Deleted',
        ]);
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);

        $appointment = $this->makeAppointment($patient, $doctor);

        $patient->delete();

        $fresh = $appointment->fresh();

        $this->assertNotNull($fresh->patient, 'Patient relation should resolve even when soft-deleted.');
        $this->assertSame('Soft Deleted', $fresh->patient->name);
        $this->assertTrue($fresh->patient->trashed());
    }

    public function test_doctor_relation_resolves_after_doctor_soft_deleted(): void
    {
        $patient = User::factory()->create(['role' => UserRole::Patient]);
        $doctor = User::factory()->create([
            'role' => UserRole::Doctor,
            'first_name' => 'Gone',
            'last_name' => 'Doctor',
        ]);

        $appointment = $this->makeAppointment($patient, $doctor);

        $doctor->delete();

        $this->assertNotNull($appointment->fresh()->doctor, 'Doctor relation should resolve even when soft-deleted.');
        $this->assertSame('Gone Doctor', $appointment->fresh()->doctor->name);
    }

    public function test_cancelled_by_user_relation_resolves_after_soft_delete(): void
    {
        $patient = User::factory()->create(['role' => UserRole::Patient]);
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'first_name' => 'Cancelling',
            'last_name' => 'Admin',
        ]);

        $appointment = $this->makeAppointment($patient, $doctor);
        $appointment->update(['cancelled_by' => $admin->id, 'cancelled_at' => now()]);

        $admin->delete();

        $this->assertNotNull($appointment->fresh()->cancelledByUser, 'CancelledBy relation should resolve even when soft-deleted.');
        $this->assertSame('Cancelling Admin', $appointment->fresh()->cancelledByUser->name);
    }

    public function test_relations_still_resolve_for_active_users(): void
    {
        $patient = User::factory()->create([
            'role' => UserRole::Patient,
            'first_name' => 'Active',
            'last_name' => 'Patient',
        ]);
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);

        $appointment = $this->makeAppointment($patient, $doctor);

        $this->assertNotNull($appointment->patient);
        $this->assertSame('Active Patient', $appointment->patient->name);
        $this->assertFalse($appointment->patient->trashed());
    }
}
