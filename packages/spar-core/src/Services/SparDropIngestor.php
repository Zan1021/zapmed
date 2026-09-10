<?php

namespace Zapmed\SparCore\Services;

use Zapmed\SparCore\Models\SparImportBatch;
use Illuminate\Support\Facades\Log;

/**
 * FTP-drop ingestion (the automated counterpart to the manual admin upload).
 *
 * SPAR / their vendor drop the two files (sales extract + Drug Usage report)
 * into a directory exposed over FTP. A scheduled command calls this ingestor,
 * which pairs the two by filename and runs the SAME SparImportService the admin
 * screen uses — one import code path, two front doors.
 *
 * This class does NOT talk FTP itself: it reads a local directory. In
 * production that directory is where the FTP account writes (an FTP server /
 * mounted home is infra provisioned at deploy). Keeping the network transport
 * out of the app means the ingestion logic is unit-testable and the FTP setup
 * can change (SFTP, S3 drop, shared mount) without touching this code.
 */
class SparDropIngestor
{
    public function __construct(private ?SparImportService $service = null)
    {
        $this->service = $service ?? new SparImportService();
    }

    /**
     * Scan a directory for a sales file (+ optional Drug Usage file), import the
     * newest pair, and archive/delete the source files.
     *
     * @return array{status:string, message:string, batch_id:?int}
     */
    public function ingestDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            return ['status' => 'skipped', 'message' => "Drop directory not found: {$directory}", 'batch_id' => null];
        }

        $salesMatch = strtolower((string) config('spar.import.sales_match', 'salesextract'));
        $drugMatch = strtolower((string) config('spar.import.drug_usage_match', 'drug usage'));

        $files = array_values(array_filter(
            scandir($directory) ?: [],
            fn ($f) => !in_array($f, ['.', '..'], true) && is_file($directory . DIRECTORY_SEPARATOR . $f)
        ));

        $salesFile = $this->newestMatching($directory, $files, $salesMatch, ['csv', 'txt']);
        $drugFile = $this->newestMatching($directory, $files, $drugMatch, ['xlsx', 'xls']);

        if ($salesFile === null) {
            return ['status' => 'skipped', 'message' => 'No sales extract file found in drop directory.', 'batch_id' => null];
        }

        try {
            if ($drugFile !== null) {
                $batch = $this->service->importPair($salesFile, $drugFile, null, 'ftp');
            } else {
                // Sales-only ingest still works (dispense history without contact).
                $batch = $this->service->importFile($salesFile, null, 'ftp');
            }
        } catch (\Throwable $e) {
            Log::error('SPAR drop ingest failed', ['error' => $e->getMessage()]);

            return ['status' => 'failed', 'message' => $e->getMessage(), 'batch_id' => null];
        }

        // importPair/importFile already delete the source files (PHI). If a file
        // somehow survived and archiving is on, move it; else make sure it's gone.
        $this->finaliseSource($salesFile);
        if ($drugFile) {
            $this->finaliseSource($drugFile);
        }

        return [
            'status' => $batch->status,
            'message' => sprintf(
                'Ingested %s%s → %d created, %d updated, %d skipped, %d failed.',
                basename($salesFile),
                $drugFile ? ' + ' . basename($drugFile) : '',
                $batch->records_created,
                $batch->records_updated,
                $batch->records_skipped,
                $batch->records_failed
            ),
            'batch_id' => $batch->id,
        ];
    }

    /**
     * @param array<int,string> $files
     * @param array<int,string> $extensions
     */
    private function newestMatching(string $dir, array $files, string $needle, array $extensions): ?string
    {
        $candidates = [];
        foreach ($files as $f) {
            $lower = strtolower($f);
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (str_contains($lower, $needle) && in_array($ext, $extensions, true)) {
                $full = $dir . DIRECTORY_SEPARATOR . $f;
                $candidates[$full] = filemtime($full) ?: 0;
            }
        }
        if (empty($candidates)) {
            return null;
        }
        arsort($candidates); // newest first

        return array_key_first($candidates);
    }

    private function finaliseSource(string $path): void
    {
        if (!is_file($path)) {
            return; // already deleted by the import service (expected for PHI)
        }

        if (config('spar.import.archive_processed', false)) {
            $archiveDir = dirname($path) . DIRECTORY_SEPARATOR . 'processed';
            if (!is_dir($archiveDir)) {
                @mkdir($archiveDir, 0700, true);
            }
            @rename($path, $archiveDir . DIRECTORY_SEPARATOR . date('Ymd_His_') . basename($path));

            return;
        }

        @unlink($path);
    }
}
