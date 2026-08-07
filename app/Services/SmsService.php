<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private string $provider;
    private string $apiUrl;
    private string $apiToken;
    private string $senderId;

    public function __construct()
    {
        $this->provider = config('services.sms.provider', 'bulksms');
        $this->apiToken = config('services.sms.api_token', '');
        $this->senderId = config('services.sms.sender_id', 'Zapmed');

        $this->apiUrl = match ($this->provider) {
            'bulksms' => 'https://api.bulksms.com/v1/messages',
            'clickatell' => 'https://platform.clickatell.com/messages',
            default => '',
        };
    }

    /**
     * Send an SMS message.
     *
     * @param string $phoneNumber SA phone number (e.g. 0821234567 or +27821234567)
     * @param string $message The SMS content
     * @return bool Whether the SMS was sent successfully
     */
    public function send(string $phoneNumber, string $message): bool
    {
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);

        if (!$this->isConfigured()) {
            // In development, log the SMS instead of sending
            Log::info("SMS [DEV MODE] to {$phoneNumber}: {$message}");
            return true;
        }

        try {
            return match ($this->provider) {
                'bulksms' => $this->sendViaBulkSms($phoneNumber, $message),
                'clickatell' => $this->sendViaClickatell($phoneNumber, $message),
                default => false,
            };
        } catch (\Exception $e) {
            Log::error("SMS send failed: {$e->getMessage()}", [
                'phone' => $phoneNumber,
                'provider' => $this->provider,
            ]);
            return false;
        }
    }

    /**
     * Send OTP code via SMS.
     */
    public function sendOtp(string $phoneNumber, string $code): bool
    {
        $message = "Your Zapmed verification code is: {$code}. This code expires in 10 minutes. Do not share it with anyone.";

        return $this->send($phoneNumber, $message);
    }

    /**
     * Send via BulkSMS.
     */
    private function sendViaBulkSms(string $phoneNumber, string $message): bool
    {
        $response = Http::withToken($this->apiToken)
            ->post($this->apiUrl, [
                'to' => $phoneNumber,
                'body' => $message,
            ]);

        if ($response->failed()) {
            Log::error('BulkSMS API error', ['response' => $response->body()]);
            return false;
        }

        return true;
    }

    /**
     * Send via Clickatell.
     */
    private function sendViaClickatell(string $phoneNumber, string $message): bool
    {
        $response = Http::withHeaders([
                'Authorization' => $this->apiToken,
                'Content-Type' => 'application/json',
            ])
            ->post($this->apiUrl, [
                'messages' => [
                    [
                        'channel' => 'sms',
                        'to' => $phoneNumber,
                        'content' => $message,
                    ],
                ],
            ]);

        if ($response->failed()) {
            Log::error('Clickatell API error', ['response' => $response->body()]);
            return false;
        }

        return true;
    }

    /**
     * Format SA phone number to international format.
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove spaces, dashes, brackets
        $phone = preg_replace('/[\s\-\(\)]+/', '', $phone);

        // Convert 0XX to +27XX
        if (str_starts_with($phone, '0')) {
            $phone = '+27' . substr($phone, 1);
        }

        // Ensure + prefix
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Check if the service is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiToken) && !empty($this->apiUrl);
    }
}
