<?php

namespace App\Services\Coach;

use App\Models\CoachMessage;
use App\Models\CoachingAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The in-app thread between a member and their assigned coach.
 *
 *  - A member can write only when a coach is assigned; a coach can write only to a member they are assigned to.
 *  - Messages are plain text, 1 to 1,000 characters, at most 20 an hour per member.
 *  - "Online" is the coach's published reply hours (config), not live presence.
 *  - New-message notifications say only that a message exists (never its text) and stay OFF until
 *    COACH_MESSAGE_NOTIFY=true. They are sent for the first unread message, not for every one.
 */
class CoachMessaging
{
    public function __construct(
        private readonly CoachAccess $access,
        private readonly ReminderSender $sender,
        private readonly ReminderSettings $reminders,
    ) {
    }

    public function coachFor(User $patient): ?User
    {
        $id = $this->access->assignedCoachId($patient);

        return $id ? User::find($id) : null;
    }

    public function coachName(User $patient): string
    {
        return $this->coachFor($patient)?->first_name ?: (string) config('coach.messaging.coach_name', 'your coach');
    }

    /** Oldest first, newest last (the last N). @return Collection<int,CoachMessage> */
    public function thread(User $patient): Collection
    {
        $limit = (int) config('coach.messaging.thread_limit', 100);

        return CoachMessage::where('patient_id', $patient->id)->orderByDesc('id')->limit($limit)->get()->reverse()->values();
    }

    public function sendFromPatient(User $patient, string $body): CoachMessage
    {
        $coach = $this->coachFor($patient);
        if (! $coach) {
            throw new InvalidArgumentException((string) config('coach.messaging.unassigned_text'));
        }

        $body = $this->cleanBody($body);

        $limit = (int) config('coach.messaging.per_hour_limit', 20);
        $recent = CoachMessage::where('patient_id', $patient->id)->where('from', CoachMessage::FROM_PATIENT)
            ->where('created_at', '>=', now()->subHour())->count();
        if ($recent >= $limit) {
            throw new InvalidArgumentException('You have sent a lot of messages in the last hour. Please wait a little, your coach will reply soon.');
        }

        $firstUnread = ! CoachMessage::where('patient_id', $patient->id)->where('from', CoachMessage::FROM_PATIENT)->whereNull('read_at')->exists();
        $message = CoachMessage::create(['patient_id' => $patient->id, 'sender_id' => $patient->id, 'from' => CoachMessage::FROM_PATIENT, 'body' => $body]);

        if ($firstUnread && config('coach.messaging.notify_enabled')) {
            $this->sender->sendText($coach, 'coach.message.to_coach', 'Zapmed coach app: new message',
                'A member sent you a message in the Zapmed coach app. Sign in to read and reply.', 'email');
        }

        return $message;
    }

    public function sendFromCoach(User $coach, User $patient, string $body): CoachMessage
    {
        if ($this->access->assignedCoachId($patient) !== $coach->id) {
            throw new InvalidArgumentException('You can only message members assigned to you.');
        }

        $body = $this->cleanBody($body);

        $firstUnread = ! CoachMessage::where('patient_id', $patient->id)->where('from', CoachMessage::FROM_COACH)->whereNull('read_at')->exists();
        $message = CoachMessage::create(['patient_id' => $patient->id, 'sender_id' => $coach->id, 'from' => CoachMessage::FROM_COACH, 'body' => $body]);

        if ($firstUnread && config('coach.messaging.notify_enabled')) {
            $name = $coach->first_name ?: config('coach.messaging.coach_name');
            $this->sender->sendText($patient, 'coach.message.to_member', 'Zapmed coach app: new message',
                "{$name} sent you a message in the Zapmed app. Open the app to read it.",
                $this->reminders->get($patient, 'hydration')['channel'] ?? 'whatsapp');
        }

        return $message;
    }

    /** The member opened the thread: coach messages become read. */
    public function markReadByPatient(User $patient): void
    {
        CoachMessage::where('patient_id', $patient->id)->where('from', CoachMessage::FROM_COACH)->whereNull('read_at')->update(['read_at' => now()]);
    }

    /** The coach opened the thread: member messages become read. Only the assigned coach counts. */
    public function markReadByCoach(User $coach, User $patient): void
    {
        if ($this->access->assignedCoachId($patient) === $coach->id) {
            CoachMessage::where('patient_id', $patient->id)->where('from', CoachMessage::FROM_PATIENT)->whereNull('read_at')->update(['read_at' => now()]);
        }
    }

    public function unreadForPatient(User $patient): int
    {
        return CoachMessage::where('patient_id', $patient->id)->where('from', CoachMessage::FROM_COACH)->whereNull('read_at')->count();
    }

    /**
     * Threads for a coach: each assigned member with messages, newest activity first, with unread counts.
     *
     * @return Collection<int,array{patient:User,unread:int,last:CoachMessage}>
     */
    public function inboxFor(User $coach): Collection
    {
        $patientIds = CoachingAssignment::active()->where('coach_id', $coach->id)->pluck('patient_id');

        return CoachMessage::whereIn('patient_id', $patientIds)->orderByDesc('id')->get()
            ->groupBy('patient_id')
            ->map(fn (Collection $rows) => [
                'patient' => User::find($rows->first()->patient_id),
                'unread' => $rows->where('from', CoachMessage::FROM_PATIENT)->whereNull('read_at')->count(),
                'last' => $rows->first(),
            ])
            ->filter(fn ($t) => $t['patient'])
            ->values();
    }

    public function unreadForCoach(User $coach): int
    {
        $patientIds = CoachingAssignment::active()->where('coach_id', $coach->id)->pluck('patient_id');

        return CoachMessage::whereIn('patient_id', $patientIds)->where('from', CoachMessage::FROM_PATIENT)->whereNull('read_at')->count();
    }

    /** Inside the published reply hours (South African time)? */
    public function isOnline(?CarbonImmutable $now = null): bool
    {
        $now = ($now ?? CoachClock::now())->setTimezone(config('coach.timezone'));
        $h = config('coach.messaging.hours');

        if (! in_array(strtolower($now->englishDayOfWeek), $h['days'], true)) {
            return false;
        }

        [$fh, $fm] = array_map('intval', explode(':', $h['from']));
        [$uh, $um] = array_map('intval', explode(':', $h['until']));

        return $now->gte($now->setTime($fh, $fm)) && $now->lt($now->setTime($uh, $um));
    }

    private function cleanBody(string $body): string
    {
        $body = trim(preg_replace("/\r\n?/", "\n", $body));
        $max = (int) config('coach.messaging.max_length', 1000);

        if ($body === '') {
            throw new InvalidArgumentException('Please type a message.');
        }
        if (mb_strlen($body) > $max) {
            throw new InvalidArgumentException("Messages can be up to {$max} characters.");
        }

        return $body;
    }
}