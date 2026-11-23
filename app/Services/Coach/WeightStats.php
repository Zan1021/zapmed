<?php

namespace App\Services\Coach;

use App\Models\CoachProfile;
use App\Models\User;
use App\Models\WeightEntry;
use Carbon\CarbonImmutable;

/**
 * Weight numbers for the Track home and the weight screen (build guide section 6).
 *
 *  - percent to goal = (start - now) / (start - goal)
 *  - predicted goal date = latest date + (now - goal) / average daily loss over the last 90 days
 *  - BMI = kg / height(m) squared. A general guide only, never a diagnosis.
 * Everything returns null when it cannot be worked out honestly (no goal, no loss, no height).
 */
class WeightStats
{
    public function __construct(
        public readonly ?float $current,
        public readonly ?float $start,
        public readonly ?float $goal,
        public readonly ?int $percentToGoal,
        public readonly ?CarbonImmutable $predictedGoalDate,
        public readonly ?float $bmi,
        public readonly ?CarbonImmutable $lastLoggedOn,
        /** @var array<int,array{date:string,kg:float}> oldest first */
        public readonly array $series,
        /** Kg lost over the last 7 days (positive = lost). Null when there is no weigh-in 7+ days before the latest. */
        public readonly ?float $weekLossKg = null,
    ) {
    }

    public static function forUser(User $user, int $seriesDays = 30): self
    {
        $profile = CoachProfile::where('user_id', $user->id)->first();
        $entries = WeightEntry::where('user_id', $user->id)->orderBy('logged_on')->get();

        $latest = $entries->last();
        $current = $latest ? (float) $latest->weight_kg : null;
        $start = $profile?->start_weight_kg !== null
            ? (float) $profile->start_weight_kg
            : ($entries->first() ? (float) $entries->first()->weight_kg : null);
        $goal = $profile?->goal_weight_kg !== null ? (float) $profile->goal_weight_kg : null;

        $lastDate = $latest ? CarbonImmutable::parse($latest->logged_on->toDateString()) : null;

        $series = $entries
            ->filter(fn ($e) => $lastDate && $e->logged_on->toDateString() >= $lastDate->subDays($seriesDays - 1)->toDateString())
            ->map(fn ($e) => ['date' => $e->logged_on->toDateString(), 'kg' => (float) $e->weight_kg])
            ->values()->all();

        return new self(
            $current,
            $start,
            $goal,
            self::percent($start, $current, $goal),
            self::predict($entries, $current, $goal, $lastDate),
            self::bmi($current, $profile?->height_cm),
            $lastDate,
            $series,
            self::weekLoss($entries, $current, $lastDate),
        );
    }

    /** Weight 7+ days before the latest weigh-in minus the latest (positive = lost). */
    private static function weekLoss($entries, ?float $current, ?CarbonImmutable $lastDate): ?float
    {
        if ($current === null || $lastDate === null) {
            return null;
        }

        $cut = $lastDate->subDays(7)->toDateString();
        $ref = $entries->filter(fn ($e) => $e->logged_on->toDateString() <= $cut)->last();

        return $ref ? round((float) $ref->weight_kg - $current, 1) : null;
    }

    public static function percent(?float $start, ?float $current, ?float $goal): ?int
    {
        if ($start === null || $current === null || $goal === null || $start <= $goal) {
            return null;
        }

        return (int) max(0, min(100, round(($start - $current) / ($start - $goal) * 100)));
    }

    public static function bmi(?float $kg, $heightCm): ?float
    {
        if ($kg === null || ! $heightCm) {
            return null;
        }
        $m = $heightCm / 100;

        return round($kg / ($m * $m), 1);
    }

    private static function predict($entries, ?float $current, ?float $goal, ?CarbonImmutable $lastDate): ?CarbonImmutable
    {
        if ($current === null || $goal === null || $lastDate === null || $current <= $goal) {
            return null;
        }

        $window = $entries->filter(fn ($e) => $e->logged_on->toDateString() >= $lastDate->subDays((int) config('coach.weight.prediction_days', 90))->toDateString());
        if ($window->count() < 2) {
            return null;
        }

        $first = $window->first();
        $days = CoachClock::daysBetween($first->logged_on, $lastDate);
        $loss = (float) $first->weight_kg - $current;

        if ($days <= 0 || $loss <= 0) {
            return null; // no loss: show a dash
        }

        return $lastDate->addDays((int) ceil(($current - $goal) / ($loss / $days)));
    }
}