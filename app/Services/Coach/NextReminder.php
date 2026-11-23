<?php

namespace App\Services\Coach;

use App\Models\ReminderSetting;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Text for the "Next reminder" card: the next water time and the weigh-in day (build guide 5.2).
 * Never names a medication or any health detail.
 */
class NextReminder
{
    private const DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    /** @return string[] short phrases, e.g. ["Water at 10:00", "Weigh-in Monday"] */
    public static function phrases(User $user, ?CarbonImmutable $now = null): array
    {
        $now ??= CoachClock::now();
        $settings = ReminderSetting::where('user_id', $user->id)->get()->keyBy('type');
        $out = [];

        if (($h = $settings->get('hydration')) && $h->enabled && ($time = self::nextWater($h->settings ?? [], $now))) {
            $out[] = 'Water at '.$time;
        }

        if (($w = $settings->get('weigh_in')) && $w->enabled) {
            $day = strtolower((string) ($w->settings['day'] ?? 'monday'));
            if (in_array($day, self::DAYS, true)) {
                $out[] = 'Weigh-in '.ucfirst($day);
            }
        }

        return $out;
    }

    /** Next hydration slot today (from, from+every, ... until), else the first slot tomorrow. */
    public static function nextWater(array $s, CarbonImmutable $now): ?string
    {
        $every = max(1, (int) ($s['every_hours'] ?? 2));
        [$fh, $fm] = array_map('intval', explode(':', $s['from'] ?? '08:00'));
        [$uh, $um] = array_map('intval', explode(':', $s['until'] ?? '20:00'));

        $start = $now->setTime($fh, $fm);
        $end = $now->setTime($uh, $um);

        for ($slot = $start; $slot->lte($end); $slot = $slot->addHours($every)) {
            if ($slot->gt($now)) {
                return $slot->format('H:i');
            }
        }

        return $start->format('H:i'); // first slot tomorrow
    }
}