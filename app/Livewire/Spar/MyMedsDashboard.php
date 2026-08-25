<?php

namespace App\Livewire\Spar;

use App\Models\SparDispenseRecord;
use App\Models\SparOrder;
use App\Models\SparPatient;
use App\Models\SparPrescriptionJourney;
use App\Services\SparOrderService;
use App\Traits\LogsSparActivity;
use Livewire\Component;

class MyMedsDashboard extends Component
{
    use LogsSparActivity;

    public bool $showDeliveryForm = false;
    public string $deliveryAddress = '';
    public string $deliveryCity = '';
    public string $deliveryPostalCode = '';
    public string $deliveryPhone = '';

    public function getSparPatientProperty(): ?SparPatient
    {
        return SparPatient::where('user_id', auth()->id())
            ->where('is_active', true)
            ->with(['pharmacy', 'journeys' => fn ($q) => $q->whereIn('status', ['active', 'renewal_due'])->latest()])
            ->first();
    }

    public function getActiveJourneyProperty(): ?SparPrescriptionJourney
    {
        return $this->sparPatient?->journeys->first();
    }

    public function getDispenseRecordsProperty()
    {
        if (!$this->activeJourney) return collect();

        return SparDispenseRecord::where('journey_id', $this->activeJourney->id)
            ->orderBy('dispense_number')
            ->get();
    }

    public function getNextDispenseProperty(): ?SparDispenseRecord
    {
        if (!$this->activeJourney) return null;

        return SparDispenseRecord::where('journey_id', $this->activeJourney->id)
            ->whereIn('status', ['upcoming', 'reminded'])
            ->orderBy('due_date')
            ->first();
    }

    public function getPendingOrderProperty(): ?SparOrder
    {
        if (!$this->sparPatient) return null;

        return SparOrder::where('spar_patient_id', $this->sparPatient->id)
            ->whereIn('status', ['requested', 'preparing', 'ready'])
            ->latest()
            ->first();
    }

    /**
     * Request collection from pharmacy.
     */
    public function requestCollection(): void
    {
        $dispense = $this->nextDispense;
        if (!$dispense) return;

        $service = new SparOrderService();
        $service->requestCollection($dispense);

        $this->logSparActivity('collection_requested', 'Patient requested collection', [
            'spar_patient_id' => $this->sparPatient->id,
            'dispense_id' => $dispense->id,
        ]);

        session()->flash('success', 'Collection requested! Your pharmacy will prepare your medication.');
    }

    /**
     * Show delivery form.
     */
    public function showDelivery(): void
    {
        $this->showDeliveryForm = true;
        // Pre-fill from user profile
        $user = auth()->user();
        $this->deliveryAddress = $user->address ?? '';
        $this->deliveryCity = $user->city ?? '';
        $this->deliveryPostalCode = $user->postal_code ?? '';
        $this->deliveryPhone = $user->phone ?? '';
    }

    /**
     * Request delivery.
     */
    public function requestDelivery(): void
    {
        $this->validate([
            'deliveryAddress' => 'required|string|max:255',
            'deliveryCity' => 'required|string|max:100',
            'deliveryPostalCode' => 'required|string|max:10',
            'deliveryPhone' => 'required|string|max:20',
        ]);

        $dispense = $this->nextDispense;
        if (!$dispense) return;

        $service = new SparOrderService();
        $service->requestDelivery(
            $dispense,
            $this->deliveryAddress,
            $this->deliveryCity,
            $this->deliveryPostalCode,
            $this->deliveryPhone
        );

        $this->logSparActivity('delivery_requested', 'Patient requested delivery', [
            'spar_patient_id' => $this->sparPatient->id,
            'dispense_id' => $dispense->id,
            'address' => $this->deliveryAddress,
        ]);

        $this->showDeliveryForm = false;
        session()->flash('success', 'Delivery requested! Your pharmacy will confirm the delivery date.');
    }

    public function render()
    {
        return view('livewire.spar.my-meds-dashboard')
            ->layout('layouts.spar-meds');
    }
}
