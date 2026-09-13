<?php

namespace App\Services\Notifications\Channels;

use App\Services\Notifications\ChannelResult;
use App\Services\Notifications\Contracts\NotificationChannel;
use App\Services\Notifications\OutboundMessage;
use App\Services\SmsService;

/**
 * SMS via the existing SmsService (BulkSMS/Clickatell, dev-mode logs when
 * unconfigured). Thin wrapper so all sends flow through the dispatcher + log.
 */
class SmsChannel implements NotificationChannel
{
    public function __construct(private SmsService $sms) {}

    public function key(): string
    {
        return 'sms';
    }

    public function isConfigured(): bool
    {
        return $this->sms->isConfigured();
    }

    public function send(OutboundMessage $message): ChannelResult
    {
        $phone = $message->phone;
        $provider = (string) config('services.sms.provider', 'bulksms');

        if (empty($phone)) {
            return ChannelResult::failed($provider, 'No phone number for recipient.');
        }

        $body = $message->body ?? '';
        if ($body === '') {
            return ChannelResult::failed($provider, 'Empty SMS body.');
        }

        // SmsService returns true in dev-mode (logs) and on provider success.
        $ok = $this->sms->send($phone, $body);

        return $ok
            ? ChannelResult::sent($this->isConfigured() ? $provider : 'log')
            : ChannelResult::failed($provider, 'SmsService reported failure.');
    }
}
