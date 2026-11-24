<?php

namespace App\Services\Coach;

use App\Enums\UserRole;
use App\Models\CoachingAssignment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gives a member with the coach add-on a coach (a registered dietitian with the health_coach role).
 *
 * Rule: the active coach with the fewest members, lowest id on a tie, skipping anyone at the cap
 * (config coach.assignment.max_members, 0 = no cap). One active assignment per member (database rule),
 * so calling this twice is safe. When nobody has room the member waits and assignPending() retries.
 */
class CoachAssigner
{
    public function assign(User $patient, ?User $by = null, string $reason = 'Auto-assigned when the coach add-on started'): ?CoachingAssignment
    {
        if ($patient->role !== UserRole::Patient) {
            return null;
        }

        $existing = CoachingAssignment::active()->where('patient_id', $patient->id)->first();
        if ($existing) {
            return $existing;
        }

        $coach = $this->pickCoach();
        if (! $coach) {
            Log::warning('coach assign: no coach has room, member is waiting', ['patient' => $patient->id]);

            return null;
        }

        try {
            return DB::transaction(fn () => CoachingAssignment::create([
                'patient_id' => $patient->id,
                'coach_id' => $coach->id,
                'reason' => $reason,
                'started_at' => now(),
                'created_by' => $by?->id,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Another request assigned this member a moment ago.
            return CoachingAssignment::active()->where('patient_id', $patient->id)->first();
        }
    }

    public function pickCoach(): ?User
    {
        $cap = (int) config('coach.assignment.max_members', 0);

        $load = CoachingAssignment::active()
            ->selectRaw('coach_id, count(*) as members')
            ->groupBy('coach_id')
            ->pluck('members', 'coach_id');

        return User::active()
            ->where('role', UserRole::HealthCoach->value)
            ->orderBy('id')
            ->get()
            ->map(fn (User $c) => ['coach' => $c, 'load' => (int) ($load[$c->id] ?? 0)])
            ->filter(fn (array $row) => $cap === 0 || $row['load'] < $cap)
            ->sortBy('load')      // sortBy is stable, so the lowest id wins a tie
            ->first()['coach'] ?? null;
    }

    /**
     * Members who paid for the add-on but have no coach yet (nobody had room earlier, or the coach left).
     *
     * @return int how many members were given a coach
     */
    public function assignPending(): int
    {
        $assigned = 0;

        $userIds = Subscription::query()
            ->active()
            ->whereHas('plan', fn ($q) => $q->where('slug', config('coach.plan_slug')))
            ->pluck('user_id')
            ->unique();

        foreach (User::whereIn('id', $userIds)->where('role', UserRole::Patient->value)->get() as $patient) {
            if (CoachingAssignment::active()->where('patient_id', $patient->id)->exists()) {
                continue;
            }
            if ($this->assign($patient)) {
                $assigned++;
            }
        }

        return $assigned;
    }
}
