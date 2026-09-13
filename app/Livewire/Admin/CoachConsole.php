<?php

namespace App\Livewire\Admin;

use App\Enums\OfferStatus;
use App\Enums\TouchpointChannel;
use App\Enums\TouchpointKind;
use App\Enums\UserRole;
use App\Models\CoachingAssignment;
use App\Models\CoachingOffer;
use App\Models\User;
use App\Services\Coaching\CoachingService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Coach console (Task 5, specs/contro-rebuild/08 §2.4).
 *
 * A health coach sees ONLY their assigned patients; an admin sees all assignments and can (re)assign
 * coaches. From a selected patient the coach logs touchpoints and manages cross-sell offers.
 *
 * Scoping is enforced server-side (assignments query is filtered by the acting coach) — the route is
 * open to admin + health_coach, but a coach can never see another coach's patients.
 */
class CoachConsole extends Component
{
    public ?int $selectedPatientId = null;

    // Touchpoint form.
    public string $tpKind = 'check_in';
    public string $tpChannel = 'phone';
    public string $tpDirection = 'outbound';
    public string $tpSummary = '';
    public string $tpSentiment = '';

    // Offer form.
    public string $offerServiceLine = '';
    public string $offerNotes = '';

    // Admin-only (re)assignment.
    public string $assignCoachId = '';
    public string $assignReason = '';

    private function isAdmin(): bool
    {
        return auth()->user()?->role === UserRole::Admin;
    }

    public function selectPatient(int $patientId): void
    {
        // Guard: a coach may only open a patient they are actively assigned to.
        if (! $this->isAdmin() && ! $this->assignedPatientIds()->contains($patientId)) {
            return;
        }

        $this->selectedPatientId = $patientId;
        $this->reset(['tpSummary', 'tpSentiment', 'offerServiceLine', 'offerNotes']);
    }

    public function closePatient(): void
    {
        $this->reset(['selectedPatientId', 'tpSummary', 'tpSentiment', 'offerServiceLine', 'offerNotes']);
    }

    /** IDs of patients actively assigned to the acting coach (empty for admins — they use the full list). */
    private function assignedPatientIds(): Collection
    {
        return CoachingAssignment::where('coach_id', auth()->id())
            ->whereNull('ended_at')
            ->pluck('patient_id');
    }

    public function logTouchpoint(CoachingService $coaching): void
    {
        $patient = $this->guardedPatient();
        if (! $patient) {
            return;
        }

        $this->validate([
            'tpKind' => 'required|string',
            'tpChannel' => 'required|string',
            'tpDirection' => 'required|in:outbound,inbound',
            'tpSummary' => 'nullable|string|max:2000',
        ]);

        $coaching->logTouchpoint(
            $patient,
            auth()->user(),
            $this->tpKind,
            $this->tpChannel,
            $this->tpDirection,
            ['summary' => $this->tpSummary ?: null, 'sentiment' => $this->tpSentiment ?: null, 'successful' => true],
        );

        session()->flash('message', 'Touchpoint logged.');
        $this->reset(['tpSummary', 'tpSentiment']);
    }

    public function makeOffer(CoachingService $coaching): void
    {
        $patient = $this->guardedPatient();
        if (! $patient) {
            return;
        }

        $this->validate(['offerServiceLine' => 'required|string|max:120']);

        $coaching->makeOffer($patient, auth()->user(), $this->offerServiceLine, null, $this->offerNotes ?: null);
        session()->flash('message', 'Offer created.');
        $this->reset(['offerServiceLine', 'offerNotes']);
    }

    public function acceptOffer(int $offerId, CoachingService $coaching): void
    {
        $offer = $this->guardedOffer($offerId);
        if ($offer) {
            $coaching->acceptOffer($offer);
            session()->flash('message', 'Offer accepted.');
        }
    }

    public function declineOffer(int $offerId, CoachingService $coaching): void
    {
        $offer = $this->guardedOffer($offerId);
        if ($offer) {
            $coaching->declineOffer($offer);
            session()->flash('message', 'Offer declined.');
        }
    }

    public function withdrawOffer(int $offerId, CoachingService $coaching): void
    {
        $offer = $this->guardedOffer($offerId);
        if ($offer) {
            $coaching->withdrawOffer($offer);
            session()->flash('message', 'Offer withdrawn.');
        }
    }

    /** Admin-only: assign a coach to the selected patient. */
    public function assignCoach(CoachingService $coaching): void
    {
        if (! $this->isAdmin() || $this->selectedPatientId === null) {
            return;
        }

        $this->validate(['assignCoachId' => 'required']);
        $patient = User::find($this->selectedPatientId);
        $coach = User::find((int) $this->assignCoachId);

        if ($patient && $coach) {
            $coaching->assign($patient, $coach, $this->assignReason ?: null, auth()->id());
            session()->flash('message', 'Coach assigned.');
            $this->reset(['assignCoachId', 'assignReason']);
        }
    }

    /** Resolve the selected patient with the same scoping guard used everywhere. */
    private function guardedPatient(): ?User
    {
        if ($this->selectedPatientId === null) {
            return null;
        }
        if (! $this->isAdmin() && ! $this->assignedPatientIds()->contains($this->selectedPatientId)) {
            return null;
        }

        return User::find($this->selectedPatientId);
    }

    private function guardedOffer(int $offerId): ?CoachingOffer
    {
        $offer = CoachingOffer::find($offerId);
        if (! $offer) {
            return null;
        }
        // A coach can only touch offers for their own assigned patients.
        if (! $this->isAdmin() && ! $this->assignedPatientIds()->contains($offer->patient_id)) {
            return null;
        }

        return $offer;
    }

    /** @return Collection<int,CoachingAssignment> the assignments shown in the left list. */
    #[Computed]
    public function assignments(): Collection
    {
        return CoachingAssignment::query()
            ->with(['patient:id,first_name,last_name,member_number', 'coach:id,first_name,last_name'])
            ->whereNull('ended_at')
            ->when(! $this->isAdmin(), fn ($q) => $q->where('coach_id', auth()->id()))
            ->latest('started_at')
            ->get();
    }

    #[Computed]
    public function selectedPatient(): ?User
    {
        return $this->selectedPatientId ? User::find($this->selectedPatientId) : null;
    }

    #[Computed]
    public function touchpoints(): Collection
    {
        if ($this->selectedPatientId === null) {
            return collect();
        }

        return \App\Models\CoachingTouchpoint::where('patient_id', $this->selectedPatientId)
            ->with('coach:id,first_name,last_name')
            ->latest('occurred_at')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function offers(): Collection
    {
        if ($this->selectedPatientId === null) {
            return collect();
        }

        return CoachingOffer::where('patient_id', $this->selectedPatientId)
            ->latest()
            ->get();
    }

    #[Computed]
    public function coaches(): Collection
    {
        return User::query()
            ->whereIn('role', [UserRole::HealthCoach->value, UserRole::Admin->value])
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);
    }

    public function render()
    {
        return view('livewire.admin.coach-console', [
            'isAdmin' => $this->isAdmin(),
            'touchpointKinds' => TouchpointKind::cases(),
            'touchpointChannels' => TouchpointChannel::cases(),
            'offerStatuses' => OfferStatus::cases(),
        ]);
    }
}
