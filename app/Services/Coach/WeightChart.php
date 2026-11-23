<?php

namespace App\Services\Coach;

use App\Models\CoachProfile;
use App\Models\User;
use App\Models\WeightEntry;

/**
 * Chart geometry for the weight screen: a line over the last 14 / 30 / 90 days, a goal line, and (when the member
 * tracks her cycle) shading for luteal-phase days. X is by calendar day, so gaps between weigh-ins show as gaps.
 */
class WeightChart
{
    public const W = 330;
    public const H = 150;
    public const PAD = 18;

    /**
     * @return array{days:int,points:array<int,array{x:float,y:float,date:string,kg:float}>,line:?string,
     *               goalY:?float,luteal:array<int,array{x:float,w:float}>,hasData:bool}
     */
    public static function build(User $user, int $days, ?CoachProfile $profile = null): array
    {
        $allowed = config('coach.weight.ranges_days', [14, 30, 90]);
        $days = in_array($days, $allowed, true) ? $days : 30;

        $profile ??= CoachProfile::where('user_id', $user->id)->first();
        $today = CoachClock::today();
        $startDate = $today->subDays($days - 1);

        $entries = WeightEntry::where('user_id', $user->id)
            ->whereDate('logged_on', '>=', $startDate->toDateString())
            ->orderBy('logged_on')->get();

        $goal = $profile?->goal_weight_kg !== null ? (float) $profile->goal_weight_kg : null;
        $values = $entries->map(fn ($e) => (float) $e->weight_kg)->all();
        if ($goal !== null) {
            $values[] = $goal;
        }

        $step = $days > 1 ? (self::W - self::PAD * 2) / ($days - 1) : 0;
        $x = fn (string $date) => self::PAD + CoachClock::daysBetween($startDate, \Carbon\CarbonImmutable::parse($date)) * $step;

        if (! $values) {
            return ['days' => $days, 'points' => [], 'line' => null, 'goalY' => null, 'luteal' => [], 'hasData' => false];
        }

        $min = min($values) - 1;
        $max = max($values) + 1;
        $y = fn (float $kg) => 10 + ($max - $kg) / ($max - $min) * (self::H - 20);

        $points = $entries->map(fn ($e) => [
            'x' => round($x($e->logged_on->toDateString()), 1),
            'y' => round($y((float) $e->weight_kg), 1),
            'date' => $e->logged_on->toDateString(),
            'kg' => (float) $e->weight_kg,
        ])->values()->all();

        $luteal = [];
        if (CycleCalendar::enabled($profile) && $profile->last_period_start) {
            for ($i = 0; $i < $days; $i++) {
                $d = $startDate->addDays($i);
                if ((CycleCalendar::status($profile, $d)['phase'] ?? null) === 'luteal') {
                    $luteal[] = ['x' => round(self::PAD + $i * $step - $step / 2, 1), 'w' => round($step, 1)];
                }
            }
        }

        return [
            'days' => $days,
            'points' => $points,
            'line' => count($points) > 1 ? collect($points)->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') : null,
            'goalY' => $goal !== null ? round($y($goal), 1) : null,
            'luteal' => $luteal,
            'hasData' => true,
        ];
    }
}