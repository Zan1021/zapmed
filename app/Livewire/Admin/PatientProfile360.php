<?php

namespace App\Livewire\Admin;

use App\Services\Crm\Patient360;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Patient 360 (Task 3, specs/contro-rebuild/08 §2.1).
 *
 * Cross-field patient search + a single-screen aggregate of everything ops needs about one patient:
 * profile, CRM lead + funnel timeline, risk score, active flags, notes, recent orders/payments/
 * consults, and lifetime value. Read-only.
 */
class PatientProfile360 extends Component
{
    #[Url]
    public string $q = '';

    #[Url]
    public ?int $patientId = null;

    public function updatedQ(): void
    {
        // New search clears the open patient so results and detail don't disagree.
        $this->patientId = null;
    }

    public function view(int $id): void
    {
        $this->patientId = $id;
    }

    public function clear(): void
    {
        $this->reset(['q', 'patientId']);
    }

    #[Computed]
    public function results()
    {
        if (trim($this->q) === '' || $this->patientId !== null) {
            return collect();
        }

        return app(Patient360::class)->search($this->q);
    }

    /** @return array<string,mixed>|null */
    #[Computed]
    public function dossier(): ?array
    {
        if ($this->patientId === null) {
            return null;
        }

        return app(Patient360::class)->assembleById($this->patientId);
    }

    public function render()
    {
        return view('livewire.admin.patient-profile-360');
    }
}
