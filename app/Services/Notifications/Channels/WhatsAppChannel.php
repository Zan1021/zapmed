<?php

namespace App\Services\Notifications\Channels;

use App\Services\Notifications\ChannelResult;
use App\Services\Notifications\Contracts\NotificationChannel;
use App\Services\Notifications\OutboundMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp via the Meta WhatsApp Business Cloud API.
 *
 * STUB-UNTIL-CREDS: fully wired, but until real Meta credentials
 * (services.whatsapp.token + phone_number_id) are configured it logs the
 * intended message and reports success (dev-mode), exactly like SmsService.
 * No refactor is needed when creds arrive — isConfigured() flips to true and
 * the real Graph API call runs.
 *
 * DEPENDENCY (Captain Zan): Meta WhatsApp Business account -> permanent access
 * token, phone number id, and APPROVED message templates before live sending.
 */
class WhatsAppChannel implements NotificationChannel
{
    public function key(): string
    {
        return 'whatsapp';
    }

    public function isConfigured(): bool
    {
        return !empty(config('services.whatsapp.token'))
            && !empty(config('services.whatsapp.phone_number_id'));
    }

    public function send(OutboundMessage $message): ChannelResult
    {
        $phone = $this->formatE164($message->phone ?? '');

        if ($phone === '') {
            return ChannelResult::failed('whatsapp_meta', 'No phone number for recipient.');
        }

        // Dev / not-yet-provisioned: log instead of sending, report success.
        if (!$this->isConfigured()) {
            Log::info("WhatsApp [DEV MODE — no Meta creds] to {$phone}: " . ($message->body ?? ''));

            return ChannelResult::sent('log');
        }

        try {
            $phoneId = (string) config('services.whatsapp.phone_number_id');
            $version = (string) config('services.whatsapp.api_version', 'v21.0');

            $response = Http::withToken((string) config('services.whatsapp.token'))
                ->post("https://graph.facebook.com/{$version}/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim($phone, '+'),
                    'type' => 'text',
                    'text' => ['body' => $message->body ?? ''],
                ]);

            if ($response->failed()) {
                Log::error('WhatsApp API error', ['response' => $response->body()]);

                return ChannelResult::failed('whatsapp_meta', 'Graph API error: ' . $response->status());
            }

            $ref = $response->json('messages.0.id');

            return ChannelResult::sent('whatsapp_meta', $ref);
        } catch (\Throwable $e) {
            Log::error('WhatsApp send failed: ' . $e->getMessage(), ['phone' => $phone]);

            return ChannelResult::failed('whatsapp_meta', $e->getMessage());
        }
    }

    /** Normalise SA numbers to E.164 (mirrors SmsService formatting). */
    private function formatE164(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]+/', '', $phone);
        if ($phone === '') {
            return '';
        }
        if (str_starts_with($phone, '0')) {
            $phone = '+27' . substr($phone, 1);
        }
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}
