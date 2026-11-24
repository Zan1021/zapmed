<?php

namespace App\Services\Coach;

use App\Models\CoachProfile;
use App\Models\User;
use InvalidArgumentException;

/**
 * Saves the member's profile choices (design screen 22): goal duration, height, health concerns, diet,
 * allergies, avoids, activity level, the cycle switch and units. Every value must be a known key from
 * config('coach.profile'), so nothing free-typed reaches the database.
 */
class ProfileSettings
{
    public function __construct(private CoachOnboarding $onboarding, private WeightLogger $weight)
    {
    }

    public function profile(User $user): CoachProfile
    {
        return $this->onboarding->profileFor($user);
    }

    public function setGoal(User $user, float $kg): CoachProfile
    {
        return $this->weight->setGoal($user, $kg);
    }

    public function setTargetMonths(User $user, int $months): void
    {
        if (! in_array($months, config('coach.profile.target_months', []), true)) {
            throw new InvalidArgumentException('Please pick one of the listed durations.');
        }

        $this->profile($user)->update(['target_months' => $months]);
    }

    public function setHeight(User $user, int $cm): void
    {
        [$min, $max] = config('coach.profile.height_cm', [100, 230]);

        if ($cm < $min || $cm > $max) {
            throw new InvalidArgumentException("Please enter a height between {$min} and {$max} cm.");
        }

        $this->profile($user)->update(['height_cm' => $cm]);
    }

    public function setDiet(User $user, string $diet): void
    {
        $this->assertKnown('diets', $diet);
        $this->profile($user)->update(['diet' => $diet]);
        app(MealPlanner::class)->afterProfileChange($user);
    }

    /**
     * Save diet, allergies and avoids together (the Personalise plan screen). Unlike the single setters this does not
     * touch the meal plan: the caller rebuilds it once afterwards.
     *
     * @param  array<int,string>  $allergies
     * @param  array<int,string>  $avoids
     */
    public function setDietary(User $user, string $diet, array $allergies, array $avoids): void
    {
        $this->assertKnown('diets', $diet);
        foreach ($allergies as $key) {
            $this->assertKnown('allergies', (string) $key);
        }
        foreach ($avoids as $key) {
            $this->assertKnown('avoids', (string) $key);
        }

        $this->profile($user)->update([
            'diet' => $diet,
            'allergies' => array_values(array_unique($allergies)),
            'avoids' => array_values(array_unique($avoids)),
        ]);
    }
    public function setActivity(User $user, string $level): void
    {
        $this->assertKnown('activity_levels', $level);
        $this->profile($user)->update(['activity_level' => $level]);
    }

    /** Add the key to the list when absent, remove it when present. $field: health_concerns | allergies | avoids. */
    public function toggleInList(User $user, string $field, string $key): void
    {
        $options = ['health_concerns' => 'health_concerns', 'allergies' => 'allergies', 'avoids' => 'avoids'];
        if (! isset($options[$field])) {
            throw new InvalidArgumentException('Unknown profile list.');
        }
        $this->assertKnown($options[$field], $key);

        $profile = $this->profile($user);
        $current = (array) ($profile->{$field} ?? []);
        $current = in_array($key, $current, true)
            ? array_values(array_diff($current, [$key]))
            : array_values(array_unique(array_merge($current, [$key])));

        $profile->update([$field => $current]);

        // Diet-related lists change what the meal plan may contain: ban meals at once.
        if (in_array($field, ['allergies', 'avoids'], true)) {
            app(MealPlanner::class)->afterProfileChange($user);
        }
    }

    public function setTrackCycle(User $user, bool $on): void
    {
        // Men never get the cycle tracker, whatever was sent.
        $on = $on && $this->onboarding->showsCycleSwitch($user);
        $this->profile($user)->update(['track_cycle' => $on]);
    }

    public function setUnits(User $user, string $unit): void
    {
        $this->weight->setUnits($user, $unit);
    }

    private function assertKnown(string $list, string $key): void
    {
        if (! array_key_exists($key, config("coach.profile.{$list}", []))) {
            throw new InvalidArgumentException('That choice is not available.');
        }
    }
}
