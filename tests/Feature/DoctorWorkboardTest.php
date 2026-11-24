<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Doctor\Appointments;
use App\Livewire\Doctor\PatientHistory;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Doctor\DoctorWorkboard;
use App\Services\Doctor\PatientNoteService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 2.7 doctor workboard (Craig's guide 4.1): new and past appointment lists with date and service filters,
 * calendar-style day/week/month views, and the patient history board. Always limited to the doctor's own patients.
 */
class DoctorWorkboardTest extends TestCase
{
    use RefreshDatabase;

    private User $doctor;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));   // a Wednesday
        $this->doctor = User::factory()->create(['role' => UserRole::Doctor]);
        $this->other = User::factory()->create(['role' => UserRole::Doctor]);
    }

    private function board(): DoctorWorkboard
    {
        return app(DoctorWorkboard::class);
    }

    private function appt(User $doctor, string $date, string $status = 'confirmed', string $type = 'general', ?User $patient = null): Appointment
    {
        return Appointment::create([
            'patient_id' => ($patient ?? User::factory()->create(['role' => UserRole::Patient]))->id,
            'doctor_id' => $doctor->id, 'type' => $type, 'status' => $status,
            'appointment_date' => $date, 'start_time' => '10:00', 'end_time' => '10:30', 'fee_amount' => 0,
        ]);
    }

    private function ids($c): array
    {
        return $c->pluck('id')->all();
    }

    public function test_new_appointments_are_only_the_doctors_own_and_only_open_ones(): void
    {
        $mine = $this->appt($this->doctor, '2026-11-26', 'pending');
        $inProgress = $this->appt($this->doctor, '2026-11-25', 'in_progress');
        $this->appt($this->doctor, '2026-11-20', 'completed');
        $this->appt($this->doctor, '2026-11-27', 'cancelled');
        $this->appt($this->other, '2026-11-26', 'confirmed');

        $ids = $this->ids($this->board()->newAppointments($this->doctor, null, null, null, 'date'));

        $this->assertSame([$inProgress->id, $mine->id], $ids);
    }

    public function test_the_default_order_is_newest_booked_first(): void
    {
        $first = $this->appt($this->doctor, '2026-11-26');
        $second = $this->appt($this->doctor, '2026-11-30');
        $third = $this->appt($this->doctor, '2026-11-28');
        $first->forceFill(['created_at' => now()->subDays(3)])->save();
        $second->forceFill(['created_at' => now()->subDay()])->save();
        $third->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->assertSame([$second->id, $third->id, $first->id], $this->ids($this->board()->newAppointments($this->doctor)));
        $this->assertSame([$first->id, $third->id, $second->id], $this->ids($this->board()->newAppointments($this->doctor, null, null, null, 'date')));
    }

    public function test_date_and_service_filters(): void
    {
        $a = $this->appt($this->doctor, '2026-11-26', 'confirmed', 'general');
        $b = $this->appt($this->doctor, '2026-12-05', 'confirmed', 'follow_up');
        $c = $this->appt($this->doctor, '2026-12-20', 'confirmed', 'follow_up');

        $this->assertSame([$b->id, $c->id], $this->ids($this->board()->newAppointments($this->doctor, null, null, 'follow_up', 'date')));
        $this->assertSame([$b->id], $this->ids($this->board()->newAppointments($this->doctor, '2026-12-01', '2026-12-10', null, 'date')));
        $this->assertSame([$a->id, $b->id], $this->ids($this->board()->newAppointments($this->doctor, null, '2026-12-05', null, 'date')), 'The end date is included.');
        $this->assertSame([], $this->ids($this->board()->newAppointments($this->doctor, '2027-01-01', null)));
    }

    public function test_bad_dates_are_refused(): void
    {
        foreach ([['from' => 'tomorrow'], ['to' => '2026-13-45'], ['from' => '2026-12-10', 'to' => '2026-12-01']] as $args) {
            try {
                $this->board()->newAppointments($this->doctor, $args['from'] ?? null, $args['to'] ?? null);
                $this->fail('Should be refused');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_day_week_and_month_ranges(): void
    {
        [$d1, $d2] = $this->board()->range('day', '2026-11-25');
        $this->assertSame(['2026-11-25', '2026-11-25'], [$d1->format('Y-m-d'), $d2->format('Y-m-d')]);

        [$w1, $w2] = $this->board()->range('week', '2026-11-25');
        $this->assertSame(['2026-11-23', '2026-11-29'], [$w1->format('Y-m-d'), $w2->format('Y-m-d')], 'Monday to Sunday.');

        [$m1, $m2] = $this->board()->range('month', '2026-11-25');
        $this->assertSame(['2026-11-01', '2026-11-30'], [$m1->format('Y-m-d'), $m2->format('Y-m-d')]);

        [$t1] = $this->board()->range('day');
        $this->assertSame('2026-11-25', $t1->format('Y-m-d'), 'Today in South African time.');

        $this->expectException(ValidationException::class);
        $this->board()->range('year');
    }

    public function test_past_appointments_show_submitted_prescriptions_and_the_next_repeat(): void
    {
        $patient = User::factory()->create(['role' => UserRole::Patient]);
        $done = $this->appt($this->doctor, '2026-11-10', 'completed', 'general', $patient);
        $older = $this->appt($this->doctor, '2026-10-01', 'no_show');
        $this->appt($this->doctor, '2026-11-26', 'confirmed');   // new, not past

        $consultation = Consultation::create(['appointment_id' => $done->id, 'patient_id' => $patient->id, 'doctor_id' => $this->doctor->id, 'status' => 'completed', 'completed_at' => now()]);
        foreach (['signed', 'draft', 'dispensed'] as $i => $status) {
            Prescription::create(['reference' => 'RX-W'.$i, 'consultation_id' => $consultation->id, 'patient_id' => $patient->id, 'doctor_id' => $this->doctor->id, 'status' => $status]);
        }
        Order::create(['patient_id' => $patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now(), 'next_repeat_date' => '2026-12-03']);

        $rows = $this->board()->pastAppointments($this->doctor);

        $this->assertSame([$done->id, $older->id], $this->ids($rows), 'Newest first, no open ones.');
        $this->assertSame(2, $rows->first()->submitted_prescriptions, 'Signed and dispensed count, a draft does not.');
        $this->assertSame('2026-12-03', \Illuminate\Support\Carbon::parse($rows->first()->next_repeat)->format('Y-m-d'));
        $this->assertSame(0, $rows->last()->submitted_prescriptions);
    }

    public function test_patient_history_groups_by_patient_filters_and_counts_readable_notes(): void
    {
        $ann = User::factory()->create(['role' => UserRole::Patient, 'first_name' => 'Annabel', 'last_name' => 'Zulu']);
        $bob = User::factory()->create(['role' => UserRole::Patient, 'first_name' => 'Bobby', 'last_name' => 'Yeo']);
        $this->appt($this->doctor, '2026-10-02', 'completed', 'general', $ann);
        $this->appt($this->doctor, '2026-11-12', 'completed', 'follow_up', $ann);
        $this->appt($this->doctor, '2026-11-14', 'completed', 'general', $bob);
        $this->appt($this->other, '2026-11-14', 'completed', 'general', User::factory()->create(['role' => UserRole::Patient]));

        $notes = app(PatientNoteService::class);
        $notes->create($this->doctor, $ann, 'clinical', 'Note A');
        $notes->create($this->doctor, $ann, 'patient_summary', 'Note B');

        $rows = $this->board()->patientHistory($this->doctor);

        $this->assertCount(2, $rows, 'Only this doctor\'s patients.');
        $annRow = $rows->firstWhere('patient.id', $ann->id);
        $this->assertSame(2, $annRow['visits']);
        $this->assertSame('2026-11-12', $annRow['last_visit']);
        $this->assertSame(2, $annRow['notes']);
        $this->assertSame(0, $rows->firstWhere('patient.id', $bob->id)['notes']);

        $this->assertCount(1, $this->board()->patientHistory($this->doctor, '2026-11-01', '2026-11-30', 'Bobby'));
        $this->assertCount(1, $this->board()->patientHistory($this->doctor, '2026-11-13', null));
        $this->assertCount(1, $this->board()->patientHistory($this->doctor, null, null, $ann->member_number ?: 'Annabel'));
        $this->assertCount(0, $this->board()->patientHistory($this->doctor, null, null, 'nobody-by-this-name'));
    }

    public function test_only_doctors_can_use_the_board(): void
    {
        foreach ([UserRole::Admin, UserRole::HealthCoach, UserRole::Patient] as $role) {
            try {
                $this->board()->newAppointments(User::factory()->create(['role' => $role]));
                $this->fail('Should be refused');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_the_screens(): void
    {
        $mine = $this->appt($this->doctor, '2026-11-26', 'confirmed', 'follow_up');
        $this->appt($this->doctor, '2026-11-10', 'completed');
        $this->appt($this->other, '2026-11-26', 'confirmed');

        $this->actingAs($this->doctor)->get(route('doctor.appointments'))->assertOk();
        $this->actingAs($this->doctor)->get(route('doctor.patient-history'))->assertOk();
        foreach ([UserRole::Admin, UserRole::HealthCoach, UserRole::Patient] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('doctor.appointments'))->assertForbidden();
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('doctor.patient-history'))->assertForbidden();
        }

        Livewire::actingAs($this->doctor)->test(Appointments::class)
            ->assertSeeHtml('data-appointment="'.$mine->id.'"')
            ->set('type', 'general')->assertDontSeeHtml('data-appointment="'.$mine->id.'"')
            ->call('clear')->assertSeeHtml('data-appointment="'.$mine->id.'"')
            ->set('view', 'week')->set('anchor', '2026-11-25')->assertSeeHtml('data-appointment="'.$mine->id.'"')
            ->set('anchor', '2026-12-25')->assertDontSeeHtml('data-appointment="'.$mine->id.'"')
            ->set('view', 'list')->set('tab', 'past')->assertDontSeeHtml('data-appointment="'.$mine->id.'"')
            ->set('from', '2026-12-10')->set('to', '2026-12-01')->assertHasErrors('to');

        Livewire::actingAs($this->doctor)->test(PatientHistory::class)
            ->set('from', 'garbage')->assertHasErrors('from');
    }
}
