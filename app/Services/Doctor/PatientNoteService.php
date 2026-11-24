<?php

namespace App\Services\Doctor;

use App\Enums\UserRole;
use App\Models\CoachingAssignment;
use App\Models\Order;
use App\Models\PatientNote;
use App\Models\PatientNoteAudience;
use App\Models\User;
use App\Services\Coach\CoachAccess;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Note separation (Craig's guide 4.3 and 8.1).
 *
 *  clinical         Only doctors who treat the patient (the author included). Never Operations, a coach or the patient.
 *  patient_summary  Written by a treating doctor. The patient and treating doctors read it.
 *  operations       A staff note addressed to chosen people and/or whole roles. Read by the author and the addressees:
 *                   a person named directly, or someone in a named role (a doctor or coach in a role also needs access
 *                   to the patient). Operations does NOT read a note unless it is addressed to Operations or they wrote it.
 *
 * Notes are append-only (a correction is a new note). A staff note can ask for an action: open until an addressee
 * (or the author) marks it done. Coach notes are kept by the Coach App (coach_notes: private or shared) and follow the
 * same rule: never visible to Operations. Messages about a new note carry no note text (OFF until DOCTOR_NOTE_NOTIFY).
 */
class PatientNoteService
{
    public const ROLES = ['admin', 'doctor', 'health_coach'];

    public function __construct(
        private readonly CoachAccess $access,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    /** A doctor who treats the patient, or a coach assigned to them. */
    private function hasPatientAccess(User $user, User $patient): bool
    {
        return in_array($user->role, [UserRole::Doctor, UserRole::HealthCoach], true) && $this->access->canView($user, $patient);
    }

    /** What this person may write about this patient. */
    public function typesFor(User $author, User $patient): array
    {
        return match (true) {
            $author->role === UserRole::Doctor && $this->access->canView($author, $patient)
                => [PatientNote::CLINICAL, PatientNote::PATIENT_SUMMARY, PatientNote::OPERATIONS],
            $author->role === UserRole::HealthCoach && $this->access->canView($author, $patient) => [PatientNote::OPERATIONS],
            $author->role === UserRole::Admin => [PatientNote::OPERATIONS],
            default => [],
        };
    }

    /**
     * @param array<int,array{user_id?:int,role?:string}> $audience for operations notes: people and/or roles
     */
    public function create(User $author, User $patient, string $type, string $body, array $audience = [], ?int $orderId = null, bool $actionRequired = false): PatientNote
    {
        abort_unless($patient->role === UserRole::Patient, 404);
        abort_unless(in_array($type, $this->typesFor($author, $patient), true), 403);

        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Write the note first.']);
        }
        if (mb_strlen($body) > 5000) {
            throw ValidationException::withMessages(['body' => 'That note is too long (5000 characters at most).']);
        }
        if ($orderId && ! Order::where('id', $orderId)->where('patient_id', $patient->id)->exists()) {
            throw ValidationException::withMessages(['order' => 'That order does not belong to the patient.']);
        }

        $rows = $type === PatientNote::OPERATIONS ? $this->checkedAudience($audience) : [];
        if ($type === PatientNote::OPERATIONS && $rows === []) {
            throw ValidationException::withMessages(['audience' => 'Choose who this note is for.']);
        }
        if ($type !== PatientNote::OPERATIONS && ($audience || $actionRequired)) {
            throw ValidationException::withMessages(['audience' => 'Only staff notes are addressed to people or ask for an action.']);
        }

        $note = DB::transaction(function () use ($author, $patient, $type, $body, $rows, $orderId, $actionRequired) {
            $note = PatientNote::create([
                'patient_id' => $patient->id,
                'order_id' => $orderId,
                'author_id' => $author->id,
                'type' => $type,
                'body' => $body,
                'action_status' => $actionRequired ? 'open' : 'none',
            ]);
            foreach ($rows as $row) {
                $note->audiences()->create($row);
            }

            return $note;
        });

        if ($type === PatientNote::OPERATIONS) {
            $this->tell($note, $author, $patient);
        }

        return $note;
    }

    public function canRead(User $user, PatientNote $note): bool
    {
        $patient = $note->patient;

        switch ($note->type) {
            case PatientNote::CLINICAL:
                return $user->role === UserRole::Doctor && $this->access->canView($user, $patient);

            case PatientNote::PATIENT_SUMMARY:
                return $user->id === $note->patient_id
                    || ($user->role === UserRole::Doctor && $this->access->canView($user, $patient));

            case PatientNote::OPERATIONS:
                if ($note->author_id === $user->id) {
                    return true;
                }

                return $this->isAddressee($user, $note);
        }

        return false;
    }

    private function isAddressee(User $user, PatientNote $note): bool
    {
        foreach ($note->audiences as $a) {
            if ($a->user_id === $user->id) {
                return true;   // named directly: they are being asked to look at it
            }
            if ($a->role === $user->role->value) {
                // everyone in a role: Operations see it, a doctor or coach only when they have access to that patient
                return $user->role === UserRole::Admin || $this->hasPatientAccess($user, $note->patient);
            }
        }

        return false;
    }

    /** Every note about this patient the viewer may read, newest first. Nothing else leaks into the list. */
    public function readableFor(User $viewer, User $patient): Collection
    {
        return PatientNote::with(['author', 'audiences', 'order', 'patient'])
            ->where('patient_id', $patient->id)->orderByDesc('id')->get()
            ->filter(fn (PatientNote $n) => $this->canRead($viewer, $n))->values();
    }

    /** What a patient may read: the summaries their doctors wrote for them. */
    public function summariesFor(User $patient): Collection
    {
        return PatientNote::where('patient_id', $patient->id)->where('type', PatientNote::PATIENT_SUMMARY)
            ->orderByDesc('id')->get();
    }

    /** Staff notes waiting for this person (addressed to them or their role), newest first. */
    public function inbox(User $user, bool $openActionsOnly = false): Collection
    {
        abort_unless(in_array($user->role, [UserRole::Admin, UserRole::Doctor, UserRole::HealthCoach], true), 403);

        $q = PatientNote::with(['author', 'audiences', 'order', 'patient'])
            ->where('type', PatientNote::OPERATIONS)
            ->whereHas('audiences', fn ($a) => $a->where('user_id', $user->id)->orWhere('role', $user->role->value))
            ->orderByDesc('id');
        if ($openActionsOnly) {
            $q->where('action_status', 'open');
        }

        return $q->limit(300)->get()->filter(fn (PatientNote $n) => $this->canRead($user, $n))->values();
    }

    public function markRead(User $user, PatientNote $note): void
    {
        abort_unless($this->canRead($user, $note), 403);

        PatientNoteAudience::where('patient_note_id', $note->id)->where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    /** An addressee or the author marks the requested action as done. */
    public function completeAction(User $user, PatientNote $note): PatientNote
    {
        abort_unless($this->canRead($user, $note), 403);

        if ($note->action_status !== 'open') {
            throw ValidationException::withMessages(['action' => 'There is no open action on this note.']);
        }

        $note->update(['action_status' => 'done', 'action_done_by' => $user->id, 'action_done_at' => now()]);

        return $note->fresh();
    }

    /** @return array<int,array{user_id:?int,role:?string}> */
    private function checkedAudience(array $audience): array
    {
        $rows = [];

        foreach ($audience as $entry) {
            if (! empty($entry['user_id'])) {
                $user = User::whereIn('role', [UserRole::Admin->value, UserRole::Doctor->value, UserRole::HealthCoach->value])->find((int) $entry['user_id']);
                if (! $user) {
                    throw ValidationException::withMessages(['audience' => 'Notes can only be addressed to staff.']);
                }
                $rows[] = ['user_id' => $user->id, 'role' => null];
            } elseif (! empty($entry['role'])) {
                if (! in_array($entry['role'], self::ROLES, true)) {
                    throw ValidationException::withMessages(['audience' => 'That role cannot be addressed.']);
                }
                $rows[] = ['user_id' => null, 'role' => $entry['role']];
            }
        }

        return array_values(array_unique($rows, SORT_REGULAR));
    }

    /** Who is told: named people, plus everyone in a named role who may read the note. OFF until DOCTOR_NOTE_NOTIFY=true. */
    private function tell(PatientNote $note, User $author, User $patient): void
    {
        if (! config('doctor.notes.notify_enabled')) {
            return;
        }

        $note->load('audiences');
        $people = collect();
        foreach ($note->audiences as $a) {
            if ($a->user_id) {
                $people->push(User::find($a->user_id));
            } elseif ($a->role === 'admin') {
                $people = $people->merge(User::where('role', UserRole::Admin->value)->get());
            } elseif ($a->role === 'doctor') {
                $people = $people->merge(User::where('role', UserRole::Doctor->value)->get()->filter(fn ($u) => $this->access->canView($u, $patient)));
            } elseif ($a->role === 'health_coach') {
                $coachId = CoachingAssignment::active()->where('patient_id', $patient->id)->value('coach_id');
                $people = $people->merge(User::where('id', $coachId)->get());
            }
        }

        foreach ($people->filter()->unique('id')->reject(fn ($u) => $u->id === $author->id) as $user) {
            try {
                $this->dispatcher->send(new OutboundMessage(
                    templateKey: 'doctor.staff_note', category: 'transactional', user: $user,
                    subject: 'You have a new note in Zapmed', body: 'A colleague has sent you a note. Sign in to Zapmed to read it.',
                    meta: ['patient_note_id' => $note->id],
                ), ['email']);
            } catch (\Throwable $e) {
                Log::error('staff note: message failed', ['note' => $note->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
