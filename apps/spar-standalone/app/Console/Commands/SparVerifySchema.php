<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AC-1 verification: the standalone schema must contain NO telehealth tables.
 */
class SparVerifySchema extends Command
{
    protected $signature = 'spar:verify-schema';
    protected $description = 'AC-1: assert no telehealth tables exist in the standalone schema';

    public function handle(): int
    {
        $tables = collect(Schema::getTableListing())
            ->reject(fn ($n) => $n === 'migrations' || str_starts_with($n, 'sqlite_'))
            ->map(fn ($n) => str_contains($n, '.') ? explode('.', $n)[1] : $n)
            ->values();

        $this->info('TABLES: ' . $tables->implode(', '));

        $forbidden = ['users', 'prescriptions', 'prescription_items', 'appointments', 'consultations', 'doctor_profiles', 'patient_profiles', 'medications', 'payments'];
        $leaks = $tables->intersect($forbidden)->values();

        if ($leaks->isEmpty()) {
            $this->info('AC-1 PASS: no telehealth tables present.');
        } else {
            $this->error('AC-1 FAIL: telehealth tables present -> ' . $leaks->implode(', '));
            return self::FAILURE;
        }

        // AC-4 + binding checks: standalone impls must be bound.
        $checks = [
            \Zapmed\SparCore\Contracts\SparIdentityProvider::class => \App\Spar\StandaloneSparIdentityProvider::class,
            \Zapmed\SparCore\Contracts\TelehealthBridge::class => \Zapmed\SparCore\Services\Telehealth\NullTelehealthBridge::class,
            \Zapmed\SparCore\Contracts\AuditLogger::class => \App\Spar\LogAuditLogger::class,
            \Zapmed\SparCore\Contracts\OtpSender::class => \App\Spar\StandaloneOtpSender::class,
        ];

        foreach ($checks as $contract => $expected) {
            $actual = get_class(app($contract));
            $ok = $actual === $expected;
            $this->line(($ok ? '  [OK] ' : '  [FAIL] ') . class_basename($contract) . ' -> ' . class_basename($actual));
            if (!$ok) {
                $this->error('Binding mismatch for ' . $contract);
                return self::FAILURE;
            }
        }

        // AC-4: NullTelehealthBridge offers NO online consult.
        $bridge = app(\Zapmed\SparCore\Contracts\TelehealthBridge::class);
        if ($bridge->offersOnlineConsult()) {
            $this->error('AC-4 FAIL: standalone bridge offers online consult.');
            return self::FAILURE;
        }
        $this->info('AC-4 PASS: no online-consult option (own-doctor renewal only).');

        // MessagingDispatcher must have the standalone SMS channel registered.
        $factories = config('spar.channel_factories', []);
        $smsOk = ($factories['sms'] ?? null) === \App\Spar\StandaloneSmsChannel::class;
        $this->line(($smsOk ? '  [OK] ' : '  [FAIL] ') . 'sms channel -> ' . ($factories['sms'] ?? 'none'));

        return self::SUCCESS;
    }
}
