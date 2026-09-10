<?php

namespace Zapmed\SparCore\Livewire;

use Zapmed\SparCore\Concerns\LogsSparActivity;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Services\MessagingDispatcher;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pharmacist capture interface — Mode B onboarding (spec FR-6.3).
 *
 * Store-scoped. Lists patients imported without contact details
 * (onboarding_status = awaiting_contact) and lets the pharmacist capture
 * name/surname + cellphone/email and a consent basis, activate the patient,
 * and send the tracker link. Attaches to already-imported medication history
 * via Profile Code (the SparPatient row already exists).
 */
class PharmacistCapture extends Component
{
    use WithPagination;
    use LogsSparActivity;

    public ?int $editingId = null;

    public string $firstName = '';
    public string $lastName = '';
    public string $cellphone = '';
    public string $email = '';
    public bool $consentConfirmed = false;
    public string $consentChannel = 'in_store';

    public string $search = '';
    public string $flash = '';

    protected function rules(): array
    {
        return [
            'firstName' => 'required|string|max:100',
            'lastName' => 'required|string|max:100',
            'cellphone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    private function pharmacyId(): int
    {
        // Store scope enforced by EnsureSparPharmacyScope; admins pass through.
        // Prefer an explicit scoped id set by middleware, else the host identity
        // provider's current pharmacy scope.
        return (int) (request('_spar_pharmacy_id')
            ?? app(SparIdentityProvider::class)->currentPharmacyId());
    }

    public function edit(int $patientId): void
    {
        $patient = $this->scopedPatient($patientId);

        $this->editingId = $patient->id;
        $this->firstName = $patient->first_name ?? '';
        $this->lastName = $patient->last_name ?? '';
        $this->cellphone = $patient->cellphone ?? '';
        $this->email = $patient->email ?? '';
        $this->consentConfirmed = false;
        $this->consentChannel = 'in_store';
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'firstName', 'lastName', 'cellphone', 'email', 'consentConfirmed']);
        $this->resetValidation();
    }

    /**
     * Save captured identity. Requires name + at least one contact channel
     * (spec FR-6.4). Optionally records consent + sends the link if confirmed.
     */
    public function save(): void
    {
        $this->validate();

        if (empty(trim($this->cellphone)) && empty(trim($this->email))) {
            $this->addError('cellphone', 'Enter a cellphone or an email — at least one is required to send the tracker link.');
            return;
        }

        $patient = $this->scopedPatient($this->editingId);

        $patient->update([
            'first_name' => trim($this->firstName),
            'last_name' => trim($this->lastName),
            'cellphone' => trim($this->cellphone) ?: null,
            'email' => trim($this->email) ?: null,
        ]);

        // Record consent if the pharmacist confirmed the patient agreed in-store.
        // This is the preliminary basis; the binding grant is the patient's own
        // tap on the consent screen when they open the link (double opt-in).
        if ($this->consentConfirmed) {
            $patient->optIn($this->consentChannel, [
                'source' => 'pharmacist:' . auth()->id(),
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ]);
        }

        $patient->refreshOnboardingStatus();

        $this->logSparActivity('patient_captured', 'Pharmacist captured patient contact', [
            'spar_patient_id' => $patient->id,
            'consent_confirmed' => $this->consentConfirmed,
        ]);

        // Send the tracker link if the patient is now contactable + consented.
        if ($patient->isOnboarded()) {
            $this->sendTrackerLink($patient);
            $this->flash = "Saved and tracker link sent to {$patient->display_name}.";
        } else {
            $this->flash = "Saved. {$patient->display_name} is contactable; link will send once consent is granted.";
        }

        $this->cancel();
    }

    private function sendTrackerLink(SparPatient $patient): void
    {
        $ttl = (int) config('spar.link.ttl_minutes', 60 * 24 * 7);
        $link = URL::temporarySignedRoute('spar.track', now()->addMinutes($ttl), ['patient' => $patient->id]);

        app(MessagingDispatcher::class)->send($patient, [
            'subject' => config('spar.branding.name', 'SPAR Pharmacy') . ' — your medication tracker',
            'body' => "Hi {$patient->first_name}, tap the link to view and manage your chronic medication with "
                . config('spar.branding.name', 'SPAR Pharmacy') . '.',
            'link' => $link,
        ]);
    }

    private function scopedPatient(int $id): SparPatient
    {
        return SparPatient::where('id', $id)
            ->where('spar_pharmacy_id', $this->pharmacyId())
            ->firstOrFail();
    }

    public function render()
    {
        $patients = SparPatient::forPharmacy($this->pharmacyId())
            ->where('onboarding_status', 'awaiting_contact')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('spar::livewire.pharmacist-capture', ['patients' => $patients])
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
