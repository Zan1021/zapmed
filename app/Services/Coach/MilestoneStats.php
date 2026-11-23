<?php

namespace App\Services\Coach;

use App\Models\CoachCall;
use App\Models\CoachProfile;
use App\Models\DoseEntry;
use App\Models\SideEffectEntry;
use App\Models\User;
use App\Models\WaterEntry;
use App\Models\WeightEntry;

/**
 * Numbers for the milestone screen (design screen 25, build guide 5.16):
 *  - percent of STARTING weight lost
 *  - kg lost
 *  - days logged (distinct days with any weight, dose, side effect or water entry)
 *  - coach check-ins (calls that were completed, or booked and already in the past)
 */
class MilestoneStats
{
    public function __construct(
        public readonly ?float $percentLost,
        public readonly ?float $kgLost,
        public readonly int $daysLogged,
        public readonly int $checkIns,
    ) {
    }

    public function hasProgress(): bool
    {
        return $this->kgLost !== null && $this->kgLost > 0;
    }

    public static function forUser(User $user): self
    {
        $weight = WeightStats::forUser($user);
        $lost = ($weight->start !== null && $weight->current !== null) ? round($weight->start - $weight->current, 1) : null;
        $percent = ($lost !== null && $weight->start > 0) ? round($lost / $weight->start * 100, 1) : null;

        return new self(
            $percent !== null ? max(0, $percent) : null,
            $lost !== null ? max(0, $lost) : null,
            self::daysLogged($user),
            self::checkIns($user),
        );
    }

    private static function daysLogged(User $user): int
    {
        $days = collect();

        $days = $days->merge(WeightEntry::where('user_id', $user->id)->pluck('logged_on'));
        $days = $days->merge(SideEffectEntry::where('user_id', $user->id)->pluck('logged_on'));

        // Timestamps are stored in UTC; a "day" is a South African day.
        $tz = config('coach.timezone');
        $days = $days->merge(DoseEntry::where('user_id', $user->id)->pluck('taken_at')->map(fn ($t) => $t->timezone($tz)));
        $days = $days->merge(WaterEntry::where('user_id', $user->id)->pluck('logged_at')->map(fn ($t) => $t->timezone($tz)));

        return $days->map(fn ($d) => $d->format('Y-m-d'))->unique()->count();
    }

    private static function checkIns(User $user): int
    {
        return CoachCall::where('patient_id', $user->id)
            ->where(fn ($q) => $q->where('status', CoachCall::COMPLETED)
                ->orWhere(fn ($q) => $q->where('status', CoachCall::BOOKED)->where('starts_at', '<', now())))
            ->count();
    }

    public static function profile(User $user): ?CoachProfile
    {
        return CoachProfile::where('user_id', $user->id)->first();
    }
}
