<?php

namespace App\Services\Coach;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * "Today" for a Coach App patient. The app runs in UTC; patients live in South Africa, so days, reminder times
 * and cycle days are worked out in config('coach.timezone').
 */
class CoachClock
{
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('coach.timezone'));
    }

    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    /** UTC [start, end) of the patient's current day, for querying timestamp columns. */
    public static function todayRangeUtc(): array
    {
        $start = self::today();

        return [$start->utc(), $start->addDay()->utc()];
    }

    /** Whole local calendar days from $from to $to (positive when $to is later). */
    public static function daysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        // Compare calendar dates (each in its own zone), never raw instants, so a 02:00 offset cannot shift a day.
        return (int) CarbonImmutable::parse($from->toDateString())->diffInDays(CarbonImmutable::parse($to->toDateString()), false);
    }
}