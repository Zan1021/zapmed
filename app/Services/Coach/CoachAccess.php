<?php

namespace App\Services\Coach;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CoachFlag;
use App\Models\CoachingAssignment;
use App\Models\StaffAssignment;
use App\Models\Subscription;
use App\Models\User;

/**
 * Who may use the Coach App, and who may see a patient's tracking data.
 *
 * Rules (Craig's build guide, sections 2 and 8):
 *  - A patient uses the app when they have an active coaching assignment OR an active subscription on the
 *    coach plan (config coach.plan_slug, the R450/month membership).
 *  - Tracking data is visible to: the patient, their ACTIVELY assigned coach, and a doctor who has an appointment
 *    with that patient. Nobody else. Operations/admin do NOT get patient tracking data here (they see aggregated
 *    oversight metrics elsewhere), and confidential doctor/coach notes are never part of this data.
 */
class CoachAccess
{
    public function patientHasAddon(User $patient): bool
    {
        if ($patient->role !== UserRole::Patient) {
            return false;
        }

        if (CoachingAssignment::active()->where('patient_id', $patient->id)->exists()) {
            return true;
        }

        return Subscription::query()
            ->where('user_id', $patient->id)
            ->active()
            ->whereHas('plan', fn ($q) => $q->where('slug', config('coach.plan_slug')))
            ->exists();
    }

    public function canView(User $viewer, User $patient): bool
    {
        if ($viewer->is($patient)) {
            return $patient->role === UserRole::Patient;
        }

        // A person Operations assigned (several doctors or coaches per patient, with start and end) has access while the
        // assignment is active, on top of the usual routes: a coach's own coaching assignment, a doctor's appointment.
        $assigned = in_array($viewer->role, [UserRole::Doctor, UserRole::HealthCoach], true)
            && StaffAssignment::active()->where('patient_id', $patient->id)->where('staff_id', $viewer->id)->where('role', $viewer->role->value)->exists();

        return $assigned || match ($viewer->role) {
            UserRole::HealthCoach => CoachingAssignment::active()
                ->where('patient_id', $patient->id)
                ->where('coach_id', $viewer->id)
                ->exists(),
            UserRole::Doctor => Appointment::query()
                ->where('patient_id', $patient->id)
                ->where('doctor_id', $viewer->id)
                ->exists(),
            default => false,
        };
    }

    /**
     * Who may see and close a safety flag: anyone who may view that patient's data, plus the doctors named in
     * COACH_FLAG_NOTIFY (e.g. Dr Anke), who triage flags for patients they have not treated. Admin does not.
     */
    public function canHandleFlag(User $viewer, CoachFlag $flag): bool
    {
        // A member can never triage (or close) their own flag, even though they can see their own data.
        if ($flag->patient && ! $viewer->is($flag->patient) && $this->canView($viewer, $flag->patient)) {
            return true;
        }

        return $viewer->role === UserRole::Doctor
            && in_array($viewer->email, config('coach.flags.notify_emails', []), true);
    }

    /**
     * Every patient this doctor looks after: the ones they have an appointment with and the ones Operations assigned to them.
     * Used to fill the doctor's lists.
     *
     * @return int[]
     */
    public function doctorPatientIds(User $doctor): array
    {
        return Appointment::where('doctor_id', $doctor->id)->pluck('patient_id')
            ->merge(StaffAssignment::active()->where('staff_id', $doctor->id)->where('role', 'doctor')->pluck('patient_id'))
            ->unique()->values()->all();
    }

    public function assignedCoachId(User $patient): ?int
    {
        return CoachingAssignment::active()->where('patient_id', $patient->id)->value('coach_id');
    }
}
