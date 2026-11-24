<?php

namespace App\Services\Coach;

use App\Models\CoachProfile;
use App\Models\CycleEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Cycle tracker actions (build guide 5.9): when the last period started, cycle and period length, and the daily mood and
 * symptom entry. Everything is an estimate. Nothing here works unless the member switched the tracker on (women only,
 * see ProfileSettings::setTrackCycle); turning it off hides the screen and every cycle item in the app.
 */
class CycleTracker
{
    public function __construct(private readonly CoachOnboarding $onboarding)
    {
    }

    public function profile(User $user): CoachProfile
    {
        return $this->onboarding->profileFor($user);
    }

    public function enabled(User $user): bool
    {
        return CycleCalendar::enabled(CoachProfile::where('user_id', $user->id)->first());
    }

    /** "Period started today": the cycle goes back to day 1. */
    public function startPeriodToday(User $user): void
    {
        $this->assertEnabled($user);
        $this->profile($user)->update(['last_period_start' => CoachClock::today()->toDateString()]);
    }

    /** Set the first day of the last period (not in the future, not more than a year ago). */
    public function setLastPeriodStart(User $user, string $date): void
    {
        $this->assertEnabled($user);

        try {
            $day = CarbonImmutable::createFromFormat('Y-m-d', trim($date), config('coach.timezone'))->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Pick the first day of your last period.');
        }

        if ($day->format('Y-m-d') !== trim($date)) {
            throw new InvalidArgumentException('Pick the first day of your last period.');
        }
        if ($day->greaterThan(CoachClock::today())) {
            throw new InvalidArgumentException('That date is in the future.');
        }
        if ($day->lessThan(CoachClock::today()->subYear())) {
            throw new InvalidArgumentException('That date is more than a year ago. Pick a more recent period.');
        }

        $this->profile($user)->update(['last_period_start' => $day->toDateString()]);
    }

    public function setLengths(User $user, int $cycle, int $period): void
    {
        $this->assertEnabled($user);

        $min = (int) config('coach.cycle.min_length', 21);
        $max = (int) config('coach.cycle.max_length', 35);
        if ($cycle < $min || $cycle > $max) {
            throw new InvalidArgumentException("Cycle length must be between {$min} and {$max} days.");
        }

        $pMin = (int) config('coach.cycle.min_period', 2);
        $pMax = (int) config('coach.cycle.max_period', 10);
        if ($period < $pMin || $period > $pMax) {
            throw new InvalidArgumentException("Period length must be between {$pMin} and {$pMax} days.");
        }

        $this->profile($user)->update(['cycle_length' => $cycle, 'period_length' => $period]);
    }

    public function entryToday(User $user): ?CycleEntry
    {
        return CycleEntry::where('user_id', $user->id)->whereDate('entry_date', CoachClock::today()->toDateString())->first();
    }

    /** Pick a mood (1 to 5) for today. Picking the same mood again clears it. */
    public function setMood(User $user, int $mood): CycleEntry
    {
        $this->assertEnabled($user);
        if (! array_key_exists($mood, config('coach.cycle.moods', []))) {
            throw new InvalidArgumentException('Pick one of the faces.');
        }

        $entry = $this->todayRecord($user);
        $entry->update(['mood' => $entry->mood === $mood ? null : $mood]);

        return $entry;
    }

    /** Add or remove a symptom for today. */
    public function toggleSymptom(User $user, string $key): CycleEntry
    {
        $this->assertEnabled($user);
        if (! array_key_exists($key, config('coach.cycle.symptoms', []))) {
            throw new InvalidArgumentException('That symptom is not in the list.');
        }

        $entry = $this->todayRecord($user);
        $current = (array) ($entry->symptoms ?? []);
        $entry->update(['symptoms' => in_array($key, $current, true)
            ? array_values(array_diff($current, [$key]))
            : array_values(array_merge($current, [$key]))]);

        return $entry;
    }

    /**
     * The wheel: one segment per cycle day with its phase, and which day is today.
     *
     * @return array<int,array{day:int,phase:string,today:bool}>
     */
    public function wheel(CoachProfile $profile, ?int $today): array
    {
        $length = max(1, (int) $profile->cycle_length);
        $period = (int) $profile->period_length;
        $out = [];

        for ($d = 1; $d <= $length; $d++) {
            $out[] = ['day' => $d, 'phase' => CycleCalendar::phaseFor($d, $length, $period), 'today' => $d === $today];
        }

        return $out;
    }

    private function todayRecord(User $user): CycleEntry
    {
        // whereDate: the date column is stored with a time part, so a plain equality would miss it.
        return $this->entryToday($user)
            ?? CycleEntry::create(['user_id' => $user->id, 'entry_date' => CoachClock::today()->toDateString()]);
    }

    private function assertEnabled(User $user): void
    {
        if (! $this->enabled($user)) {
            throw new InvalidArgumentException('The cycle tracker is switched off.');
        }
    }
}
