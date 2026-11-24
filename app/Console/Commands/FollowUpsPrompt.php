<?php

namespace App\Console\Commands;

use App\Services\Doctor\FollowUpService;
use Illuminate\Console\Command;

/**
 * Prompts patients to book a doctor-requested follow-up a few days before it is due (config
 * doctor.followups.prompt_days_before). Each follow-up is prompted once. Messages stay OFF until
 * DOCTOR_FOLLOWUP_NOTIFY=true. Runs daily at 09:00 SA time (routes/console.php).
 */
class FollowUpsPrompt extends Command
{
    protected $signature = 'followups:prompt';

    protected $description = 'Prompt patients to book follow-ups that are coming due';

    public function handle(FollowUpService $service): int
    {
        $this->info('Prompted '.$service->promptDue().' follow-up(s).');

        return self::SUCCESS;
    }
}
