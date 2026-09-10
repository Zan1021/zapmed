<?php

namespace App\Providers;

use App\Spar\LogAuditLogger;
use App\Spar\StandaloneOtpSender;
use App\Spar\StandaloneSmsChannel;
use App\Spar\StandaloneSparIdentityProvider;
use Zapmed\SparCore\Contracts\AuditLogger;
use Zapmed\SparCore\Contracts\OtpSender;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Contracts\TelehealthBridge;
use Zapmed\SparCore\Services\Telehealth\NullTelehealthBridge;
use Zapmed\SparCore\Services\Channels\WhatsAppChannel;
use Illuminate\Support\ServiceProvider;

/**
 * Standalone host bindings for the spar-core package (spec FR-2/3/5, design §5).
 *
 * This is the standalone counterpart to ZapMed's SparServiceProvider. It binds
 * every SPAR contract to a NON-telehealth implementation, guaranteeing the app
 * has zero dependency on telehealth (AC-1/AC-4):
 *   - SparIdentityProvider -> SPAR-owned identity only (no User table)
 *   - TelehealthBridge     -> NullTelehealthBridge (renewal = own-doctor only)
 *   - AuditLogger          -> spar_audit channel
 *   - OtpSender            -> standalone transport
 *   - MessagingChannel     -> standalone SMS as the primary text channel
 */
class SparStandaloneServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SparIdentityProvider::class, StandaloneSparIdentityProvider::class);

        // No telehealth in standalone (AC-4): renewal offers only "own doctor".
        $this->app->bind(TelehealthBridge::class, NullTelehealthBridge::class);

        $this->app->bind(AuditLogger::class, LogAuditLogger::class);

        $this->app->bind(OtpSender::class, StandaloneOtpSender::class);
    }

    public function boot(): void
    {
        // Resolve captured_by_id → pharmacist display name for the staff patient
        // detail view (the package stays free of the host user model).
        config([
            'spar.staff_name_resolver' => fn ($id) => \App\Models\PharmacyUser::whereKey($id)->value('name') ?? "Staff #{$id}",
        ]);

        // Register the standalone SMS channel with the package MessagingDispatcher.
        // The package ships portable in-app + email channels; the host supplies
        // its own SMS. WhatsApp slots in here later with no rewrite.
        config([
            'spar.channel_factories' => array_merge(
                (array) config('spar.channel_factories', []),
                [
                    'sms' => StandaloneSmsChannel::class,
                    'whatsapp' => WhatsAppChannel::class,
                ]
            ),
        ]);
    }
}
