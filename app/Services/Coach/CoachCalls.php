<?php

namespace App\Services\Coach;

use App\Models\CoachCall;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Booking a call with the coach (build guide 5.15). All times are South African time; stored as UTC.
 *
 *  - A member books with THEIR assigned coach, for 15 or 30 minutes, on one of the next 5 working days.
 *  - Slots start on the half hour inside the coach's call hours, skip the breaks, and need 2 hours' notice.
 *  - A slot is taken when the coach already has a booked call overlapping it. Two members confirming at the same
 *    moment cannot both win: slot_key is UNIQUE while a call is booked.
 *  - A member holds one upcoming call at a time and can cancel it. Confirmation and reminder messages carry the
 *    time only, never anything about health, and stay off until COACH_CALL_NOTIFY=true.
 */
class CoachCalls
{
    public function __construct(
        private readonly CoachMessaging $messaging,
        private readonly ReminderSender $sender,
        private readonly ReminderSettings $reminders,
    ) {
    }

    /** @return string[] */
    public function lengths(): array
    {
        return array_map('intval', config('coach.calls.lengths_minutes', [15, 30]));
    }

    /** The next N working days from today, as South African midnight dates. @return Collection<int,CarbonImmutable> */
    public function days(?CarbonImmutable $from = null): Collection
    {
        $day = ($from ?? CoachClock::today())->startOfDay();
        $want = (int) config('coach.calls.days_ahead', 5);
        $workdays = config('coach.calls.hours.days', []);
        $days = collect();

        for ($i = 0; $i < 21 && $days->count() < $want; $i++, $day = $day->addDay()) {
            if (in_array(strtolower($day->englishDayOfWeek), $workdays, true)) {
                $days->push($day);
            }
        }

        return $days;
    }

    /**
     * Every start time of that day with whether it can be booked.
     *
     * @return array<int,array{time:string,starts_at:CarbonImmutable,available:bool}>
     */
    public function slots(User $patient, CarbonImmutable $date, int $length, ?CarbonImmutable $now = null): array
    {
        $coach = $this->messaging->coachFor($patient);
        $now = $now ?? CoachClock::now();
        $date = $date->startOfDay();

        if (! in_array($length, $this->lengths(), true) || ! in_array(strtolower($date->englishDayOfWeek), config('coach.calls.hours.days', []), true)) {
            return [];
        }

        $hours = config('coach.calls.hours');
        $step = (int) config('coach.calls.step_minutes', 30);
        $earliest = $now->addHours((int) config('coach.calls.min_notice_hours', 2));
        $end = $this->at($date, $hours['until']);

        $taken = $coach
            ? CoachCall::booked()->where('coach_id', $coach->id)
                ->where('starts_at', '>=', $date->utc())->where('starts_at', '<', $date->addDay()->utc())->get()
            : collect();

        $slots = [];
        for ($t = $this->at($date, $hours['from']); $t->addMinutes($length)->lte($end); $t = $t->addMinutes($step)) {
            if ($this->inBreak($date, $t, $length)) {
                continue;
            }

            $busy = $taken->contains(function (CoachCall $c) use ($t, $length) {
                $cs = CarbonImmutable::parse($c->starts_at);

                return $cs->lt($t->addMinutes($length)) && $cs->addMinutes($c->length_minutes)->gt($t);
            });

            $slots[] = ['time' => $t->format('H:i'), 'starts_at' => $t, 'available' => $coach && ! $busy && $t->gte($earliest)];
        }

        return $slots;
    }

    /** @throws InvalidArgumentException with a message fit to show the member */
    public function book(User $patient, CarbonImmutable $startsAt, int $length, ?string $purpose = null): CoachCall
    {
        $coach = $this->messaging->coachFor($patient);
        if (! $coach) {
            throw new InvalidArgumentException((string) config('coach.messaging.unassigned_text'));
        }

        if ($this->upcomingFor($patient)) {
            throw new InvalidArgumentException('You already have a call booked. Cancel it first if you would like a different time.');
        }

        $startsAt = $startsAt->setTimezone(config('coach.timezone'));
        $allowedDays = $this->days()->map(fn ($d) => $d->toDateString())->all();
        $match = collect($this->slots($patient, $startsAt, $length))->first(fn ($s) => $s['starts_at']->equalTo($startsAt));

        if (! in_array($startsAt->toDateString(), $allowedDays, true) || ! $match) {
            throw new InvalidArgumentException('That time is not available. Please pick another.');
        }
        if (! $match['available']) {
            throw new InvalidArgumentException('That time was just taken. Please pick another.');
        }

        $purpose = in_array($purpose, config('coach.calls.purposes', []), true) ? $purpose : null;

        try {
            $call = DB::transaction(fn () => CoachCall::create([
                'patient_id' => $patient->id, 'coach_id' => $coach->id, 'starts_at' => $startsAt->utc(), 'length_minutes' => $length,
                'status' => CoachCall::BOOKED, 'purpose' => $purpose, 'slot_key' => $coach->id.':'.$startsAt->utc()->format('YmdHi'),
            ]));
        } catch (UniqueConstraintViolationException) {
            throw new InvalidArgumentException('That time was just taken. Please pick another.');
        }

        $this->notifyBooked($call, $patient, $coach);

        return $call;
    }

    public function cancel(User $patient, int $callId): bool
    {
        $call = CoachCall::booked()->where('patient_id', $patient->id)->where('starts_at', '>', now())->find($callId);
        if (! $call) {
            return false;
        }

        $call->update(['status' => CoachCall::CANCELLED, 'slot_key' => null]);

        if (config('coach.calls.notify_enabled') && $call->coach) {
            $this->sender->sendText($call->coach, 'coach.call.cancelled', 'Zapmed coach app: call cancelled',
                'A member cancelled their call on '.$this->when($call).'. The slot is free again.', 'email');
        }

        return true;
    }

    public function upcomingFor(User $patient): ?CoachCall
    {
        return CoachCall::booked()->where('patient_id', $patient->id)->where('starts_at', '>', now())->orderBy('starts_at')->first();
    }

    /** @return Collection<int,CoachCall> a coach's booked calls from now on, soonest first */
    public function upcomingForCoach(User $coach, int $limit = 20): Collection
    {
        return CoachCall::booked()->with('patient')->where('coach_id', $coach->id)->where('starts_at', '>', now())
            ->orderBy('starts_at')->limit($limit)->get();
    }

    /** "Wed 25 Nov, 10:00" in South African time. */
    public function when(CoachCall $call): string
    {
        return CarbonImmutable::parse($call->starts_at)->setTimezone(config('coach.timezone'))->format('D j M, H:i');
    }

    /** Reminder an hour before. Called every 5 minutes with the reminders. @return int how many were sent */
    public function sendDueReminders(?CarbonImmutable $now = null): int
    {
        if (! config('coach.calls.notify_enabled')) {
            return 0;
        }

        $now = $now ?? CarbonImmutable::now();
        $until = $now->addMinutes((int) config('coach.calls.reminder_minutes_before', 60));
        $sent = 0;

        $due = CoachCall::booked()->with(['patient', 'coach'])->whereNull('reminded_at')
            ->where('starts_at', '>', $now->utc())->where('starts_at', '<=', $until->utc())->get();

        foreach ($due as $call) {
            if (! $call->patient) {
                continue;
            }

            $name = $call->coach?->first_name ?: config('coach.messaging.coach_name');
            $ok = $this->sender->sendText($call->patient, 'coach.call.reminder', 'Zapmed reminder',
                "Reminder: your call with {$name} is at ".CarbonImmutable::parse($call->starts_at)->setTimezone(config('coach.timezone'))->format('H:i').' today.',
                $this->reminders->get($call->patient, 'hydration')['channel'] ?? 'whatsapp');

            if ($ok) {
                $call->update(['reminded_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }

    private function notifyBooked(CoachCall $call, User $patient, User $coach): void
    {
        if (! config('coach.calls.notify_enabled')) {
            return;
        }

        $name = $coach->first_name ?: config('coach.messaging.coach_name');
        $this->sender->sendText($patient, 'coach.call.booked', 'Your Zapmed call is booked',
            "Your call with {$name} is booked for ".$this->when($call).' ('.$call->length_minutes.' minutes). You can change it in the Zapmed app.',
            $this->reminders->get($patient, 'hydration')['channel'] ?? 'whatsapp');

        $this->sender->sendText($coach, 'coach.call.to_coach', 'Zapmed coach app: new call booked',
            'A member booked a call with you on '.$this->when($call).' ('.$call->length_minutes.' minutes).', 'email');
    }

    private function at(CarbonImmutable $day, string $hhmm): CarbonImmutable
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $day->setTime($h, $m);
    }

    private function inBreak(CarbonImmutable $date, CarbonImmutable $start, int $length): bool
    {
        $end = $start->addMinutes($length);

        foreach (config('coach.calls.breaks', []) as [$from, $until]) {
            if ($start->lt($this->at($date, $until)) && $end->gt($this->at($date, $from))) {
                return true;
            }
        }

        return false;
    }
}
