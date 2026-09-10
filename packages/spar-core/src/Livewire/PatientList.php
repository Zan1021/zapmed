<?php

namespace Zapmed\SparCore\Livewire;

use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Models\SparPatient;
use Livewire\Component;
use Livewire\WithPagination;

class PatientList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $consentFilter = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Pharmacy scope for the current staff actor (via host identity provider).
     */
    public function getPharmacyIdProperty(): ?int
    {
        return app(SparIdentityProvider::class)->currentPharmacyId();
    }

    public function render()
    {
        $patients = SparPatient::visibleToCurrentActor()
            ->with(['journeys' => fn ($q) => $q->where('status', 'active')])
            ->when($this->search, function ($query) {
                // profile_code + contact are encrypted, so search the plaintext
                // identity fields we own (first/last name).
                $query->where(function ($q) {
                    $q->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%");
                });
            })
            ->when($this->consentFilter !== 'all', fn ($q) => $q->where('consent_status', $this->consentFilter))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('spar::livewire.patient-list', ['patients' => $patients])
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
