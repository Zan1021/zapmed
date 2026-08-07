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

    public function render()
    {
        return view('livewire.patient.my-prescriptions')
            ->layout('layouts.app');
    }
}
