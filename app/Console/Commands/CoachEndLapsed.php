<?php

namespace App\Console\Commands;

use App\Services\Coach\CoachAssigner;
use Illuminate\Console\Command;

/**
 * Ends the coach of members whose coach add-on has lapsed (cancelled and past the paid period). Coaches an admin chose by hand
 * are never touched. Runs daily (routes/console.php). Safe to run any time.
 */
class CoachEndLapsed extends Command
{
    protected $signature = 'coach:end-lapsed';

    protected $description = 'End the coach of members whose coach add-on has lapsed';

    public function handle(CoachAssigner $assigner): int
    {
        $n = $assigner->endLapsed();
        $this->info("Ended {$n} coach assignment(s).");

        return self::SUCCESS;
    }
}