<?php

namespace App\Services\Coach;

use App\Models\DoseEntry;
use App\Models\User;
use InvalidArgumentException;

/**
 * Writes dose entries (build guide 5.4): one of six sites, pain 0 to 10. Saving sets "last dose" to now, and the
 * next dose follows from the interval (MedicationLevel). One dose per local day: saving again today corrects it.
 */
class DoseLogger
{
    /** @return string[] */
    public function sites(): array
    {
        return config('coach.medication.sites', []);
    }

    public function log(User $user, string $site, int $pain): DoseEntry
    {
        if (! in_array($site, $this->sites(), true)) {
            throw new InvalidArgumentException('Please choose where you injected.');
        }

        $max = (int) config('coach.medication.pain_max', 10);
        if ($pain < 0 || $pain > $max) {
            throw new InvalidArgumentException("Pain must be between 0 and {$max}.");
        }

        [$from, $to] = CoachClock::todayRangeUtc();
        $today = DoseEntry::where('user_id', $user->id)->where('taken_at', '>=', $from)->where('taken_at', '<', $to)->latest('taken_at')->first();

        if ($today) {
            $today->update(['site' => $site, 'pain' => $pain]);

            return $today;
        }

        return DoseEntry::create(['user_id' => $user->id, 'taken_at' => now(), 'site' => $site, 'pain' => $pain]);
    }

    /** The next site in rotation after the last logged dose (list order, wrapping). Null before the first dose. */
    public function nextSite(User $user): ?string
    {
        $last = DoseEntry::where('user_id', $user->id)->orderByDesc('taken_at')->value('site');
        $sites = $this->sites();
        $i = array_search($last, $sites, true);

        return $i === false ? null : $sites[($i + 1) % count($sites)];
    }
}