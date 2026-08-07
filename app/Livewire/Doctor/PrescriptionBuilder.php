<?php

namespace App\Livewire\Doctor;

use App\Mail\PrescriptionReady;
use App\Models\Consultation;
use App\Models\Medication;
use App\Models\Prescription;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PrescriptionBuilder extends Component
{
    public Consultation $consultation;

    // Medication search
    public string $search = '';
    public bool $showResults = false;

    // Current item being added
    public ?int $selectedMedicationId = null;
    public string $medicationName = '';
    public string $medicationForm = '';
    public string $medicationStrength = '';
    public int $medicationPrice = 0; // price in cents
    public string $dosage = '';
    public string $frequency = 'once daily';
    public string $route = 'oral';
    public ?int $durationDays = null;
    public int $quantity = 0;
    public string $instructions = '';
    public bool $substitutionAllowed = true;
    public bool $isCustomMedication = false;
    public string $customPrice = ''; // for custom medications

    // Prescription items (array of items before saving)
    public array $items = [];

    // Prescription settings
    public bool $isChronic = false;
    public int $repeats = 1;
    public string $pharmacistNotes = '';

    // Signed state
    public bool $prescriptionSigned = false;
    public ?int $signedPrescriptionId = null;

    public function mount(Consultation $consultation): void
    {
        // Ensure the authenticated doctor owns this consultation
        if ($consultation->doctor_id !== Auth::id()) {
            abort(403);
        }

        $this->consultation = $consultation->load(['patient.patientProfile.allergies']);
    }

    #[Computed]
    public function searchResults(): array
    {
        if (strlen($this->search) < 2) {
            return [];
        }

        return Medication::active()
            ->search($this->search)
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get total prescription cost.
     */
    #[Computed]
    public function totalCost(): int
    {
        return collect($this->items)->sum('line_total');
    }

    public function updatedSearch(): void
    {
        $this->showResults = strlen($this->search) >= 2;
    }

    /**
     * Select a medication from search results.
     */
    public function selectMedication(int $medicationId): void
    {
        $medication = Medication::find($medicationId);

        if (!$medication) {
            return;
        }

        $this->selectedMedicationId = $medication->id;
        $this->medicationName = $medication->name;
        $this->medicationForm = $medication->form;
        $this->medicationStrength = $medication->strength;
        $this->medicationPrice = $medication->price;
        $this->isCustomMedication = false;
        $this->showResults = false;
        $this->search = '';
    }

    /**
     * Enable custom medication entry.
     */
    public function addCustomMedication(): void
    {
        $this->isCustomMedication = true;
        $this->selectedMedicationId = null;
        $this->medicationName = '';
        $this->medicationForm = 'tablet';
        $this->medicationStrength = '';
        $this->medicationPrice = 0;
        $this->customPrice = '';
        $this->showResults = false;
        $this->search = '';
    }

    /**
     * Add the current item to the prescription items list.
     */
    public function addItem(): void
    {
        $this->validate([
            'medicationName' => 'required|string|max:255',
            'medicationForm' => 'required|string|max:50',
            'medicationStrength' => 'required|string|max:50',
            'dosage' => 'required|string|max:255',
            'frequency' => 'required|string',
            'route' => 'required|string|max:30',
            'quantity' => 'required|integer|min:1',
        ]);

        // Calculate price
        $unitPrice = $this->medicationPrice;
        if ($this->isCustomMedication && $this->customPrice) {
            $unitPrice = (int) round((float) $this->customPrice * 100);
        }
        $lineTotal = $unitPrice * $this->quantity;

        $this->items[] = [
            'medication_id' => $this->selectedMedicationId,
            'medication_name' => $this->medicationName,
            'form' => $this->medicationForm,
            'strength' => $this->medicationStrength,
            'dosage' => $this->dosage,
            'frequency' => $this->frequency,
            'route' => $this->route,
            'duration_days' => $this->durationDays,
            'quantity' => $this->quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'instructions' => $this->instructions,
            'substitution_allowed' => $this->substitutionAllowed,
        ];

        $this->resetItemForm();
    }

    /**
     * Remove an item from the list.
     */
    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * Sign and finalize the prescription.
     * Creates a pending medication payment for the patient.
     */
    public function signPrescription(): void
    {
        if (empty($this->items)) {
            $this->addError('items', 'Add at least one medication before signing.');
            return;
        }

        $totalAmount = collect($this->items)->sum('line_total');

        // Pre-fill delivery from patient's address
        $patient = $this->consultation->patient;

        $prescription = Prescription::create([
            'consultation_id' => $this->consultation->id,
            'patient_id' => $this->consultation->patient_id,
            'doctor_id' => Auth::id(),
            'status' => 'signed',
            'diagnosis' => $this->consultation->diagnosis,
            'notes' => $this->pharmacistNotes ?: null,
            'total_amount' => $totalAmount,
            'payment_status' => 'unpaid',
            'pharmacy_status' => 'pending',
            'delivery_address' => $patient->address,
            'delivery_city' => $patient->city,
            'delivery_province' => $patient->province,
            'delivery_postal_code' => $patient->postal_code,
            'delivery_phone' => $patient->phone,
            'is_chronic' => $this->isChronic,
            'repeats' => $this->isChronic ? $this->repeats : 0,
            'signed_at' => now(),
        ]);

        foreach ($this->items as $item) {
            $prescription->items()->create([
                'medication_id' => $item['medication_id'],
                'medication_name' => $item['medication_name'],
                'strength' => $item['strength'],
                'form' => $item['form'],
                'dosage' => $item['dosage'],
                'frequency' => $item['frequency'],
                'route' => $item['route'],
                'duration_days' => $item['duration_days'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
                'instructions' => $item['instructions'] ?: null,
                'substitution_allowed' => $item['substitution_allowed'],
            ]);
        }

        // Create a payment record for medication
        \App\Models\Payment::create([
            'patient_id' => $this->consultation->patient_id,
            'provider' => 'payfast',
            'amount' => $totalAmount,
            'currency' => 'ZAR',
            'status' => 'pending',
            'description' => "Medication - {$prescription->reference}",
        ]);

        $this->prescriptionSigned = true;
        $this->signedPrescriptionId = $prescription->id;

        // Notify patient that medication payment is required
        $prescription->loadMissing(['items', 'doctor', 'patient']);
        Mail::to($prescription->patient)->queue(new PrescriptionReady($prescription));
    }

    /**
     * Cancel and go back.
     */
    public function cancel(): void
    {
        $this->redirect(route('doctor.consultation', $this->consultation->appointment_id), navigate: true);
    }

    /**
     * Reset the item form fields.
     */
    private function resetItemForm(): void
    {
        $this->selectedMedicationId = null;
        $this->medicationName = '';
        $this->medicationForm = '';
        $this->medicationStrength = '';
        $this->medicationPrice = 0;
        $this->customPrice = '';
        $this->dosage = '';
        $this->frequency = 'once daily';
        $this->route = 'oral';
        $this->durationDays = null;
        $this->quantity = 0;
        $this->instructions = '';
        $this->substitutionAllowed = true;
        $this->isCustomMedication = false;
    }

    public function render()
    {
        return view('livewire.doctor.prescription-builder')
            ->layout('layouts.app');
    }
}
