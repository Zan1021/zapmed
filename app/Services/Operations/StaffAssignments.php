<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Services\Compliance\AuditTrail;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Several doctors and health coaches per patient, with start and end and who did it (Craig's guide 8.1). Operations only.
 *
 *  - Only an active doctor or health coach can be assigned, to a patient. The same person cannot be assigned twice at once.
 *  - Ending an assignment keeps the row. Access stops at once.
 *  - Every assignment and every end is written to the tamper-evident audit trail.
 *  - An assignment never lets a coach read a doctor's confidential notes (or the reverse): that is decided by role elsewhere.
 */
class StaffAssignments
{
    public function __construct(private readonly AuditTrail $audit)
    {
    }

    private function assertOps(User $user): void
    {
        abort_unless($user->role === UserRole::Admin, 403);
    }

    public function assign(User $ops, User $patient, User $staff, ?string $note = null): StaffAssignment
    {
        $this->assertOps($ops);

        if ($patient->role !== UserRole::Patient) {
            throw ValidationException::withMessages(['patient' => 'Only a patient can be given a care team.']);
        }
        if (! in_array($staff->role, [UserRole::Doctor, UserRole::HealthCoach], true)) {
            throw ValidationException::withMessages(['staff' => 'Choose a doctor or a health coach.']);
        }
        if (! $staff->is_active) {
            throw ValidationException::withMessages(['staff' => 'That account is switched off.']);
        }
        if (StaffAssignment::active()->where('patient_id', $patient->id)->where('staff_id', $staff->id)->exists()) {
            throw ValidationException::withMessages(['staff' => $staff->name.' is already assigned to this patient.']);
        }

        $row = StaffAssignment::create([
            'patient_id' => $patient->id, 'staff_id' => $staff->id, 'role' => $staff->role->value,
            'started_at' => now(), 'assigned_by' => $ops->id, 'note' => $note ? mb_substr(trim($note), 0, 255) : null,
        ]);

        $this->audit->record('assignment', 'staff_assigned', 'user', (string) $patient->id, null, ['staff_id' => $staff->id, 'role' => $staff->role->value, 'assignment_id' => $row->id], $ops->id);

        return $row;
    }

    public function end(User $ops, StaffAssignment $assignment, ?string $note = null): StaffAssignment
    {
        $this->assertOps($ops);

        if ($assignment->ended_at !== null) {
            throw ValidationException::withMessages(['assignment' => 'This assignment has already ended.']);
        }

        $assignment->update(['ended_at' => now(), 'ended_by' => $ops->id, 'note' => $note ? mb_substr(trim($note), 0, 255) : $assignment->note]);
        $this->audit->record('assignment', 'staff_unassigned', 'user', (string) $assignment->patient_id, ['staff_id' => $assignment->staff_id, 'role' => $assignment->role], ['ended' => true, 'assignment_id' => $assignment->id], $ops->id);

        return $assignment->fresh();
    }

    /** Active and past assignments for one patient, newest first. */
    public function historyFor(User $patient): Collection
    {
        return StaffAssignment::with('staff')->where('patient_id', $patient->id)->orderByDesc('id')->get();
    }

    public function isAssigned(User $staff, User $patient): bool
    {
        return StaffAssignment::active()->where('staff_id', $staff->id)->where('patient_id', $patient->id)->where('role', $staff->role->value)->exists();
    }

    /** @return int[] patients this doctor or coach is assigned to right now */
    public function patientIds(User $staff): array
    {
        return StaffAssignment::active()->where('staff_id', $staff->id)->where('role', $staff->role->value)->pluck('patient_id')->all();
    }

    /** Staff Operations can pick from. */
    public function assignable(): Collection
    {
        return User::whereIn('role', [UserRole::Doctor->value, UserRole::HealthCoach->value])->where('is_active', true)->orderBy('first_name')->get();
    }
}
