<?php

namespace App\Livewire\Admin;

use App\Services\Crm\CrmAiService;
use App\Services\Crm\Patient360;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Patient 360 (Task 3, specs/contro-rebuild/08 §2.1).
 *
 * Cross-field patient search + a single-screen aggregate of everything ops needs about one patient:
 * profile, CRM lead + funnel timeline, risk score, active flags, notes, recent orders/payments/
 * consults, and lifetime value. Read-only.
 *
 * Task 8 wire-in: an on-demand AI patient summary (CrmAiService::summarisePatient) — degrades to a
 * templated digest with no OpenAI key.
 */
class PatientProfile360 extends Component
{
    #[Url]
    public string $q = '';

    #[Url]
    public ?int $patientId = null;

    /** AI summary state (filled on demand by summarise()). */
    public ?string $aiSummary = null;
    public ?string $aiSummaryBy = null;   // 'ai' | 'rules'

    public function updatedQ(): void
    {
        // New search clears the open patient so results and detail don't disagree.
        $this->patientId = null;
        $this->reset(['aiSummary', 'aiSummaryBy']);
    }

    public function view(int $id): void
    {
        $this->patientId = $id;
        $this->reset(['aiSummary', 'aiSummaryBy']);
    }

    public function clear(): void
    {
        $this->reset(['q', 'patientId', 'aiSummary', 'aiSummaryBy']);
    }

    /** Generate (or regenerate) the AI patient summary for the open patient. */
    public function summarise(): void
    {
        if ($this->patientId === null) {
            return;
        }

        $patient = User::find($this->patientId);
        if (! $patient) {
            session()->flash('error', 'Patient not found.');
            return;
        }

        $result = app(CrmAiService::class)->summarisePatient($patient);
        $this->aiSummary = $result['summary'];
        $this->aiSummaryBy = $result['generated_by'];
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
