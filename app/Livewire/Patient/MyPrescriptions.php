<?php

namespace App\Livewire\Patient;

use App\Models\Prescription;
use App\Services\PayFastService;
use App\Services\PharmacyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MyPrescriptions extends Component
{
    public ?Prescription $viewingPrescription = null;

    // Delivery address form (for medication payment)
    public bool $showPaymentForm = false;
    public ?int $payingPrescriptionId = null;
    public string $deliveryAddress = '';
    public string $deliveryCity = '';
    public string $deliveryProvince = '';
    public string $deliveryPostalCode = '';
    public string $deliveryPhone = '';
    public string $deliveryInstructions = '';

    /**
     * View prescription details.
     */
    public function viewPrescription(int $prescriptionId): void
    {
        $this->viewingPrescription = Prescription::where('patient_id', Auth::id())
            ->with(['items', 'doctor'])
            ->findOrFail($prescriptionId);
    }

    public function closeView(): void
    {
        $this->viewingPrescription = null;
    }

    /**
     * Request a repeat of a chronic prescription.
     * Creates a new prescription with the same items at the same prices.
     */
    public function requestRepeat(int $prescriptionId): void
    {
        $original = Prescription::where('patient_id', Auth::id())
            ->where('is_chronic', true)
            ->where('payment_status', 'paid')
            ->with('items')
            ->findOrFail($prescriptionId);

        // Check repeats remaining
        if ($original->repeats > 0 && $original->repeats_used >= $original->repeats) {
            session()->flash('error', 'No repeats remaining on this prescription. Please book a consultation for a new script.');
            return;
        }

        // Create a new prescription as a repeat
        $repeat = Prescription::create([
            'consultation_id' => $original->consultation_id,
            'patient_id' => Auth::id(),
            'doctor_id' => $original->doctor_id,
            'status' => 'signed',
            'diagnosis' => $original->diagnosis,
            'notes' => $original->notes,
            'total_amount' => $original->total_amount,
            'payment_status' => 'unpaid',
            'pharmacy_status' => 'pending',
            'delivery_address' => $original->delivery_address,
            'delivery_city' => $original->delivery_city,
            'delivery_province' => $original->delivery_province,
            'delivery_postal_code' => $original->delivery_postal_code,
            'delivery_phone' => $original->delivery_phone,
            'delivery_instructions' => $original->delivery_instructions,
            'is_chronic' => true,
            'repeats' => 0, // Repeat of a repeat doesn't get more repeats
            'signed_at' => now(),
        ]);

        // Copy items
        foreach ($original->items as $item) {
            $repeat->items()->create([
                'medication_id' => $item->medication_id,
                'medication_name' => $item->medication_name,
                'strength' => $item->strength,
                'form' => $item->form,
                'dosage' => $item->dosage,
                'frequency' => $item->frequency,
                'route' => $item->route,
                'duration_days' => $item->duration_days,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
                'instructions' => $item->instructions,
                'substitution_allowed' => $item->substitution_allowed,
            ]);
        }

        // Increment repeats used on original
        $original->increment('repeats_used');

        // Redirect to payment for the repeat
        $this->viewingPrescription = null;
        $this->startPayment($repeat->id);

        session()->flash('message', 'Repeat prescription created. Please confirm delivery and pay to dispatch.');
    }

    /**
     * Start medication payment flow — show delivery address form.
     */
    public function startPayment(int $prescriptionId): void
    {
        $prescription = Prescription::where('patient_id', Auth::id())
            ->where('payment_status', 'unpaid')
            ->findOrFail($prescriptionId);

        $this->payingPrescriptionId = $prescription->id;

        // Pre-fill from prescription (which got it from patient profile)
        $this->deliveryAddress = $prescription->delivery_address ?? Auth::user()->address ?? '';
        $this->deliveryCity = $prescription->delivery_city ?? Auth::user()->city ?? '';
        $this->deliveryProvince = $prescription->delivery_province ?? Auth::user()->province ?? '';
        $this->deliveryPostalCode = $prescription->delivery_postal_code ?? Auth::user()->postal_code ?? '';
        $this->deliveryPhone = $prescription->delivery_phone ?? Auth::user()->phone ?? '';
        $this->deliveryInstructions = $prescription->delivery_instructions ?? '';

        $this->showPaymentForm = true;
    }

    /**
     * Confirm delivery address and proceed to PayFast payment.
     */
    public function confirmAndPay(): void
    {
        $this->validate([
            'deliveryAddress' => 'required|string|max:500',
            'deliveryCity' => 'required|string|max:100',
            'deliveryProvince' => 'required|string|max:50',
            'deliveryPostalCode' => 'required|string|max:10',
            'deliveryPhone' => 'required|string|max:20',
        ]);

        $prescription = Prescription::where('patient_id', Auth::id())
            ->findOrFail($this->payingPrescriptionId);

        // Save delivery address
        $prescription->update([
            'delivery_address' => $this->deliveryAddress,
            'delivery_city' => $this->deliveryCity,
            'delivery_province' => $this->deliveryProvince,
            'delivery_postal_code' => $this->deliveryPostalCode,
            'delivery_phone' => $this->deliveryPhone,
            'delivery_instructions' => $this->deliveryInstructions ?: null,
            'payment_status' => 'pending',
        ]);

        // Create payment record
        $payment = \App\Models\Payment::create([
            'patient_id' => Auth::id(),
            'provider' => 'payfast',
            'amount' => $prescription->total_amount,
            'currency' => 'ZAR',
            'status' => 'pending',
            'description' => "Medication - {$prescription->reference}",
        ]);

        // Store prescription ID in session for webhook to link back
        session(['medication_payment' => [
            'prescription_id' => $prescription->id,
            'payment_reference' => $payment->reference,
        ]]);

        $this->redirect(route('payment.checkout', $payment->reference));
    }

    public function cancelPayment(): void
    {
        $this->showPaymentForm = false;
        $this->payingPrescriptionId = null;
    }

    public function getPrescriptionsProperty()
    {
        return Prescription::where('patient_id', Auth::id())
            ->with(['items', 'doctor'])
            ->latest()
            ->get();
    }

    /**
     * Get prescriptions that are eligible for repeat.
     */
    public function getRepeatEligibleProperty()
    {
        return Prescription::where('patient_id', Auth::id())
            ->where('is_chronic', true)
            ->where('payment_status', 'paid')
            ->where(function ($q) {
                $q->where('repeats', 0) // unlimited repeats
                  ->orWhereColumn('repeats_used', '<', 'repeats');
            })
            ->with('items')
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.patient.my-prescriptions')
            ->layout('layouts.app');
    }
}
