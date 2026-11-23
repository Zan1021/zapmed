<?php

namespace App\Services\Coach;

use App\Enums\AlertSeverity;
use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\CoachFlag;
use App\Models\CoachingAssignment;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Safety escalation (build guide section 7): a serious symptom score, or "something feels wrong", must reach a
 * doctor with no queue. Order of work matters: the flag is saved FIRST, then staff are told. If telling fails the
 * flag still exists and shows on the staff list, so a flag can never be lost.
 *
 * Messages to staff carry NO health detail (member number only). Detail lives in the flag, behind access checks.
 */
class CoachEscalation
{
    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    /** Abdominal pain at 7+ or any symptom at 9+ (levels in config coach.flags). */
    public static function isFlagLevel(string $symptom, int $score): bool
    {
        $f = config('coach.flags');

        return $score >= (int) $f['any_level']
            || ($symptom === $f['abdominal_symptom'] && $score >= (int) $f['abdominal_level']);
    }

    /** Flag a serious symptom. Re-flagging the same symptom within the dedupe window keeps ONE open flag (highest score). */
    public function flagSymptom(User $patient, string $symptom, int $score): CoachFlag
    {
        $open = CoachFlag::where('patient_id', $patient->id)->open()
            ->where('source', 'side_effect')->where('symptom', $symptom)
            ->where('created_at', '>=', now()->subHours((int) config('coach.flags.dedupe_hours', 24)))
            ->latest()->first();

        if ($open) {
            if ($score > (int) $open->score) {
                $open->update(['score' => $score]);
            }

            return $open;
        }

        return $this->raise($patient, 'side_effect', $symptom, $score);
    }

    /** "Reach Dr Anke now" / "Something feels wrong". One open request per hour. */
    public function selfReport(User $patient): CoachFlag
    {
        $open = CoachFlag::where('patient_id', $patient->id)->open()->where('source', 'self_report')
            ->where('created_at', '>=', now()->subHour())->latest()->first();

        return $open ?? $this->raise($patient, 'self_report', null, null);
    }

    private function raise(User $patient, string $source, ?string $symptom, ?int $score): CoachFlag
    {
        $flag = CoachFlag::create([
            'patient_id' => $patient->id, 'source' => $source, 'symptom' => $symptom, 'score' => $score, 'status' => 'open',
        ]);

        try {
            Alert::raise([
                'definition_code' => 'coach.safety_flag',
                'severity' => AlertSeverity::Critical,
                'title' => 'Coach app safety flag',
                'detail' => 'A member needs a clinician to review their coach app entry. Member '.($patient->member_number ?? '#'.$patient->id).'.',
                'subject_type' => 'coach_flag',
                'subject_id' => $flag->id,
                'dedupe_key' => 'coach.safety_flag:'.$flag->id,
                'metadata' => ['flag_id' => $flag->id, 'source' => $source],
            ]);
        } catch (\Throwable $e) {
            Log::error('coach flag: could not raise board alert', ['flag' => $flag->id, 'error' => $e->getMessage()]);
        }

        $this->notify($flag, $patient);

        return $flag;
    }

    /** @return Collection<int,User> */
    public function recipients(User $patient): Collection
    {
        $recipients = collect();

        $emails = config('coach.flags.notify_emails', []);
        if ($emails) {
            $recipients = $recipients->merge(User::whereIn('email', $emails)->get());
        }

        $coachId = CoachingAssignment::active()->where('patient_id', $patient->id)->value('coach_id');
        if ($coachId && ($coach = User::find($coachId))) {
            $recipients->push($coach);
        }

        if ($recipients->isEmpty()) {
            $recipients = User::where('role', UserRole::Admin->value)->where('is_active', true)->get();
        }

        return $recipients->unique('id')->values();
    }

    private function notify(CoachFlag $flag, User $patient): void
    {
        $member = $patient->member_number ?? '#'.$patient->id;
        $subject = 'Zapmed coach app: safety flag to review';
        $body = "A member ({$member}) has reached a safety level in the coach app. Please sign in and open the coach flags list now. No health detail is sent by message.";
        $sent = false;

        foreach ($this->recipients($patient) as $user) {
            try {
                $message = new OutboundMessage(
                    templateKey: 'coach.safety_flag', category: 'transactional', user: $user,
                    phone: $user->phone, subject: $subject, body: $body, meta: ['flag_id' => $flag->id],
                );
                $logs = $this->dispatcher->send($message, config('coach.flags.channels', ['email']));
                $sent = $sent || collect($logs)->contains(fn ($l) => $l->status === 'sent');
            } catch (\Throwable $e) {
                Log::error('coach flag: notification failed', ['flag' => $flag->id, 'user' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        if ($sent) {
            $flag->update(['notified_at' => now()]);
        } else {
            Log::warning('coach flag: nobody could be notified, flag stays open on the staff list', ['flag' => $flag->id]);
        }
    }
}