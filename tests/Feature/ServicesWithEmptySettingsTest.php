<?php

namespace Tests\Feature;

use App\Services\AiAssistantService;
use App\Services\AiPrescriptionService;
use App\Services\DailyService;
use App\Services\PharmacyService;
use App\Services\SmsService;
use Tests\TestCase;

/**
 * On a server where a service is not set up, its settings exist but are EMPTY (null). The page that happens to need the service must
 * still open: building the service must never throw. (A typed string property refuses null; the payment page once crashed on this.)
 */
class ServicesWithEmptySettingsTest extends TestCase
{
    public function test_every_outside_service_can_be_built_when_its_settings_are_empty(): void
    {
        config([
            'services.pharmacy.api_url' => null, 'services.pharmacy.api_key' => null, 'services.pharmacy.enabled' => null,
            'services.openai.api_key' => null, 'services.openai.model' => null,
            'services.daily.api_key' => null, 'services.daily.domain' => null,
            'services.sms.api_token' => null, 'services.sms.sender_id' => null,
        ]);

        foreach ([PharmacyService::class, AiAssistantService::class, AiPrescriptionService::class, DailyService::class, SmsService::class] as $class) {
            $this->assertInstanceOf($class, app()->make($class), $class);
        }
    }
}