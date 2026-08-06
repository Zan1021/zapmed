<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Payment;
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
        $paymentData = $this->payfast->generatePaymentData($appointment, $payment);
        $processUrl = $this->payfast->getProcessUrl();

        return view('payment.checkout', compact('payment', 'appointment', 'paymentData', 'processUrl'));
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

        // Mark appointment as paid
        if ($payment->appointment) {
            $payment->appointment->update([
                'is_paid' => true,
                'status' => 'confirmed',
            ]);
        }

        Log::info('PayFast payment completed', [
            'reference' => $payment->reference,
            'amount' => $payment->amount,
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
