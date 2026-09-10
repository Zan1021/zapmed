<?php

namespace App\Services\Spar;

use Zapmed\SparCore\Contracts\OtpSender;
use App\Services\SmsService;

/**
 * Integrated (ZapMed) OTP transport — wraps the existing BulkSMS/Clickatell
 * SmsService. Bound to the OtpSender contract by SparServiceProvider so the
 * package's patient-link re-verification stays free of any host SMS class.
 */
class ZapmedOtpSender implements OtpSender
{
    public function __construct(private SmsService $sms)
    {
    }

    public function sendOtp(string $phone, string $code): bool
    {
        return (bool) $this->sms->sendOtp($phone, $code);
    }
}
