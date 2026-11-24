<?php

namespace App\Console\Commands;

use App\Services\Doctor\AuthorisationService;
use Illuminate\Console\Command;

/**
 * Marks doctor authorisations whose six months have ended as "expired" so the review lists are right. The repeat
 * check itself works from the dates, so a missed run never lets an expired authorisation through.
 * Runs daily at 08:00 SA time, before the 09:30 repeat run (routes/console.php).
 */
class AuthorisationsExpire extends Command
{
    protected $signature = 'authorisations:expire';

    protected $description = 'Mark ended doctor authorisations as expired';

    public function handle(AuthorisationService $service): int
    {
        $this->info('Expired '.$service->expireLapsed().' authorisation(s).');

        return self::SUCCESS;
    }
}
