<?php

namespace App\Services\Coach;

use App\Models\DoseEntry;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * ILLUSTRATIVE medication level curve (build guide 5.4). The sum of decaying doses, shown as a share of the
 * peak in the chart window. It is NOT a blood level and the screens must say so. It never advises a dose change.
 *
 * Time axis: days relative to now (0 = now). Past doses are the patient's logged doses. Future doses are projected
 * on schedule (last dose + n x interval) so the dashed line looks ahead 7 days.
 */
class MedicationLevel
{
    public function __construct(
        public readonly ?CarbonImmutable $lastDoseAt,
        public readonly ?string $lastSite,
        public readonly ?CarbonImmutable $nextDoseDate,
        public readonly ?int $daysToNext,
        public readonly ?int $levelNowPercent,
        /** @var array<int,array{t:float,level:float,future:bool}> level 0..1 of peak, t in days from now */
        public readonly array $curve,
    ) {
    }

    public static function forUser(User $user): self
    {
        $tz = config('coach.timezone');
        $now = CarbonImmutable::now($tz);
        $interval = (int) config('coach.medication.dose_interval_days', 7);

        $doses = DoseEntry::where('user_id', $user->id)->orderBy('taken_at')->get();
        $last = $doses->last();

        if (! $last) {
            return new self(null, null, null, null, null, []);
        }

        $lastAt = CarbonImmutable::parse($last->taken_at)->setTimezone($tz);
        $next = $lastAt->startOfDay()->addDays($interval);

        // Dose times as days relative to now (past = logged, future = projected on schedule).
        $times = $doses->map(fn ($d) => self::days($now, CarbonImmutable::parse($d->taken_at)))->all();
        $lastT = self::days($now, $lastAt);
        $pastWindow = (int) config('coach.medication.chart_past_days', 14);
        $futureWindow = (int) config('coach.medication.chart_future_days', 7);
        for ($t = $lastT + $interval; $t <= $futureWindow + $interval; $t += $interval) {
            if ($t > 0) {
                $times[] = $t;
            }
        }

        $half = (float) config('coach.medication.half_life_days', 5);
        $level = function (float $t) use ($times, $half): float {
            $sum = 0.0;
            foreach ($times as $d) {
                if ($d <= $t) {
                    $sum += exp(-($t - $d) * M_LN2 / $half);
                }
            }

            return $sum;
        };

        $points = [];
        for ($t = -$pastWindow; $t <= $futureWindow + 1e-9; $t += 0.5) {
            $points[] = ['t' => $t, 'raw' => $level($t)];
        }
        $peak = max(array_column($points, 'raw')) ?: 1.0;
        $curve = array_map(fn ($p) => ['t' => $p['t'], 'level' => round($p['raw'] / $peak, 4), 'future' => $p['t'] > 0], $points);

        $nowPercent = (int) round($level(0.0) / $peak * 100);

        return new self($lastAt, $last->site, $next, CoachClock::daysBetween($now, $next), $nowPercent, $curve);
    }

    private static function days(CarbonImmutable $now, CarbonImmutable $at): float
    {
        return ($at->getTimestamp() - $now->getTimestamp()) / 86400;
    }
}