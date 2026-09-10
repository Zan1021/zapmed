<?php

namespace App\Console\Commands;

use Zapmed\SparCore\Services\SparDropIngestor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Automated FTP-drop ingestion (the scheduled counterpart to the manual admin
 * upload). Reads the configured drop directory — where the SPAR FTP account
 * writes the sales extract + Drug Usage report — pairs them, and imports via
 * the shared SparDropIngestor / SparImportService.
 *
 * Schedule (routes/console.php or the scheduler): daily/hourly as SPAR agree.
 * FTP transport itself is infrastructure (an FTP/SFTP account whose home is the
 * drop directory); this command only consumes the local directory it lands in.
 */
class SparIngestDrop extends Command
{
    protected $signature = 'spar:ingest-drop {--path= : Absolute path to the drop directory (overrides config)}';
    protected $description = 'Ingest SPAR files dropped via FTP (sales extract + Drug Usage report)';

    public function handle(SparDropIngestor $ingestor): int
    {
        $path = $this->option('path');

        if (!$path) {
            $disk = config('spar.import.drop_disk', 'local');
            $rel = config('spar.import.drop_path', 'spar-drop');
            $path = Storage::disk($disk)->path($rel);
        }

        $this->info("Scanning drop directory: {$path}");

        $result = $ingestor->ingestDirectory($path);

        $this->line(match ($result['status']) {
            'completed' => "<info>{$result['message']}</info>",
            'skipped' => "<comment>{$result['message']}</comment>",
            default => "<error>{$result['message']}</error>",
        });

        return $result['status'] === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
