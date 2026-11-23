<?php

namespace App\Services\Coach;

use App\Models\CoachProfile;
use App\Models\ReminderSetting;
use App\Models\User;
use InvalidArgumentException;

/**
 * Coach App onboarding (design screens 1 and 2): the worry question, the starting plan and the cycle switch.
 *
 * Cycle tracker default (build guide open decision 6): ON only when the patient's gender is female,
 * OFF for men and for anyone who has not said. The switch is hidden for men.
 */
class CoachOnboarding
{
    public function profileFor(User $user): CoachProfile
    {
        return CoachProfile::firstOrCreate(['user_id' => $user->id], [
            'height_cm' => $user->patientProfile?->height_cm ? (int) round((float) $user->patientProfile->height_cm) : null,
            'start_weight_kg' => $user->patientProfile?->weight_kg,
            'track_cycle' => $this->defaultTrackCycle($user),
        ]);
    }

    public function defaultTrackCycle(User $user): bool
    {
        return $this->gender($user) === 'female';
    }

    public function showsCycleSwitch(User $user): bool
    {
        return $this->gender($user) !== 'male';
    }

    public function isOnboarded(User $user): bool
    {
        return CoachProfile::where('user_id', $user->id)->whereNotNull('onboarded_at')->exists();
    }

    /** @return array<string,array{label:string,reply:string}> */
    public function worries(): array
    {
        return config('coach.onboarding.worries', []);
    }

    public function complete(User $user, string $worry, bool $trackCycle): CoachProfile
    {
        if (! array_key_exists($worry, $this->worries())) {
            throw new InvalidArgumentException('Unknown worry choice.');
        }

        $profile = $this->profileFor($user);
        $profile->update([
            'worry' => $worry,
            // Men never get the cycle tracker, whatever was posted.
            'track_cycle' => $this->showsCycleSwitch($user) ? $trackCycle : false,
            'onboarded_at' => $profile->onboarded_at ?? now(),
        ]);

        $this->seedReminders($user);

        return $profile->refresh();
    }

    /** Create the default reminder rows once (patients can change them later). Cycle reminder only when tracking. */
    private function seedReminders(User $user): void
    {
        foreach (config('coach.reminders', []) as $type => $defaults) {
            $enabled = (bool) ($defaults['enabled'] ?? true);
            unset($defaults['enabled']);

            ReminderSetting::firstOrCreate(
                ['user_id' => $user->id, 'type' => $type],
                ['enabled' => $enabled, 'settings' => $defaults]
            );
        }
    }

    private function gender(User $user): ?string
    {
        $g = strtolower(trim((string) $user->gender));

        return $g === '' ? null : $g;
    }
}
