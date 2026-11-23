<?php

namespace App\Services\Coach;

use App\Models\CoachProfile;
use App\Models\ReminderSetting;
use App\Models\User;
use App\Models\WeightEntry;
use Carbon\CarbonImmutable;

/**
 * Works out which reminders are due right now and sends them (build guide 5.7). Called every 5 minutes.
 *
 * A reminder has a SLOT (a clock time in South African time). It is sent when the scheduler sees it within
 * `window_minutes` after the slot and it has not been sent for that slot yet (ReminderSetting.last_sent_at).
 * Nothing is sent unless the master switch coach.reminder_sending.enabled is on.
 */
class ReminderScheduler
{
    public function __construct(
        private readonly ReminderSettings $settings,
        private readonly ReminderSender $sender,
        private readonly CoachAccess $access,
        private readonly WaterLogger $water,
    ) {
    }

    /** @return int how many reminders were sent */
    public function run(?CarbonImmutable $now = null): int
    {
        if (! config('coach.reminder_sending.enabled')) {
            return 0;
        }

        $now = ($now ?? CoachClock::now())->setTimezone(config('coach.timezone'));
        $sent = 0;

        CoachProfile::whereNotNull('onboarded_at')->with('user')->chunkById(200, function ($profiles) use ($now, &$sent) {
            foreach ($profiles as $profile) {
                $user = $profile->user;
                if (! $user || ! $this->access->patientHasAddon($user)) {
                    continue;
                }
                $sent += $this->runFor($user, $profile, $now);
            }
        });

        return $sent;
    }

    public function runFor(User $user, CoachProfile $profile, CarbonImmutable $now): int
    {
        $sent = 0;
        $all = $this->settings->all($user);
        $channel = $all['hydration']['channel'] ?? 'whatsapp'; // one delivery choice for all reminders

        foreach ($all as $type => $s) {
            if (! $s['enabled']) {
                continue;
            }

            $slot = $this->dueSlot($user, $profile, $type, $s, $now);
            if (! $slot) {
                continue;
            }

            $row = ReminderSetting::firstOrCreate(
                ['user_id' => $user->id, 'type' => $type],
                ['enabled' => true, 'settings' => collect($s)->except('enabled')->all()]
            );

            if ($row->last_sent_at && $row->last_sent_at->gte($slot)) {
                continue; // already sent for this slot
            }

            if ($this->sender->send($user, $type, $channel)) {
                $row->update(['last_sent_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }

    /** The slot to send now for this reminder, or null when nothing is due. */
    public function dueSlot(User $user, CoachProfile $profile, string $type, array $s, CarbonImmutable $now): ?CarbonImmutable
    {
        $slot = match ($type) {
            'hydration' => $this->hydrationSlot($user, $s, $now),
            'weigh_in' => $this->weighInSlot($user, $s, $now),
            'dose_day' => $this->doseSlot($user, $s, $now),
            'meals' => $this->mealSlot($now),
            'cycle' => $this->cycleSlot($profile, $now),
            default => null,
        };

        $window = (int) config('coach.reminder_sending.window_minutes', 10);

        return ($slot && $slot->lte($now) && $slot->addMinutes($window)->gt($now)) ? $slot : null;
    }

    private function at(CarbonImmutable $day, string $hhmm): CarbonImmutable
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $day->setTime($h, $m);
    }

    /** Latest hydration slot at or before now. Skipped when "smart nudge" says the member is on pace. */
    private function hydrationSlot(User $user, array $s, CarbonImmutable $now): ?CarbonImmutable
    {
        $every = max(1, (int) $s['every_hours']);
        $start = $this->at($now, $s['from']);
        $end = $this->at($now, $s['until']);

        $slots = [];
        for ($t = $start; $t->lte($end); $t = $t->addHours($every)) {
            $slots[] = $t;
        }

        $latestIndex = null;
        foreach ($slots as $i => $t) {
            if ($t->lte($now)) {
                $latestIndex = $i;
            }
        }
        if ($latestIndex === null) {
            return null;
        }

        if (! empty($s['smart_skip'])) {
            $expected = (int) config('coach.targets.water_ml') * (($latestIndex + 1) / count($slots));
            if ($this->water->todayTotal($user) >= $expected) {
                return null; // on pace: no nudge needed
            }
        }

        return $slots[$latestIndex];
    }

    private function weighInSlot(User $user, array $s, CarbonImmutable $now): ?CarbonImmutable
    {
        if (($s['frequency'] ?? 'weekly') === 'weekly' && strtolower($now->englishDayOfWeek) !== ($s['day'] ?? 'monday')) {
            return null;
        }

        $weighedToday = WeightEntry::where('user_id', $user->id)->whereDate('logged_on', $now->toDateString())->exists();

        return $weighedToday ? null : $this->at($now, $s['time'] ?? '08:00');
    }

    /** The evening before the next dose, and the day of it, at the chosen time. Not after the dose is logged. */
    private function doseSlot(User $user, array $s, CarbonImmutable $now): ?CarbonImmutable
    {
        $next = MedicationLevel::forUser($user)->nextDoseDate;
        if (! $next) {
            return null;
        }

        $days = CoachClock::daysBetween($now, $next);
        if ($days !== 1 && $days !== 0) {
            return null;
        }

        return $this->at($now, $s['time'] ?? '20:00');
    }

    private function mealSlot(CarbonImmutable $now): ?CarbonImmutable
    {
        if (! config('coach.reminder_sending.meals_enabled')) {
            return null; // meal logging does not exist yet
        }

        $latest = null;
        foreach (config('coach.reminder_sending.meal_times', []) as $time) {
            $t = $this->at($now, $time);
            if ($t->lte($now)) {
                $latest = $t;
            }
        }

        return $latest;
    }

    /** Two days before the period, and the first day of the luteal phase. */
    private function cycleSlot(CoachProfile $profile, CarbonImmutable $now): ?CarbonImmutable
    {
        $status = CycleCalendar::status($profile, $now);
        if (! $status) {
            return null;
        }

        $before = (int) config('coach.reminder_sending.cycle_days_before_period', 2);
        if ($status['days_to_period'] === $before || $status['day'] === CycleCalendar::lutealStartDay($profile)) {
            return $this->at($now, config('coach.reminder_sending.cycle_time', '09:00'));
        }

        return null;
    }
}