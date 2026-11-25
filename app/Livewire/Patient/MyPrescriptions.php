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

        // Repeats that run by themselves are charged and sent without the patient asking, so a manual refill would pay twice.
        if (app(\App\Services\Subscriptions\PrescriptionRepeats::class)->subscriptionFor($prescription)) {
            $this->refillError = 'Your repeats for this prescription are automatic. You do not need to request a refill.';
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

        $repeats = app(\App\Services\Subscriptions\PrescriptionRepeats::class);
        $auto = $chronic->mapWithKeys(function ($p) use ($repeats) {
            $sub = $repeats->subscriptionFor($p);
            if (! $sub) {
                return [];
            }
            $next = $sub->cycles()->where('status', 'scheduled')->orderBy('scheduled_for')->first()?->scheduled_for;

            return [$p->id => [
                'on_hold' => in_array($sub->status, ['paused', 'cancelled'], true),
                'payment_trouble' => $sub->status === 'payment_failed',
                'next' => $next?->setTimezone(config('doctor.time.timezone'))->format('j M Y'),
            ]];
        });

        return view('livewire.patient.my-prescriptions', [
            'auto' => $auto,
            'chronic' => $chronic,
            'past' => $past,
        ])->layout('layouts.app');
    }
}
