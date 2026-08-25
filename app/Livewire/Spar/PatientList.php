<?php

namespace App\Livewire\Spar;

use App\Models\SparPatient;
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

    public function getPharmacyProperty()
    {
        return auth()->user()->sparPharmacy;
    }

    public function render()
    {
        $patients = SparPatient::forPharmacy($this->pharmacy->id)
            ->with(['user', 'journeys' => fn ($q) => $q->where('status', 'active')])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('profile_code', 'like', "%{$this->search}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('first_name', 'like', "%{$this->search}%")
                            ->orWhere('last_name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->consentFilter !== 'all', fn ($q) => $q->where('consent_status', $this->consentFilter))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('livewire.spar.patient-list', ['patients' => $patients])
            ->layout('layouts.app');
    }
}
