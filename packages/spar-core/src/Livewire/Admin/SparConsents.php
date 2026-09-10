<?php

namespace Zapmed\SparCore\Livewire\Admin;

use Zapmed\SparCore\Models\SparConsent;
use Zapmed\SparCore\Models\SparPatient;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin consent section (spec FR-10). List with green/amber/red status +
 * per-patient detail showing the full POPIA consent audit trail. Shared
 * screen — renders identically in standalone and integrated hosts.
 */
class SparConsents extends Component
{
    use WithPagination;

    public string $statusFilter = 'all'; // all | opted_in | pending | opted_out
    public ?int $viewingId = null;

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function view(int $patientId): void
    {
        $this->viewingId = $patientId;
    }

    public function closeDetail(): void
    {
        $this->viewingId = null;
    }

    public function getViewingPatientProperty(): ?SparPatient
    {
        if (!$this->viewingId) {
            return null;
        }

        // Scope-guarded: only a patient the current actor may see resolves.
        return SparPatient::visibleToCurrentActor()
            ->with(['pharmacy', 'consents' => fn ($q) => $q->latest()])
            ->find($this->viewingId);
    }

    /**
     * Dependants under the same profile (encrypted profile_code → PHP filter).
     */
    public function getDependantsProperty()
    {
        $patient = $this->viewingPatient;
        if (!$patient) {
            return collect();
        }

        return SparPatient::where('spar_pharmacy_id', $patient->spar_pharmacy_id)
            ->where('id', '!=', $patient->id)
            ->get()
            ->filter(fn (SparPatient $p) => $p->profile_code === $patient->profile_code)
            ->values();
    }

    public function getStatsProperty(): array
    {
        return [
            'consented' => SparPatient::visibleToCurrentActor()->where('consent_status', 'opted_in')->count(),
            'pending' => SparPatient::visibleToCurrentActor()->where('consent_status', 'pending')->count(),
            'opted_out' => SparPatient::visibleToCurrentActor()->where('consent_status', 'opted_out')->count(),
        ];
    }

    public function render()
    {
        $patients = SparPatient::visibleToCurrentActor()
            ->with('pharmacy')
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('consent_status', $this->statusFilter))
            ->orderByDesc('updated_at')
            ->paginate(25);

        return view('spar::livewire.admin.spar-consents', [
            'patients' => $patients,
            'stats' => $this->stats,
        ])->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
