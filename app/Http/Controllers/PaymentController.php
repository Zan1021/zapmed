<?php

namespace App\Http\Controllers;

use App\Mail\AppointmentConfirmed;
use App\Mail\NewAppointmentForDoctor;
use App\Mail\PaymentReceived;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;
use App\Services\PayFastService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private PayFastService $payfast
    ) {}

    /**
     * Show the payment page with PayFast form.
     */
    public function checkout(string $reference)
    {
        $payment = Payment::where('reference', $reference)
            ->where('patient_id', auth()->id())
            ->where('status', 'pending')
            ->firstOrFail();

        $appointment = $payment->appointment;
        // The first payment for a prescription with repeats saves the card, when that is switched on.
        $tokenised = app(\App\Services\Subscriptions\PrescriptionRepeats::class)->shouldTokenise($payment);
        $paymentData = $this->payfast->generatePaymentData($appointment, $payment, $tokenised);
        $processUrl = $this->payfast->getProcessUrl();

        $couponsOn = app(\App\Services\Operations\CouponRedemption::class)->acceptsCoupon($payment);

        return view('payment.checkout', compact('payment', 'appointment', 'paymentData', 'processUrl', 'couponsOn'));
    }

    /** A coupon code typed on the payment page. Only the patient's own pending consultation payment takes one. */
    public function applyCoupon(Request $request, string $reference)
    {
        $payment = Payment::where('reference', $reference)->where('patient_id', auth()->id())->where('status', 'pending')->firstOrFail();
        $data = $request->validate(['coupon' => 'required|string|max:40'], ['coupon.required' => 'Enter your coupon code.']);

        try {
            $payment = app(\App\Services\Operations\CouponRedemption::class)->apply($payment, $data['coupon'], auth()->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('payment.checkout', $reference)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('payment.checkout', $reference)->with('coupon_message', 'Coupon applied: R'.number_format($payment->discount_cents / 100, 2).' off.');
    }

    public function removeCoupon(string $reference)
    {
        $payment = Payment::where('reference', $reference)->where('patient_id', auth()->id())->where('status', 'pending')->firstOrFail();

        try {
            app(\App\Services\Operations\CouponRedemption::class)->remove($payment, auth()->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('payment.checkout', $reference)->withErrors($e->errors());
        }

        return redirect()->route('payment.checkout', $reference)->with('coupon_message', 'Coupon removed.');
    }

    /**
     * Handle successful payment return (user redirected back).
     */
    public function success(Request $request)
    {
        $reference = $request->query('reference');
        $payment = Payment::where('reference', $reference)->first();

        return view('payment.success', compact('payment'));
    }

    /**
     * Handle cancelled payment return.
     */
    public function cancel(Request $request)
    {
        $reference = $request->query('reference');
        $payment = Payment::where('reference', $reference)->first();

        if ($payment && $payment->status === 'pending') {
            $payment->update(['status' => 'cancelled']);
        }

        return view('payment.cancel', compact('payment'));
    }

    /**
     * Handle PayFast ITN (webhook callback).
     */
    public function notify(Request $request)
    {
        $data = $request->all();

        Log::info('PayFast ITN received', $data);

        // Validate the ITN
        if (!$this->payfast->validateItn($data)) {
            Log::error('PayFast ITN validation failed');
            return response('Invalid ITN', 400);
        }

        // Find the payment
        $payment = Payment::where('reference', $data['m_payment_id'] ?? '')->first();

        if (!$payment) {
            Log::error('PayFast ITN: Payment not found', ['reference' => $data['m_payment_id'] ?? '']);
            return response('Payment not found', 404);
        }

        // Verify amount matches
        $expectedAmount = number_format($payment->amount / 100, 2, '.', '');
        if (($data['amount_gross'] ?? '') !== $expectedAmount) {
            Log::error('PayFast ITN: Amount mismatch', [
                'expected' => $expectedAmount,
                'received' => $data['amount_gross'] ?? '',
            ]);
            return response('Amount mismatch', 400);
        }

        // Update payment based on status
        $pfStatus = $data['payment_status'] ?? '';

        match ($pfStatus) {
            'COMPLETE' => $this->handleCompletePayment($payment, $data),
            'FAILED' => $payment->update(['status' => 'failed', 'provider_data' => $data]),
            'PENDING' => $payment->update(['status' => 'processing', 'provider_data' => $data]),
            default => Log::warning('PayFast ITN: Unknown status', ['status' => $pfStatus]),
        };

        return response('OK', 200);
    }

    /**
     * Handle a completed payment.
     */
    private function handleCompletePayment(Payment $payment, array $data): void
    {
        $payment->update([
            'status' => 'completed',
            'provider_reference' => $data['pf_payment_id'] ?? null,
            'payment_method' => $this->mapPaymentMethod($data['payment_method'] ?? ''),
            'provider_data' => $data,
            'paid_at' => now(),
        ]);

        // Mark appointment as paid (consultation payment)
        if ($payment->appointment) {
            $payment->appointment->update([
                'is_paid' => true,
                'status' => 'confirmed',
            ]);
        }

        // A coupon on this payment is used up now that the money is confirmed. Never blocks the payment.
        try {
            app(\App\Services\Operations\CouponRedemption::class)->redeem($payment->fresh());
        } catch (\Throwable $e) {
            Log::warning('Counting a coupon use failed', ['payment' => $payment->reference, 'error' => $e->getMessage()]);
        }

        // Check if this is a medication payment — dispatch to pharmacy
        $this->handleMedicationPayment($payment, $data);

        Log::info('PayFast payment completed', [
            'reference' => $payment->reference,
            'amount' => $payment->amount,
        ]);

        // Record partner commission if this patient was referred
        $referralService = app(\App\Services\ReferralService::class);
        if ($payment->appointment) {
            $referralService->recordConsultationCommission(
                $payment->patient,
                $payment->amount,
                $payment->appointment->reference ?? $payment->reference
            );
        } else {
            $referralService->recordMedicationCommission(
                $payment->patient,
                $payment->amount,
                $payment->reference
            );
        }

        // Record revenue split (payout tracking)
        $payoutService = app(\App\Services\PayoutService::class);
        if ($payment->appointment) {
            $payoutService->recordConsultationPayout($payment);
        } else {
            $payoutService->recordMedicationPayout($payment);
        }

        // Send email notifications (queued for fast webhook return; each hand-off is logged).
        $payment->loadMissing(['appointment.doctor', 'appointment.patient', 'patient']);

        $dispatcher = app(NotificationDispatcher::class);

        $dispatcher->queueMailable(new OutboundMessage(
            templateKey: 'payment.received',
            category: 'transactional',
            user: $payment->patient,
            email: $payment->patient->email,
            mailable: new PaymentReceived($payment),
            meta: ['payment_reference' => $payment->reference],
        ));

        if ($payment->appointment) {
            $dispatcher->queueMailable(new OutboundMessage(
                templateKey: 'appointment.confirmed',
                category: 'transactional',
                user: $payment->patient,
                email: $payment->patient->email,
                mailable: new AppointmentConfirmed($payment->appointment),
                meta: ['appointment_id' => $payment->appointment->id],
            ));

            $dispatcher->queueMailable(new OutboundMessage(
                templateKey: 'appointment.new_for_doctor',
                category: 'transactional',
                user: $payment->appointment->doctor,
                email: $payment->appointment->doctor->email,
                mailable: new NewAppointmentForDoctor($payment->appointment),
                meta: ['appointment_id' => $payment->appointment->id],
            ));
        }

        // Task 11: mirror the completed payment into the CRM Order aggregate + finance ledger. Guarded
        // so a bridge failure can never affect the payment outcome or the notifications above.
        try {
            app(\App\Services\Commerce\LiveOrderBridge::class)->onPaymentCompleted($payment);
        } catch (\Throwable $e) {
            Log::warning('LiveOrderBridge onPaymentCompleted failed', [
                'payment' => $payment->reference, 'error' => $e->getMessage(),
            ]);
        }

        // A follow-up the doctor asked for counts as booked once its card payment is in. Guarded the same way.
        try {
            app(\App\Services\Patient\PatientFollowUps::class)->onPaymentCompleted($payment);
        } catch (\Throwable $e) {
            Log::warning('Follow-up booking after payment failed', ['payment' => $payment->reference, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Handle medication payment — mark prescription as paid and dispatch to pharmacy.
     */
    private function handleMedicationPayment(Payment $payment, array $data = []): void
    {
        // Check if this payment is for medication (description starts with "Medication -")
        if (!str_starts_with($payment->description ?? '', 'Medication -')) {
            return;
        }

        // Extract prescription reference from description
        $reference = str_replace('Medication - ', '', $payment->description);
        $prescription = \App\Models\Prescription::where('reference', $reference)->first();

        if (!$prescription) {
            Log::warning('Medication payment completed but prescription not found', [
                'payment' => $payment->reference,
                'description' => $payment->description,
            ]);
            return;
        }

        // Mark prescription as paid
        $prescription->markPaid($payment->reference);

        // PayFast sent a card token with this payment (the form asked to save the card): start the repeat order. Never blocks the payment.
        if (! empty($data['token'])) {
            try {
                app(\App\Services\Subscriptions\PrescriptionRepeats::class)->start($prescription, (string) $data['token'], $payment);
            } catch (\Throwable $e) {
                Log::error('Starting the repeat order failed', ['prescription' => $prescription->reference, 'error' => $e->getMessage()]);
            }
        }

        // Dispatch to pharmacy
        $pharmacy = app(\App\Services\PharmacyService::class);
        $result = $pharmacy->dispatch($prescription);

        Log::info('Pharmacy dispatch attempted after medication payment', [
            'prescription' => $prescription->reference,
            'success' => $result['success'],
            'message' => $result['message'],
        ]);
    }

    /**
     * Map PayFast payment method to our internal type.
     */
    private function mapPaymentMethod(string $method): string
    {
        return match ($method) {
            'cc' => 'credit_card',
            'dc' => 'debit_card',
            'eft' => 'eft',
            'mp' => 'mobicred',
            'mc' => 'masterpass',
            'sc' => 'snapscan',
            default => $method ?: 'unknown',
        };
    }
}
