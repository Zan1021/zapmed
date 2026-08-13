<?php

namespace App\Livewire\Patient;

use App\Models\Prescription;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MyPrescriptions extends Component
{
    public ?string $refillMessage = null;
    public ?string $refillError = null;

    /**
     * Request a refill for a chronic prescription.
     */
    public function requestRefill(int $prescriptionId): void
    {
        $prescription = Prescription::where('id', $prescriptionId)
            ->where('patient_id', Auth::id())
            ->first();

        if (!$prescription) {
            $this->refillError = 'Prescription not found.';
            return;
        }

        if (!$prescription->hasRefillsRemaining()) {
            $this->refillError = 'No refills remaining. Please book a new consultation.';
            return;
        }

        if ($prescription->isExpired()) {
            $this->refillError = 'This prescription has expired. Please book a new consultation.';
            return;
        }

        $payment = $prescription->requestRefill();

        if ($payment) {
            $this->refillMessage = "Refill requested! Refill " . ($prescription->repeats_used + 1) . " of {$prescription->repeats}. Please complete payment to proceed.";
            $this->redirect(route('payment.checkout', $payment->reference));
        }
    }

    public function render()
    {
        $prescriptions = Prescription::where('patient_id', Auth::id())
            ->where('status', '!=', 'cancelled')
            ->with(['items', 'doctor'])
            ->orderByDesc('created_at')
            ->get();

        // Separate into active chronic (refillable) and past
        $chronic = $prescriptions->filter(fn ($p) => $p->is_chronic && $p->hasRefillsRemaining() && !$p->isExpired());
        $past = $prescriptions->reject(fn ($p) => $p->is_chronic && $p->hasRefillsRemaining() && !$p->isExpired());

        return view('livewire.patient.my-prescriptions', [
            'chronic' => $chronic,
            'past' => $past,
        ])->layout('layouts.app');
    }
}
