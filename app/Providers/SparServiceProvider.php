<?php

namespace App\Providers;

use Zapmed\SparCore\Contracts\AuditLogger;
use Zapmed\SparCore\Contracts\OtpSender;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Contracts\TelehealthBridge;
use Zapmed\SparCore\Services\Telehealth\NullTelehealthBridge;
use App\Services\Spar\Audit\ChannelAuditLogger;
use App\Services\Spar\Channels\SmsChannel;
use App\Services\Spar\Identity\UserSparIdentityProvider;
use App\Services\Spar\Telehealth\ZapmedTelehealthBridge;
use App\Services\Spar\ZapmedOtpSender;
use Zapmed\SparCore\Services\Channels\WhatsAppChannel;
use Illuminate\Support\ServiceProvider;

/**
 * Binds SPAR contracts to ZapMed (integrated host) implementations
 * (spec FR-2, FR-3, FR-5). The spar-core package carries the domain code and
 * portable channels (in-app, email); the HOST binds the concrete identity
 * provider, telehealth bridge, audit logger, and SMS channel.
 *
 * Integrated (host_mode=integrated): identity from User, real telehealth
 * bridge, ZapMed SmsService-backed SMS channel. Standalone (host_mode=
 * standalone): its own provider binds SPAR-owned identity + NullTelehealthBridge
 * + its own SMS channel.
 */
class SparServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Identity: integrated uses the User-backed provider. The standalone
        // host will override this binding with its own SPAR-owned provider.
        $this->app->bind(SparIdentityProvider::class, UserSparIdentityProvider::class);

        // Telehealth bridge: real when integrated, no-op (package) when standalone.
        $this->app->bind(TelehealthBridge::class, function ($app) {
            return config('spar.host_mode', 'integrated') === 'integrated'
                ? $app->make(ZapmedTelehealthBridge::class)
                : $app->make(NullTelehealthBridge::class);
        });

        // Audit logger (spar_audit channel).
        $this->app->bind(AuditLogger::class, ChannelAuditLogger::class);

        // OTP transport for patient link re-verification — wraps ZapMed SmsService.
        $this->app->bind(OtpSender::class, ZapmedOtpSender::class);
    }

    public function boot(): void
    {
        // Resolve captured_by_id → staff display name for the patient detail view.
        // MUST be a class-string (not a closure) so `config:cache` can serialize
        // it — a closure here breaks every host deploy with
        // "Call to undefined method Closure::__set_state()". The package consumer
        // (PatientDetail) resolves a class-string via app()->make()->name($id).
        config([
            'spar.staff_name_resolver' => \App\Services\Spar\Identity\SparStaffNameResolver::class,
        ]);

        // Register the host's SMS channel with the package MessagingDispatcher.
        // The package ships portable in-app + email channels; each host injects
        // its own SMS implementation via `spar.channel_factories` (a map of
        // channel key => resolvable class/callable). ZapMed's SmsChannel wraps
        // the existing BulkSMS-backed SmsService.
        config([
            'spar.channel_factories' => array_merge(
                (array) config('spar.channel_factories', []),
                [
                    'sms' => SmsChannel::class,
                    // WhatsApp (Meta Cloud API direct). Portable package channel;
                    // driver defaults to 'log' until a WABA token is provisioned,
                    // so it's safe to register + prioritise before go-live.
                    'whatsapp' => WhatsAppChannel::class,
                ]
            ),
        ]);
    }
}
