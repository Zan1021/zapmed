<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\OrderControlsPanel;
use App\Livewire\Doctor\FollowUps;
use App\Livewire\Doctor\MyPatients;
use App\Models\CrmAuditEvent;
use App\Models\Order;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Services\Coach\CoachAccess;
use App\Services\Doctor\BloodTestService;
use App\Services\Doctor\FollowUpService;
use App\Services\Doctor\PatientNoteService;
use App\Services\Operations\StaffAssignments;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4 leftover: several doctors and health coaches per patient, with start, end and who assigned them (Craig's guide 8.1).
 * An assignment gives role-appropriate access and never crosses the confidential-note walls.
 */
class OperationsStaffAssignmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;
    private User $patient;
    private User $doctorA;
    private User $doctorB;
    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));
        $this->ops = User::factory()->create(['role' => UserRole::Admin]);
        $this->patient = User::factory()->create(['role' => UserRole::Patient]);
        $this->doctorA = User::factory()->create(['role' => UserRole::Doctor]);
        $this->doctorB = User::factory()->create(['role' => UserRole::Doctor]);
        $this->coach = User::factory()->create(['role' => UserRole::HealthCoach]);
    }

    private function svc(): StaffAssignments
    {
        return app(StaffAssignments::class);
    }

    private function access(): CoachAccess
    {
        return app(CoachAccess::class);
    }

    private function forbidden(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected 403');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_several_doctors_and_coaches_can_look_after_one_patient(): void
    {
        $this->svc()->assign($this->ops, $this->patient, $this->doctorA);
        $this->svc()->assign($this->ops, $this->patient, $this->doctorB, 'Covers weekends');
        $this->svc()->assign($this->ops, $this->patient, $this->coach);

        $team = $this->svc()->historyFor($this->patient);

        $this->assertCount(3, $team);
        $this->assertEqualsCanonicalizing(['doctor', 'doctor', 'health_coach'], $team->pluck('role')->all());
        $this->assertSame($this->ops->id, $team->first()->assigned_by);
        $this->assertSame('2026-11-25', $team->first()->started_at->setTimezone('Africa/Johannesburg')->format('Y-m-d'));
        $this->assertSame('Covers weekends', $team->firstWhere('staff_id', $this->doctorB->id)->note);
    }

    public function test_who_can_be_assigned_to_whom(): void
    {
        $off = User::factory()->create(['role' => UserRole::Doctor, 'is_active' => false]);
        $cases = [
            'staff' => fn () => $this->svc()->assign($this->ops, $this->patient, User::factory()->create(['role' => UserRole::Admin])),
            'staff ' => fn () => $this->svc()->assign($this->ops, $this->patient, User::factory()->create(['role' => UserRole::Patient])),
            'staff  ' => fn () => $this->svc()->assign($this->ops, $this->patient, $off),
            'patient' => fn () => $this->svc()->assign($this->ops, $this->doctorA, $this->coach),
        ];
        foreach ($cases as $field => $try) {
            try {
                $try();
                $this->fail("Should be refused: $field");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(trim($field), $e->errors());
            }
        }

        $this->svc()->assign($this->ops, $this->patient, $this->doctorA);
        $this->expectException(ValidationException::class);   // the same doctor twice at once
        $this->svc()->assign($this->ops, $this->patient, $this->doctorA);
    }

    public function test_after_an_assignment_ends_the_same_person_can_be_assigned_again(): void
    {
        $a = $this->svc()->assign($this->ops, $this->patient, $this->doctorA);
        $this->svc()->end($this->ops, $a);

        $second = $this->svc()->assign($this->ops, $this->patient, $this->doctorA);

        $this->assertNotSame($a->id, $second->id);
        $this->assertSame(2, StaffAssignment::where('patient_id', $this->patient->id)->count(), 'History is kept.');
    }

    public function test_ending_keeps_the_row_records_who_and_cannot_be_done_twice(): void
    {
        $a = $this->svc()->assign($this->ops, $this->patient, $this->doctorA);

        $ended = $this->svc()->end($this->ops, $a, 'Left the practice');

        $this->assertNotNull($ended->ended_at);
        $this->assertSame($this->ops->id, $ended->ended_by);
        $this->assertSame('Left the practice', $ended->note);

        $this->expectException(ValidationException::class);
        $this->svc()->end($this->ops, $ended);
    }

    public function test_every_assignment_and_end_is_in_the_audit_trail(): void
    {
        $a = $this->svc()->assign($this->ops, $this->patient, $this->doctorA);
        $this->svc()->end($this->ops, $a);

        $events = CrmAuditEvent::where('domain', 'assignment')->orderBy('id')->get();
        $this->assertSame(['staff_assigned', 'staff_unassigned'], $events->pluck('action')->all());
        $this->assertSame((string) $this->patient->id, $events[0]->subject_id);
        $this->assertSame($this->doctorA->id, $events[0]->after['staff_id']);
        $this->assertSame($this->ops->id, $events[1]->actor_id);
    }

    public function test_only_operations_can_assign_or_end(): void
    {
        $a = $this->svc()->assign($this->ops, $this->patient, $this->doctorA);
        foreach ([UserRole::Doctor, UserRole::HealthCoach, UserRole::Patient] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->forbidden(fn () => $this->svc()->assign($u, $this->patient, $this->doctorB));
            $this->forbidden(fn () => $this->svc()->end($u, $a));
        }
        $this->assertNull($a->fresh()->ended_at);
    }

    public function test_an_assigned_doctor_gets_access_and_loses_it_when_it_ends(): void
    {
        $this->assertFalse($this->access()->canView($this->doctorA, $this->patient));

        $a = $this->svc()->assign($this->ops, $this->patient, $this->doctorA);
        $this->assertTrue($this->access()->canView($this->doctorA, $this->patient));
        $this->assertFalse($this->access()->canView($this->doctorB, $this->patient), 'Only the person assigned.');

        $this->svc()->end($this->ops, $a);
        $this->assertFalse($this->access()->canView($this->doctorA, $this->patient));
    }

    public function test_an_assigned_coach_gets_coaching_access_and_a_doctor_assignment_does_not_work_for_a_coach(): void
    {
        $this->assertFalse($this->access()->canView($this->coach, $this->patient));
        $this->svc()->assign($this->ops, $this->patient, $this->coach);

        $this->assertTrue($this->access()->canView($this->coach, $this->patient));
        $this->assertTrue($this->svc()->isAssigned($this->coach, $this->patient));
        $this->assertFalse($this->svc()->isAssigned($this->doctorA, $this->patient));
    }

    public function test_a_role_change_does_not_carry_an_old_assignment_over(): void
    {
        $this->svc()->assign($this->ops, $this->patient, $this->doctorA);
        $this->doctorA->forceFill(['role' => UserRole::HealthCoach])->save();

        $this->assertFalse($this->access()->canView($this->doctorA->fresh(), $this->patient), 'A doctor assignment is not a coach assignment.');
        $this->assertSame([], $this->svc()->patientIds($this->doctorA->fresh()));
    }

    public function test_assignment_never_crosses_the_confidential_note_walls(): void
    {
        $this->svc()->assign($this->ops, $this->patient, $this->doctorA);
        $this->svc()->assign($this->ops, $this->patient, $this->coach);
        $notes = app(PatientNoteService::class);

        $clinical = $notes->create($this->doctorA, $this->patient, 'clinical', 'CONFIDENTIAL');
        $summary = $notes->create($this->doctorA, $this->patient, 'patient_summary', 'For the patient');

        $this->assertTrue($notes->canRead($this->doctorA, $clinical));
        $this->assertFalse($notes->canRead($this->coach, $clinical), 'An assigned coach never reads a doctor\'s clinical note.');
        $this->assertFalse($notes->canRead($this->coach, $summary));
        $this->assertFalse($notes->canRead($this->doctorB, $clinical));
        $this->assertSame(['operations'], $notes->typesFor($this->coach, $this->patient), 'A coach can only write staff notes.');
    }

    public function test_an_assigned_doctor_can_use_the_doctor_tools_for_that_patient(): void
    {
        $this->svc()->assign($this->ops, $this->patient, $this->doctorA);
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);

        $follow = app(FollowUpService::class)->request($this->doctorA, $order, 14);
        $test = app(BloodTestService::class)->request($this->doctorA, $this->patient, ['fbc']);

        $this->assertSame($this->doctorA->id, $follow->requested_by);
        $this->assertSame($this->doctorA->id, $test->requested_by);
    }

    public function test_the_doctors_lists_include_assigned_patients_but_not_others(): void
    {
        $other = User::factory()->create(['role' => UserRole::Patient]);
        $this->svc()->assign($this->ops, $this->patient, $this->doctorA);
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);
        app(FollowUpService::class)->request($this->doctorA, $order, 14);

        $this->assertSame([$this->patient->id], $this->access()->doctorPatientIds($this->doctorA));
        $this->assertSame([], $this->access()->doctorPatientIds($this->doctorB));
        $this->assertNotContains($other->id, $this->access()->doctorPatientIds($this->doctorA));

        Livewire::actingAs($this->doctorA)->test(FollowUps::class)->assertSee($order->reference);
        Livewire::actingAs($this->doctorB)->test(FollowUps::class)->assertDontSee($order->reference);
        Livewire::actingAs($this->doctorA)->test(MyPatients::class)->assertSee($this->patient->first_name);
        Livewire::actingAs($this->doctorB)->test(MyPatients::class)->assertDontSee($this->patient->first_name);
    }

    public function test_the_doctor_ids_do_not_list_a_patient_twice_when_both_routes_apply(): void
    {
        \App\Models\Appointment::create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctorA->id, 'type' => 'general', 'status' => 'completed', 'appointment_date' => '2026-11-10', 'start_time' => '09:00', 'end_time' => '09:30', 'fee_amount' => 1]);
        $this->svc()->assign($this->ops, $this->patient, $this->doctorA);

        $this->assertSame([$this->patient->id], $this->access()->doctorPatientIds($this->doctorA));
    }

    public function test_the_panel_in_the_order_drawer_assigns_and_ends(): void
    {
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);
        $this->actingAs($this->ops);

        Livewire::test(OrderControlsPanel::class, ['orderId' => $order->id])
            ->assertSeeHtml('data-section="care-team"')->assertSeeHtml('data-empty="care-team"')
            ->call('assignStaff')->assertHasErrors('staff')
            ->set('staffId', (string) $this->doctorA->id)->call('assignStaff')->assertHasNoErrors()
            ->set('staffId', (string) $this->doctorA->id)->call('assignStaff')->assertHasErrors('staff')
            ->set('staffId', (string) $this->coach->id)->call('assignStaff')->assertHasNoErrors()
            ->assertSee('(doctor) since')->assertSee('(health coach) since')
            ->call('endAssignment', StaffAssignment::where('staff_id', $this->doctorA->id)->first()->id)->assertHasNoErrors()
            ->assertSee('ended');

        $this->assertFalse($this->access()->canView($this->doctorA, $this->patient));
        $this->assertTrue($this->access()->canView($this->coach, $this->patient));
    }

    public function test_an_assignment_of_another_patient_cannot_be_ended_from_this_panel(): void
    {
        $other = User::factory()->create(['role' => UserRole::Patient]);
        $theirs = $this->svc()->assign($this->ops, $other, $this->doctorA);
        $order = Order::create(['patient_id' => $this->patient->id, 'status' => 'Delivered', 'total_minor' => 1, 'ordered_at' => now()]);
        $this->actingAs($this->ops);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(OrderControlsPanel::class, ['orderId' => $order->id])->call('endAssignment', $theirs->id);
    }
}
