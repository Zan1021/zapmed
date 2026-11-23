<?php

namespace App\Services\Coach;

use App\Models\CoachProfile;
use Carbon\CarbonInterface;

/**
 * Cycle day, phase and days to the next period (build guide 5.9). All of it is an ESTIMATE.
 *
 *  Menstrual: day 1 to the period length.
 *  Follicular: after the period until 14 days before the cycle end.
 *  Ovulation: from 14 days to 12 days before the cycle end.
 *  Luteal: the rest.
 * "N days before the cycle end" = cycleLength - cycleDay.
 */
class CycleCalendar
{
    public static function enabled(?CoachProfile $profile): bool
    {
        return (bool) $profile?->track_cycle;
    }

    /** @return null|array{day:int,phase:string,label:string,days_to_period:int,arrows:array<string,string>} */
    public static function status(?CoachProfile $profile, ?CarbonInterface $on = null): ?array
    {
        if (! self::enabled($profile) || ! $profile->last_period_start) {
            return null;
        }

        $on ??= CoachClock::today();
        $length = max(1, (int) $profile->cycle_length);
        $since = CoachClock::daysBetween($profile->last_period_start, $on);
        $day = (($since % $length) + $length) % $length + 1;
        $phase = self::phaseFor($day, $length, (int) $profile->period_length);

        return [
            'day' => $day,
            'phase' => $phase,
            'label' => config("coach.cycle.phase_labels.{$phase}"),
            'days_to_period' => $length - $day + 1,
            'arrows' => config("coach.cycle.phase_arrows.{$phase}", []),
        ];
    }

    public static function phaseFor(int $day, int $length, int $period): string
    {
        if ($day <= $period) {
            return 'menstrual';
        }

        $before = $length - $day;
        $ovStart = (int) config('coach.cycle.ovulation_start_days_before_end', 14);
        $ovEnd = (int) config('coach.cycle.ovulation_end_days_before_end', 12);

        if ($before > $ovStart) {
            return 'follicular';
        }

        return $before >= $ovEnd ? 'ovulation' : 'luteal';
    }
}