<?php

namespace Zapmed\SparCore\Livewire;

use Livewire\Component;
use Zapmed\SparCore\Concerns\LogsSparActivity;
use Zapmed\SparCore\Models\SparConversation;
use Zapmed\SparCore\Models\SparPatient;

/**
 * Pharmacy-staff Health Coach INBOX (a single place to see every incoming
 * patient conversation for the staffer's store, instead of hunting per-patient
 * on PatientDetail).
 *
 * Two-pane: a scope-gated conversation list on the left; the existing
 * StaffCoachMessages thread on the right (reused verbatim — send + suggest +
 * PHI audit already live there). Selecting a conversation opens the thread,
 * which marks it read and clears its staff badge.
 *
 * Scope: conversations are restricted via SparConversation::visibleToCurrentActor()
 * (national-aware, mirrors SparPatient). Staff-only surface — group/super admins
 * keep the per-patient panel; this inbox is for the counter staff who reply.
 *
 * The unread badge (SUM staff_unread_count over the scoped conversations) is a
 * single indexed count and NEVER touches the encrypted message body.
 */
class StaffCoachInbox extends Component
{
    use LogsSparActivity;

    /** Currently open conversation (null = nothing selected yet). */
    public ?int $conversationId = null;

    /** Patient + pharmacy of the open conversation, passed to the thread pane. */
    public ?int $activePatientId = null;
    public ?int $activePharmacyId = null;

    /**
     * Open a conversation in the detail pane. Scope-gated: a staffer can only
     * open a conversation their actor may see (else 403). Mounting the thread
     * component marks it read + clears the staff badge.
     */
    public function select(int $conversationId): void
    {
        $conversation = SparConversation::visibleToCurrentActor()
            ->whereKey($conversationId)
            ->first();

        abort_unless($conversation !== null, 403);

        $this->conversationId = $conversation->id;
        $this->activePatientId = $conversation->spar_patient_id;
        $this->activePharmacyId = $conversation->spar_pharmacy_id;
    }

    /**
     * Scoped conversation list, most-recent activity first. Eager-loads the
     * patient for the name; snippet/label is derived without decrypting bodies.
     */
    public function getConversationsProperty()
    {
        return SparConversation::visibleToCurrentActor()
            ->with('patient')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    /** Badge count = unread patient messages waiting for this store's staff. */
    public function getUnreadTotalProperty(): int
    {
        return (int) SparConversation::visibleToCurrentActor()
            ->sum('staff_unread_count');
    }

    public function render()
    {
        return view('spar::livewire.staff-coach-inbox')
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
