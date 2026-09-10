<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\User;
use App\Services\RetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * POPIA per-category retention + disposal (config/retention.php + RetentionService).
 * Clinical records past their retention window are anonymised (patient_id nulled),
 * in-window records are untouched, and dry-run never mutates.
 */
class DataRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function consultation(string $createdAt): Consultation
    {
        $doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $patient = User::factory()->create(['role' => UserRole::Patient]);

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'type' => 'general',
            'status' => 'completed',
            'appointment_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '09:15',
            'fee_amount' => 45000,
        ]);

        $c = Consultation::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => 'completed',
            'diagnosis' => 'Test',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        // Force created_at to the anchor date under test.
        DB::table('consultations')->where('id', $c->id)->update(['created_at' => $createdAt]);

        return $c->fresh();
    }

    public function test_dry_run_changes_nothing(): void
    {
        $old = $this->consultation(now()->subYears(7)->toDateTimeString());

        $results = app(RetentionService::class)->apply(dryRun: true);

        $this->assertGreaterThanOrEqual(1, $results['consultations']);
        // Still linked — dry run must not mutate.
        $this->assertNotNull($old->fresh()->patient_id);
    }

    public function test_force_anonymises_past_retention_clinical_records(): void
    {
        $old = $this->consultation(now()->subYears(7)->toDateTimeString());   // past 6y
        $recent = $this->consultation(now()->subYears(1)->toDateTimeString()); // in window

        app(RetentionService::class)->apply(dryRun: false);

        // Past-retention record anonymised, in-window record preserved.
        $this->assertNull($old->fresh()->patient_id);
        $this->assertNotNull($recent->fresh()->patient_id);
    }

    public function test_command_runs_dry_by_default(): void
    {
        $old = $this->consultation(now()->subYears(7)->toDateTimeString());

        $this->artisan('data:apply-retention')->assertExitCode(0);

        // No --force => nothing disposed.
        $this->assertNotNull($old->fresh()->patient_id);
    }
}
