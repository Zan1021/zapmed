<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Reloads PHP once per new release so the web server's code cache does not fill up with old releases (that made every page slow).
 * Runs every minute from the scheduler and does nothing unless RELOAD_PHP_ON_RELEASE=true. It remembers the release it last handled in a
 * small file, so a release is only ever handled once, and a failed reload is tried again next minute.
 */
class ReloadPhpOnRelease extends Command
{
    protected $signature = 'server:reload-php-on-release';

    protected $description = 'Reload PHP once after a new release is activated (clears the code cache)';

    public function handle(): int
    {
        if (! config('operations.server.reload_php_on_release')) {
            return self::SUCCESS;
        }

        $release = (string) (realpath(base_path()) ?: base_path());
        $marker = storage_path('framework/php-reloaded-for-release');

        if (File::exists($marker) && trim((string) File::get($marker)) === $release) {
            return self::SUCCESS;     // this release was already handled
        }

        $result = Process::timeout(60)->run(config('operations.server.reload_command'));

        if (! $result->successful()) {
            Log::warning('PHP reload after a new release failed', ['release' => $release, 'error' => trim($result->errorOutput() ?: $result->output())]);
            $this->warn('Reload failed; it will be tried again.');

            return self::FAILURE;
        }

        File::put($marker, $release);
        Log::info('PHP reloaded for a new release', ['release' => $release]);
        $this->info('PHP reloaded for '.$release);

        return self::SUCCESS;
    }
}