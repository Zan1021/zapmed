<?php

namespace Zapmed\SparCore\Livewire;

use Livewire\Component;
use Zapmed\SparCore\Models\SparConversation;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparProductSuggestion;
use Zapmed\SparCore\Services\SparCoachService;
use Zapmed\SparCore\Services\SparPatientSession;
use Zapmed\SparCore\Services\SparPatientView;

/**
 * Patient side of the Health Coach (spec spar-health-coach FR-3). Embedded in
 * the MyMedsTracker dashboard step — reachable ONLY after the consent gate.
 *
 * No login: the patient is the session patient (SparPatientSession). Consent is
 * enforced as defence-in-depth (the tracker already gates the whole surface).
 */
class PatientCoachMessages extends Component
{
    public string $body = '';
    public string $error = '';

    private function session(): SparPatientSession
    {
        return app(SparPatientSession::class);
    }

    private function coachService(): SparCoachService
    {
        return app(SparCoachService::class);
    }

    public function mount(): void
    {
        if (! $this->session()->patientId()) {
            abort(403);
        }

        if ($this->consented()) {
            $this->coachService()->markRead($this->conversation, 'patient');
        }
    }

    private function primary(): ?SparPatient
    {
        $patient = $this->session()->patient();

        return $patient ? app(SparPatientView::class)->primary($patient) : null;
    }

    private function consented(): bool
    {
        return (bool) $this->primary()?->hasConsented();
    }

    /**
     * The conversation for the profile's primary member at their pharmacy.
     */
    public function getConversationProperty(): ?SparConversation
    {
        $primary = $this->primary();
        if (! $primary) {
            return null;
        }

        $pharmacyId = (int) ($primary->spar_pharmacy_id
            ?? optional(app(SparPatientView::class)->journeys($primary)->first())->spar_pharmacy_id);

        if (! $pharmacyId) {
            return null;
        }

        return $this->coachService()->openConversation($primary, $pharmacyId);
    }

    public function getMessagesProperty()
    {
        $conversation = $this->conversation;

        return $conversation
            ? $conversation->messages()->with('productSuggestion')->chronological()->get()
            : collect();
    }

    public function send(): void
    {
        if (! $this->consented()) {
            $this->error = 'Please give consent before messaging your pharmacy.';

            return;
        }

        $this->validate(['body' => 'required|string|max:2000']);

        $conversation = $this->conversation;
        if (! $conversation) {
            $this->error = 'No pharmacy is linked to your profile yet.';

            return;
        }

        $this->coachService()->postPatientMessage($conversation, trim($this->body));
        $this->body = '';
        $this->error = '';
    }

    public function accept(int $suggestionId): void
    {
        $suggestion = $this->resolveSuggestion($suggestionId);
        if ($suggestion) {
            $this->coachService()->acceptSuggestion($suggestion);
        }
    }

    public function decline(int $suggestionId): void
    {
        $suggestion = $this->resolveSuggestion($suggestionId);
        if ($suggestion) {
            $this->coachService()->declineSuggestion($suggestion);
        }
    }

    /**
     * Resolve a suggestion, guarding that it belongs to THIS patient's
     * conversation (a patient can only act on their own thread).
     */
    private function resolveSuggestion(int $suggestionId): ?SparProductSuggestion
    {
        $conversation = $this->conversation;
        if (! $conversation) {
            return null;
        }

        return SparProductSuggestion::where('id', $suggestionId)
            ->where('spar_conversation_id', $conversation->id)
            ->first();
    }

    public function render()
    {
        return view('spar::livewire.patient-coach-messages');
    }
}
