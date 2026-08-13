<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\PayFastService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(
        private PayFastService $payfast
    ) {}

    /**
     * Show the subscription checkout page with PayFast form.
     */
    public function checkout()
    {
        $data = session('subscription_checkout');

        if (!$data) {
            return redirect()->route('patient.subscription');
        }

        return view('subscription.checkout', [
            'paymentData' => $data['payment_data'],
            'processUrl' => $data['process_url'],
            'planName' => $data['plan_name'],
        ]);
    }

    /**
     * Handle successful subscription return.
     */
    public function success(Request $request)
    {
        $reference = $request->query('reference');
        $subscription = Subscription::where('payment_reference', $reference)->with('plan')->first();

        // Clear checkout session
        session()->forget('subscription_checkout');

        return view('subscription.success', compact('subscription'));
    }

    /**
     * Handle cancelled subscription return.
     */
    public function cancel(Request $request)
    {
        $reference = $request->query('reference');
        $subscription = Subscription::where('payment_reference', $reference)->first();

        if ($subscription && $subscription->status === 'pending') {
            $subscription->update(['status' => 'cancelled']);
        }

        session()->forget('subscription_checkout');

        return view('subscription.cancel', compact('subscription'));
    }

    /**
     * Handle PayFast subscription ITN (webhook).
     */
    public function notify(Request $request)
    {
        $data = $request->all();

        Log::info('PayFast Subscription ITN received', $data);

        // Validate ITN
        if (!$this->payfast->validateItn($data)) {
            Log::error('PayFast Subscription ITN validation failed');
            return response('Invalid ITN', 400);
        }

        $reference = $data['m_payment_id'] ?? '';
        $subscription = Subscription::where('payment_reference', $reference)->first();

        if (!$subscription) {
            Log::error('Subscription ITN: Subscription not found', ['reference' => $reference]);
            return response('Not found', 404);
        }

        $pfStatus = $data['payment_status'] ?? '';
        $token = $data['token'] ?? null;

        // Store PayFast token for future operations
        if ($token && !$subscription->payfast_token) {
            $subscription->update(['payfast_token' => $token]);
        }

        match ($pfStatus) {
            'COMPLETE' => $this->handleComplete($subscription, $data),
            'FAILED' => $this->handleFailed($subscription, $data),
            'PENDING' => null, // Ignore pending
            default => Log::warning('Subscription ITN: Unknown status', ['status' => $pfStatus]),
        };

        return response('OK', 200);
    }

    /**
     * Handle successful subscription payment.
     */
    private function handleComplete(Subscription $subscription, array $data): void
    {
        $amount = (int) round(((float) ($data['amount_gross'] ?? 0)) * 100);

        if ($subscription->status === 'pending') {
            // First payment — activate subscription
            $subscription->activate();
        } else {
            // Recurring payment — renew period
            $subscription->renewPeriod();
        }

        $subscription->recordPayment($amount);

        Log::info('Subscription payment completed', [
            'reference' => $subscription->payment_reference,
            'amount' => $amount,
            'payment_count' => $subscription->payment_count,
        ]);
    }

    /**
     * Handle failed subscription payment.
     */
    private function handleFailed(Subscription $subscription, array $data): void
    {
        $subscription->update([
            'status' => 'payment_failed',
            'metadata' => array_merge($subscription->metadata ?? [], [
                'last_failure' => $data,
                'last_failure_at' => now()->toISOString(),
                'failure_reason' => $data['reason'] ?? 'Unknown',
            ]),
        ]);

        // Send failed payment email to patient
        try {
            $user = $subscription->user;
            \Illuminate\Support\Facades\Mail::raw(
                "Dear {$user->first_name},\n\n" .
                "We were unable to process your subscription payment of {$subscription->plan->formatted_price} for your {$subscription->plan->name} plan.\n\n" .
                "This could be due to insufficient funds, an expired card, or a temporary issue with your bank.\n\n" .
                "What to do:\n" .
                "1. Log in to your Zapmed account\n" .
                "2. Go to My Subscription\n" .
                "3. Update your payment method or contact your bank\n\n" .
                "If we're unable to collect payment, your subscription will be paused and you may lose access to consultations.\n\n" .
                "Need help? Reply to this email or contact support@zapmed.co.za\n\n" .
                "Kind regards,\nThe Zapmed Team",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Payment Failed - Action Required | Zapmed');
                }
            );
        } catch (\Exception $e) {
            Log::error('Failed to send payment failure email', ['error' => $e->getMessage()]);
        }

        Log::warning('Subscription payment failed', [
            'reference' => $subscription->payment_reference,
            'user_id' => $subscription->user_id,
        ]);
    }
}
