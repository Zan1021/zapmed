<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayFastService
{
    private string $merchantId;
    private string $merchantKey;
    private string $passphrase;
    private bool $testMode;
    private string $processUrl;

    public function __construct()
    {
        $this->merchantId = config('payfast.merchant_id');
        $this->merchantKey = config('payfast.merchant_key');
        $this->passphrase = config('payfast.passphrase');
        $this->testMode = config('payfast.test_mode');
        $this->processUrl = config('payfast.url');
    }

    /**
     * Generate the payment form data for an appointment.
     */
    public function generatePaymentData(Appointment $appointment, Payment $payment): array
    {
        $patient = $appointment->patient;

        $data = [
            'merchant_id' => $this->merchantId,
            'merchant_key' => $this->merchantKey,
            'return_url' => url(config('payfast.return_url')) . '?reference=' . $payment->reference,
            'cancel_url' => url(config('payfast.cancel_url')) . '?reference=' . $payment->reference,
            'notify_url' => url(config('payfast.notify_url')),
            'name_first' => $patient->first_name,
            'name_last' => $patient->last_name,
            'email_address' => $patient->email,
            'cell_number' => $patient->phone ?? '',
            'm_payment_id' => $payment->reference,
            'amount' => number_format($payment->amount / 100, 2, '.', ''),
            'item_name' => "Zapmed Consultation - {$appointment->reference}",
            'item_description' => $appointment->type_label . ' with Dr. ' . $appointment->doctor->last_name,
        ];

        // Generate signature
        $data['signature'] = $this->generateSignature($data);

        return $data;
    }

    /**
     * Generate the subscription payment form data for PayFast recurring billing.
     */
    public function generateSubscriptionData(User $user, SubscriptionPlan $plan, Subscription $subscription, bool $tokenised = false): array
    {
        $data = [
            'merchant_id' => $this->merchantId,
            'merchant_key' => $this->merchantKey,
            'return_url' => url('/subscription/success') . '?reference=' . $subscription->payment_reference,
            'cancel_url' => url('/subscription/cancel') . '?reference=' . $subscription->payment_reference,
            'notify_url' => url('/subscription/notify'),
            'name_first' => $user->first_name,
            'name_last' => $user->last_name,
            'email_address' => $user->email,
            'cell_number' => $user->phone ?? '',
            'm_payment_id' => $subscription->payment_reference,
            'amount' => number_format($plan->price / 100, 2, '.', ''),
            'item_name' => "Zapmed {$plan->name} Plan",
            'item_description' => "{$plan->name} - {$plan->billing_label}",
            // Subscription-specific fields
            'subscription_type' => '1', // 1 = subscription
            'billing_date' => now()->addMonths($plan->cycle_frequency)->format('Y-m-d'),
            'recurring_amount' => number_format($plan->price / 100, 2, '.', ''),
            'frequency' => $this->getPayFastFrequency($plan),
            'cycles' => '0', // 0 = indefinite
        ];

        if ($tokenised) {
            // Tokenisation: PayFast saves the card and gives us a token. WE charge it later (ad hoc), PayFast does not bill by itself.
            $data['subscription_type'] = '2';
            foreach (['billing_date', 'recurring_amount', 'frequency', 'cycles'] as $key) {
                unset($data[$key]);
            }
        }

        $data['signature'] = $this->generateSignature($data);

        return $data;
    }

    /**
     * Charge a saved PayFast token once (ad hoc). $amountCents is in cents. Returns ['ok' => bool, 'message' => string,
     * 'unknown' => bool]. 'unknown' = we could not tell whether PayFast took the money (timeout or network error): the
     * caller must NOT treat it as a failure or retry, the ITN decides. The result of the payment itself arrives by ITN.
     *
     * @return array{ok:bool,message:string,unknown:bool}
     */
    public function adhocCharge(string $token, int $amountCents, string $itemName, string $reference): array
    {
        $timestamp = now()->format('Y-m-d\TH:i:s');
        $body = ['amount' => $amountCents, 'item_name' => $itemName, 'm_payment_id' => $reference];
        $url = 'https://api.payfast.co.za/subscriptions/'.rawurlencode($token).'/adhoc'.($this->testMode ? '?testing=true' : '');

        try {
            $response = Http::timeout((int) config('subscriptions.charging.timeout_seconds', 20))
                ->withHeaders([
                    'merchant-id' => $this->merchantId,
                    'version' => 'v1',
                    'timestamp' => $timestamp,
                    'signature' => $this->generateApiSignature($timestamp, $body),
                ])
                ->asJson()
                ->post($url, $body);
        } catch (\Throwable $e) {
            Log::error('PayFast adhoc charge: no answer, result unknown', ['reference' => $reference, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'No answer from PayFast: '.$e->getMessage(), 'unknown' => true];
        }

        if ($response->serverError()) {
            Log::error('PayFast adhoc charge: server error, result unknown', ['reference' => $reference, 'status' => $response->status()]);

            return ['ok' => false, 'message' => 'PayFast server error '.$response->status(), 'unknown' => true];
        }

        $json = $response->json() ?? [];
        $accepted = $response->status() === 200 && ($json['status'] ?? null) === 'success' && ($json['data']['response'] ?? false) !== false;

        if (! $accepted) {
            Log::warning('PayFast adhoc charge refused', ['reference' => $reference, 'status' => $response->status(), 'body' => $json]);
        }

        return [
            'ok' => $accepted,
            'message' => (string) ($json['data']['message'] ?? $json['message'] ?? ($accepted ? 'Accepted' : 'Refused')),
            'unknown' => false,
        ];
    }

    /**
     * Cancel a subscription via PayFast API.
     */
    public function cancelSubscription(string $token): bool
    {
        if (!$token) {
            return false;
        }

        $apiUrl = $this->testMode
            ? "https://sandbox.payfast.co.za/eng/recurring/update"
            : "https://api.payfast.co.za/subscriptions/{$token}/cancel";

        try {
            // In test mode, we just mark it as cancelled locally
            if ($this->testMode) {
                Log::info('PayFast subscription cancel (sandbox mode)', ['token' => $token]);
                return true;
            }

            $timestamp = now()->toISOString();
            $response = file_get_contents($apiUrl, false, stream_context_create([
                'http' => [
                    'method' => 'PUT',
                    'header' => implode("\r\n", [
                        'merchant-id: ' . $this->merchantId,
                        'version: v1',
                        'timestamp: ' . $timestamp,
                        'signature: ' . $this->generateApiSignature($timestamp),
                        'Content-Type: application/json',
                    ]),
                    'content' => json_encode(['response' => 'cancel']),
                ],
            ]));

            return $response !== false;
        } catch (\Exception $e) {
            Log::error('PayFast subscription cancel failed', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate a unique subscription reference.
     */
    public static function generateSubscriptionReference(): string
    {
        do {
            $reference = 'SUB-' . strtoupper(Str::random(8));
        } while (Subscription::where('payment_reference', $reference)->exists());

        return $reference;
    }

    /**
     * Map plan billing cycle to PayFast frequency code.
     */
    private function getPayFastFrequency(SubscriptionPlan $plan): string
    {
        // PayFast frequencies: 3=Monthly, 4=Quarterly, 5=Biannual, 6=Annual
        if ($plan->billing_cycle === 'annually') {
            return '6';
        }

        return match ($plan->cycle_frequency) {
            1 => '3', // Monthly
            3 => '4', // Quarterly
            6 => '5', // Biannual
            12 => '6', // Annual
            default => '3',
        };
    }

    /**
     * Generate API signature for PayFast API calls.
     */
    private function generateApiSignature(string $timestamp, array $body = []): string
    {
        // Header values and any body fields, sorted by name (PayFast API signature rule).
        $data = $body + [
            'merchant-id' => $this->merchantId,
            'passphrase' => $this->passphrase,
            'timestamp' => $timestamp,
            'version' => 'v1',
        ];

        ksort($data);
        $pfOutput = http_build_query($data);

        return md5($pfOutput);
    }

    /**
     * Get the PayFast process URL.
     */
    public function getProcessUrl(): string
    {
        return $this->processUrl;
    }

    /**
     * Generate the PayFast signature.
     */
    public function generateSignature(array $data, ?string $passphrase = null): string
    {
        $passphrase = $passphrase ?? $this->passphrase;

        // Build the query string
        $pfOutput = '';
        foreach ($data as $key => $val) {
            if ($val !== '' && $key !== 'signature') {
                $pfOutput .= $key . '=' . urlencode(trim($val)) . '&';
            }
        }

        // Remove last ampersand
        $pfOutput = rtrim($pfOutput, '&');

        // Add passphrase if set
        if (!empty($passphrase)) {
            $pfOutput .= '&passphrase=' . urlencode(trim($passphrase));
        }

        return md5($pfOutput);
    }

    /**
     * Validate the ITN (Instant Transaction Notification) from PayFast.
     */
    public function validateItn(array $data): bool
    {
        // 1. Verify signature
        $receivedSignature = $data['signature'] ?? '';
        $checkData = $data;
        unset($checkData['signature']);

        $expectedSignature = $this->generateSignature($checkData);

        if (!hash_equals($expectedSignature, $receivedSignature)) {
            Log::warning('PayFast ITN: Signature mismatch', [
                'received' => $receivedSignature,
                'expected' => $expectedSignature,
            ]);
            return false;
        }

        // 2. Verify the source IP (PayFast server IPs)
        $validHosts = [
            'www.payfast.co.za',
            'sandbox.payfast.co.za',
            'w1w.payfast.co.za',
            'w2w.payfast.co.za',
        ];

        $validIps = [];
        foreach ($validHosts as $host) {
            $ips = gethostbynamel($host);
            if ($ips) {
                $validIps = array_merge($validIps, $ips);
            }
        }
        $validIps = array_unique($validIps);

        $requestIp = request()->ip();
        if (!in_array($requestIp, $validIps) && !$this->testMode) {
            Log::warning('PayFast ITN: Invalid source IP', ['ip' => $requestIp]);
            return false;
        }

        // 3. Verify with PayFast server
        if (!$this->testMode) {
            $result = $this->verifyWithPayFast($data);
            if (!$result) {
                return false;
            }
        }

        return true;
    }

    /**
     * Confirm payment with PayFast server.
     */
    private function verifyWithPayFast(array $data): bool
    {
        $validateUrl = config('payfast.validate_url');

        $response = file_get_contents($validateUrl, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data),
            ],
        ]));

        return trim($response) === 'VALID';
    }
}
