<?php

namespace App\Services\Spar\Channels;

use Zapmed\SparCore\Contracts\MessagingChannel;
use Zapmed\SparCore\Models\SparPatient;
use App\Services\SmsService;

/**
 * SMS delivery — the permanent fallback channel (spec FR-12.2).
 * Wraps the existing ZapMed SmsService (BulkSMS/Clickatell).
 */
class SmsChannel implements MessagingChannel
{
    public function __construct(private SmsService $sms)
    {
    }

    public function key(): string
    {
        return 'sms';
    }

    public function canReach(SparPatient $patient): bool
    {
        return !empty($patient->primaryPhone());
    }

    public function send(SparPatient $patient, array $payload): bool
    {
        $phone = $patient->primaryPhone();
        if (empty($phone)) {
            return false;
        }

        $body = $payload['body'] ?? '';
        if (!empty($payload['link'])) {
            $body = rtrim($body) . "\n" . $payload['link'];
        }

        return $this->sms->send($phone, $body);
    }
}
