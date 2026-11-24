<?php

namespace App\Services\Coach;

use App\Models\ActivityEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Activity (build guide 5.8). "Today" is the South African day.
 *
 * Workout calories = MET x weight (kg) x hours, using the latest logged weight (config default when none).
 * Steps are typed in once a day (setting it again corrects it); calories = steps x kcal_per_step.
 */
class ActivityLogger
{
    /** @return array<string,array{name:string,group:string,met:float,emoji:string}> */
    public function workouts(): array
    {
        return config('coach.activity.workouts', []);
    }

    public function kcalFor(float $met, float $weightKg, int $minutes): int
    {
        return (int) round($met * $weightKg * ($minutes / 60));
    }

    /** The weight used for estimates: latest logged weight, else the configured default. */
    public function weightFor(User $user): float
    {
        return WeightStats::forUser($user)->current ?? (float) config('coach.activity.default_weight_kg', 75);
    }

    public function logWorkout(User $user, string $key, int $minutes): ActivityEntry
    {
        $workout = $this->workouts()[$key] ?? null;
        if (! $workout) {
            throw new InvalidArgumentException('Pick a workout from the list.');
        }

        $min = (int) config('coach.activity.minutes.min', 5);
        $max = (int) config('coach.activity.minutes.max', 300);
        if ($minutes < $min || $minutes > $max) {
            throw new InvalidArgumentException("Enter between {$min} and {$max} minutes.");
        }

        $weight = $this->weightFor($user);

        return ActivityEntry::create([
            'user_id' => $user->id, 'logged_at' => now(), 'kind' => 'workout', 'workout_key' => $key, 'name' => $workout['name'],
            'minutes' => $minutes, 'kcal' => $this->kcalFor((float) $workout['met'], $weight, $minutes),
            'met' => $workout['met'], 'weight_kg' => round($weight, 1),
        ]);
    }

    /** Set today's steps (one entry a day; typing a new number corrects it). */
    public function setSteps(User $user, int $steps): ActivityEntry
    {
        $max = (int) config('coach.activity.steps_max', 100000);
        if ($steps < 0 || $steps > $max) {
            throw new InvalidArgumentException('Enter a number of steps between 0 and '.number_format($max).'.');
        }

        $kcal = (int) round($steps * (float) config('coach.activity.kcal_per_step', 0.034));
        $existing = $this->dayQuery($user, CoachClock::today())->where('kind', 'steps')->first();

        if ($existing) {
            $existing->update(['steps' => $steps, 'kcal' => $kcal, 'logged_at' => now()]);

            return $existing;
        }

        return ActivityEntry::create([
            'user_id' => $user->id, 'logged_at' => now(), 'kind' => 'steps', 'name' => 'Steps', 'steps' => $steps, 'kcal' => $kcal,
        ]);
    }

    /** Delete one of the member's own entries (a logged workout, or the steps). */
    public function remove(User $user, int $id): bool
    {
        return (bool) ActivityEntry::where('user_id', $user->id)->find($id)?->delete();
    }

    /** @return Collection<int,ActivityEntry> today's entries, newest first */
    public function todayEntries(User $user): Collection
    {
        return $this->dayQuery($user, CoachClock::today())->latest('logged_at')->latest('id')->get();
    }

    /** @return array{kcal:int,steps:int,minutes:int,workouts:int} */
    public function todayTotals(User $user): array
    {
        return $this->totalsFor($this->todayEntries($user));
    }

    /**
     * Calories burned for each of the last 7 local days, oldest first (today last).
     *
     * @return array<int,array{date:string,label:string,kcal:int,today:bool}>
     */
    public function lastSevenDays(User $user): array
    {
        $today = CoachClock::today();
        $out = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = $today->subDays($i);
            $out[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('D'),
                'kcal' => (int) $this->dayQuery($user, $day)->sum('kcal'),
                'today' => $i === 0,
            ];
        }

        return $out;
    }

    /** @param Collection<int,ActivityEntry> $entries */
    private function totalsFor(Collection $entries): array
    {
        return [
            'kcal' => (int) $entries->sum('kcal'),
            'steps' => (int) $entries->where('kind', 'steps')->sum('steps'),
            'minutes' => (int) $entries->where('kind', 'workout')->sum('minutes'),
            'workouts' => $entries->where('kind', 'workout')->count(),
        ];
    }

    private function dayQuery(User $user, \Carbon\CarbonInterface $day)
    {
        $start = $day->startOfDay()->utc();

        return ActivityEntry::where('user_id', $user->id)->where('logged_at', '>=', $start)->where('logged_at', '<', $start->addDay());
    }
}
