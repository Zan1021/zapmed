<?php

namespace App\Services\Coach;

use App\Models\CoachProfile;
use App\Models\User;
use App\Models\WeightEntry;
use InvalidArgumentException;

/**
 * Writes weight and the weight goal, with the rules from the build guide (5.3):
 *  - saving again today replaces today's value (one entry per day)
 *  - women who track their cycle get the phase tag on the entry
 *  - a goal cannot be below 45 kg, nor at or above the current weight
 */
class WeightLogger
{
    public function log(User $user, float $kg, ?string $source = null): WeightEntry
    {
        [$min, $max] = config('coach.weight.valid_kg', [20, 400]);
        $kg = round($kg, 1);

        if ($kg < $min || $kg > $max) {
            throw new InvalidArgumentException("Please enter a weight between {$min} and {$max} kg.");
        }

        $profile = CoachProfile::firstOrCreate(['user_id' => $user->id]);
        $today = CoachClock::today();
        $cycle = CycleCalendar::status($profile, $today);

        // whereDate (not an equality on the raw string): the date cast can store 'Y-m-d 00:00:00' on some drivers.
        $entry = WeightEntry::where('user_id', $user->id)->whereDate('logged_on', $today->toDateString())->first();
        $values = ['weight_kg' => $kg, 'cycle_phase' => $cycle['phase'] ?? null];

        if ($entry) {
            $entry->update($values);
        } else {
            $entry = WeightEntry::create(['user_id' => $user->id, 'logged_on' => $today->toDateString()] + $values);
        }

        // The first weigh-in becomes the start weight when onboarding had none.
        if ($profile->start_weight_kg === null) {
            $profile->update(['start_weight_kg' => $kg]);
        }

        return $entry;
    }

    public function setGoal(User $user, float $goalKg): CoachProfile
    {
        $goalKg = round($goalKg, 1);
        $min = (float) config('coach.weight.min_goal_kg', 45);
        $current = WeightStats::forUser($user)->current;

        if ($current === null) {
            throw new InvalidArgumentException('Log your weight first, then set a goal.');
        }
        if ($goalKg < $min) {
            throw new InvalidArgumentException("A goal cannot be below {$min} kg.");
        }
        if ($goalKg >= $current) {
            throw new InvalidArgumentException('Your goal must be below your current weight.');
        }

        $profile = CoachProfile::firstOrCreate(['user_id' => $user->id]);
        $profile->update(['goal_weight_kg' => $goalKg]);

        return $profile->refresh();
    }

    public function setUnits(User $user, string $unit): void
    {
        CoachProfile::updateOrCreate(['user_id' => $user->id], ['units' => CoachUnits::normalise($unit)]);
    }
}