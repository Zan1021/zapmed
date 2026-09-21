<?php

namespace Zapmed\SparCore\Livewire;

use Livewire\Component;
use Zapmed\SparCore\Concerns\LogsSparActivity;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Models\SparConversation;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparProductSuggestion;
use Zapmed\SparCore\Services\SparCoachService;
use Zapmed\SparCore\Services\SparPatientView;

/**
 * Staff side of the Health Coach (spec spar-health-coach FR-4). Embedded on the
 * PatientDetail page — the one place a staffer can ACT on a patient (send a
 * message, suggest a product), keeping PatientDetail itself a read-only mirror.
 *
 * Scope-gated on mount (national-aware) + PHI-read audited. The coach is the
 * existing pharmacy-staff role; author identity is snapshotted onto each message.
 */
class StaffCoachMessages extends Component
{
    use LogsSparActivity;

    public int $patientId;
    public int $pharmacyId;

    public string $body = '';

    // suggest-a-product mini-form
    public bool $showSuggest = false;
    public string $productName = '';
    public ?string $productPrice = null; // rands, as typed
    public string $productNote = '';

    public function mount(int $patientId, ?int $pharmacyId = null): void
    {
        $patient = SparPatient::findOrFail($patientId);

        // Scope gate (national-aware) — a staffer may only open a patient they
        // are allowed to see (AC-8).
        $visible = SparPatient::visibleToCurrentActor()->whereKey($patient->id)->first();
        abort_unless($visible !== null, 403);

        $primary = app(SparPatientView::class)->primary($patient);
        $this->patientId = $primary->id;

        // Which pharmacy does this coach speak for? A pharmacy-scoped actor uses
        // their own; a group/super actor falls back to the patient's home/most
        // recent pharmacy so the conversation still binds somewhere sensible.
        $this->pharmacyId = (int) ($pharmacyId
            ?? app(SparIdentityProvider::class)->currentPharmacyId()
            ?? $primary->spar_pharmacy_id
            ?? optional($this->firstJourneyPharmacy($primary))->id);

        // Opening the thread is a PHI read.
        $this->logSparActivity('patient_access', 'Staff opened coach messages', [
            'spar_patient_id' => $primary->id,
            'reason' => 'coach_thread',
        ]);

        $this->coachService()->markRead($this->conversation(), 'staff');
    }

    private function firstJourneyPharmacy(SparPatient $primary)
    {
        return app(SparPatientView::class)->journeys($primary)->first()?->pharmacy;
    }

    private function coachService(): SparCoachService
    {
        return app(SparCoachService::class);
    }

    public function getConversationProperty(): SparConversation
    {
        return $this->coachService()->openConversation(
            SparPatient::findOrFail($this->patientId),
            $this->pharmacyId
        );
    }

    private function conversation(): SparConversation
    {
        return $this->conversation;
    }

    public function getMessagesProperty()
    {
        return $this->conversation->messages()->with('productSuggestion')->chronological()->get();
    }

    private function author(): array
    {
        $user = auth()->user();
        $role = $user?->role ?? null;
        $roleValue = is_object($role) && property_exists($role, 'value') ? $role->value : $role;

        return [
            'id' => $user?->id,
            'name' => $user?->name ?? ($user?->email ?? 'Pharmacy staff'),
            'role' => $roleValue,
        ];
    }

    public function send(): void
    {
        $this->validate(['body' => 'required|string|max:2000']);

        $this->coachService()->postStaffMessage($this->conversation, trim($this->body), $this->author());
        $this->body = '';
    }

    public function toggleSuggest(): void
    {
        $this->showSuggest = ! $this->showSuggest;
        $this->reset(['productName', 'productPrice', 'productNote']);
    }

    public function suggest(): void
    {
        $this->validate([
            'productName' => 'required|string|max:255',
            'productPrice' => 'nullable|numeric|min:0',
            'productNote' => 'nullable|string|max:255',
        ]);

        $priceCents = ($this->productPrice === null || $this->productPrice === '')
            ? null
            : (int) round(((float) $this->productPrice) * 100);

        $this->coachService()->suggestProduct(
            $this->conversation,
            $this->author(),
            trim($this->productName),
            $priceCents,
            $this->productNote !== '' ? trim($this->productNote) : null,
        );

        $this->reset(['productName', 'productPrice', 'productNote']);
        $this->showSuggest = false;
    }

    public function render()
    {
        return view('spar::livewire.staff-coach-messages');
    }
}
