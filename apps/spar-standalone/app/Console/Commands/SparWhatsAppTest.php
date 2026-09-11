<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Services\Channels\WhatsAppChannel;

/**
 * Throwaway smoke test: sends a WhatsApp message to a phone number THROUGH the
 * real WhatsAppChannel (not raw curl), exercising number normalization,
 * template-name resolution, and the cloud_api dispatch path.
 *
 * Usage: php artisan spar:whatsapp-test 0832810909
 *        php artisan spar:whatsapp-test +27832810909 --template=onboarding_consent
 *
 * Reads all creds/driver/templates from config('spar.whatsapp.*') i.e. .env.
 * Builds an IN-MEMORY SparPatient (no DB write) so nothing is persisted.
 */
class SparWhatsAppTest extends Command
{
    protected $signature = 'spar:whatsapp-test {phone : Recipient phone (local 0xx or E.164 +27xx)} {--template=onboarding_consent : payload template key} {--bare : send template with NO vars/link (for zero-param templates like hello_world)}';

    protected $description = 'Send a WhatsApp test message through the real WhatsAppChannel (smoke test).';

    public function handle(WhatsAppChannel $channel): int
    {
        $phone = (string) $this->argument('phone');
        $templateKey = (string) $this->option('template');

        $this->line('Driver:   ' . config('spar.whatsapp.driver'));
        $this->line('Enabled:  ' . var_export((bool) config('spar.whatsapp.enabled'), true));
        $this->line('PhoneID:  ' . config('spar.whatsapp.phone_number_id'));
        $this->line('Template: ' . $templateKey . ' => ' . (config("spar.whatsapp.templates.{$templateKey}") ?: '(none configured)'));
        $this->line('Language: ' . config('spar.whatsapp.language', 'en'));
        $this->newLine();

        // In-memory patient — NOT saved. primaryPhone() reads cellphone directly.
        $patient = new SparPatient([
            'first_name' => 'Test',
            'last_name'  => 'Recipient',
            'cellphone'  => $phone,
        ]);
        // Give it an id so log lines have something to show (not persisted).
        $patient->id = 0;

        if (! $channel->canReach($patient)) {
            $this->error('Channel cannot reach patient. Check SPAR_WHATSAPP_ENABLED=true and that a phone is set.');

            return self::FAILURE;
        }

        $payload = [
            'template' => $templateKey,
        ];
        if (! $this->option('bare')) {
            $payload['vars'] = ['Test Recipient'];
            $payload['body'] = 'SPAR WhatsApp smoke test via WhatsAppChannel.';
            $payload['link'] = 'https://spar.zapdev.co.za/track/demo';
        }

        $this->info('Sending via WhatsAppChannel->send() ...');
        $ok = $channel->send($patient, $payload);

        if ($ok) {
            $this->info('WhatsAppChannel reported SUCCESS (message accepted / logged).');

            return self::SUCCESS;
        }

        $this->error('WhatsAppChannel reported FAILURE. Check logs (storage/logs) for the Meta error.');

        return self::FAILURE;
    }
}
