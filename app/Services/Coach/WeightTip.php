<?php

namespace App\Services\Coach;

/**
 * The tip box under the weight chart (build guide 5.3), in this order:
 *  1. luteal phase (when the member tracks her cycle)  2. under 0.4 kg change in 7 days = plateau
 *  3. otherwise how much was lost (or gained) this week.
 * Wording is provisional (config coach.weight.tips) until Amy / Dr Anke review it.
 */
class WeightTip
{
    public static function for(WeightStats $stats, ?string $cyclePhase, ?string $units = 'kg'): string
    {
        $tips = config('coach.weight.tips');

        if ($cyclePhase === 'luteal') {
            return $tips['luteal'];
        }

        if ($stats->weekLossKg === null) {
            return $tips['not_enough'];
        }

        $threshold = (float) config('coach.weight.plateau_kg_7d', 0.4);

        if (abs($stats->weekLossKg) < $threshold) {
            return $tips['plateau'];
        }

        $amount = number_format(CoachUnits::fromKg(abs($stats->weekLossKg), $units), 1).' '.CoachUnits::normalise($units);

        return str_replace('{kg}', $amount, $stats->weekLossKg > 0 ? $tips['lost'] : $tips['gained']);
    }
}