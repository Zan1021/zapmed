<?php

namespace App\Services\Coach;

use App\Enums\UserRole;
use App\Models\CoachCall;
use App\Models\CoachFlag;
use App\Models\CoachingAssignment;
use App\Models\CoachMessage;
use App\Models\CoachNote;
use App\Models\CoachProfile;
use App\Models\DoseEntry;
use App\Models\SideEffectEntry;
use App\Models\User;
use App\Models\WeightEntry;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Staff side of the Coach App: the coach's board of assigned members, one member's tracking summary (coach and
 * treating doctor), and the coach notes. Every read goes through CoachAccess::canView; callers must check it first.
 */
class CoachPatientBoard
{
    public function __construct(private CoachAccess $access)
    {
    }

    /**
     * One row per actively assigned member, those with open safety flags first, then those with unread messages,
     * then the longest since a weigh-in.
     *
     * @return Collection<int,array<string,mixed>>
     */
    public function boardFor(User $coach): Collection
    {
        $patients = CoachingAssignment::active()->where('coach_id', $coach->id)->with('patient')->get()
            ->pluck('patient')->filter();

        $rows = $patients->map(function (User $p) {
            $weight = WeightStats::forUser($p);
            $daysSince = $weight->lastLoggedOn ? CoachClock::daysBetween($weight->lastLoggedOn, CoachClock::today()) : null;

            return [
                'patient' => $p,
                'percent' => $weight->percentToGoal,
                'current' => $weight->current,
                'lastWeighIn' => $weight->lastLoggedOn,
                'daysSinceWeighIn' => $daysSince,
                'quiet' => $daysSince === null || $daysSince >= (int) config('coach.board.quiet_days', 7),
                'openFlags' => CoachFlag::open()->where('patient_id', $p->id)->count(),
                'unread' => CoachMessage::where('patient_id', $p->id)->where('from', CoachMessage::FROM_PATIENT)->whereNull('read_at')->count(),
                'nextCall' => CoachCall::booked()->where('patient_id', $p->id)->where('starts_at', '>=', now())->orderBy('starts_at')->first(),
            ];
        });

        return $rows->sortBy([
            fn ($a, $b) => $b['openFlags'] <=> $a['openFlags'],
            fn ($a, $b) => $b['unread'] <=> $a['unread'],
            fn ($a, $b) => ($b['daysSinceWeighIn'] ?? PHP_INT_MAX) <=> ($a['daysSinceWeighIn'] ?? PHP_INT_MAX),
        ])->values();
    }

    /**
     * Everything the staff patient view and the doctor summary show. No medicine names (the app never stores any).
     *
     * @return array<string,mixed>
     */
    public function summary(User $patient): array
    {
        $weight = WeightStats::forUser($patient, 90);
        $profile = CoachProfile::where('user_id', $patient->id)->first();
        $limit = (int) config('coach.board.history_count', 8);

        return [
            'patient' => $patient,
            'profile' => $profile,
            'weight' => $weight,
            'weightHistory' => WeightEntry::where('user_id', $patient->id)->orderByDesc('logged_on')->limit($limit)->get(),
            'doses' => DoseEntry::where('user_id', $patient->id)->orderByDesc('taken_at')->limit($limit)->get(),
            'sideEffects' => SideEffectEntry::where('user_id', $patient->id)
                ->where('logged_on', '>=', CoachClock::today()->subDays((int) config('coach.board.side_effect_days', 30))->toDateString())
                ->orderByDesc('logged_on')->orderByDesc('id')->get(),
            'flags' => CoachFlag::where('patient_id', $patient->id)->orderByDesc('created_at')->limit(5)->get(),
            'calls' => CoachCall::where('patient_id', $patient->id)->where('status', '!=', CoachCall::CANCELLED)
                ->orderByDesc('starts_at')->limit($limit)->get(),
            'milestone' => MilestoneStats::forUser($patient),
            'coach' => app(CoachMessaging::class)->coachFor($patient),
        ];
    }

    /**
     * Concise coaching summary for the doctor's consultation screen. Null when the doctor may not see this member
     * or the member has never used the coach app (nothing to show).
     *
     * @return array<string,mixed>|null
     */
    public function doctorSummary(User $doctor, User $patient): ?array
    {
        if ($doctor->role !== UserRole::Doctor || ! $this->access->canView($doctor, $patient)) {
            return null;
        }

        $profile = CoachProfile::where('user_id', $patient->id)->whereNotNull('onboarded_at')->first();
        if (! $profile) {
            return null;
        }

        $weight = WeightStats::forUser($patient);
        $recentSince = CoachClock::today()->subDays(7)->toDateString();

        return [
            'profile' => $profile,
            'weight' => $weight,
            'units' => CoachUnits::normalise($profile->units),
            'weekLossKg' => $weight->weekLossKg,
            'lastDose' => DoseEntry::where('user_id', $patient->id)->orderByDesc('taken_at')->first(),
            'recentSideEffects' => SideEffectEntry::where('user_id', $patient->id)->where('logged_on', '>=', $recentSince)
                ->orderByDesc('score')->orderByDesc('logged_on')->limit(5)->get(),
            'openFlags' => CoachFlag::open()->where('patient_id', $patient->id)->count(),
            'coach' => app(CoachMessaging::class)->coachFor($patient),
            'sharedNotes' => CoachNote::where('patient_id', $patient->id)->where('visibility', CoachNote::SHARED)
                ->with('author')->orderByDesc('id')->limit(3)->get(),
        ];
    }

    /** Notes this viewer may read for a member (their own private notes + all shared). */
    public function notesFor(User $viewer, User $patient): Collection
    {
        return CoachNote::where('patient_id', $patient->id)->readableBy($viewer)->with('author')->orderByDesc('id')->get();
    }

    /** Only the member's actively assigned coach may write notes. */
    public function addNote(User $author, User $patient, string $visibility, string $body): CoachNote
    {
        if ($author->role !== UserRole::HealthCoach || ! $this->access->canView($author, $patient)) {
            abort(403);
        }
        if (! in_array($visibility, [CoachNote::PRIVATE, CoachNote::SHARED], true)) {
            throw new InvalidArgumentException('Choose private or shared.');
        }

        $body = trim($body);
        $max = (int) config('coach.board.note_max_length', 2000);
        if ($body === '') {
            throw new InvalidArgumentException('Please write a note first.');
        }
        if (mb_strlen($body) > $max) {
            throw new InvalidArgumentException("Notes can be up to {$max} characters.");
        }

        return CoachNote::create(['patient_id' => $patient->id, 'author_id' => $author->id, 'visibility' => $visibility, 'body' => $body]);
    }

    /** An author may delete their own note. */
    public function deleteNote(User $author, int $noteId): void
    {
        $note = CoachNote::findOrFail($noteId);
        abort_unless($note->author_id === $author->id, 403);
        $note->delete();
    }
}
