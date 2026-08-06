<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

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

        if ($receivedSignature !== $expectedSignature) {
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
