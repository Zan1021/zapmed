<?php

namespace App\Console\Commands;

use App\Services\Contro\ControClient;
use App\Services\Contro\ControPullService;
use Illuminate\Console\Command;

/**
 * Pull Contro data into the staging layer (upstream_ingested_rows). EXTRACT+LOAD only, no reconcile,
 * no side-effects. See specs/contro-rebuild/03-contro-import-blueprint.md.
 *
 *   php artisan contro:pull                 # delta pull, all entities
 *   php artisan contro:pull orders          # delta pull, one entity
 *   php artisan contro:pull --backfill      # full backfill, all entities
 */
class ControPull extends Command
{
    protected $signature = 'contro:pull {entity? : One of the configured entity sets; omit for all}
                                        {--backfill : Ignore stored watermark and pull from the beginning}';

    protected $description = 'Pull Contro CRM data into the upstream staging tables (read-only ELT).';

    public function handle(): int
    {
        if (!config('contro.api.base_url')) {
            $this->error('CONTRO_API_BASE_URL is not set. Live creds/base URL are pending from Craig.');
            return self::FAILURE;
        }

        $service = new ControPullService(ControClient::fromConfig());
        $backfill = (bool) $this->option('backfill');
        $entity = $this->argument('entity');

        try {
            if ($entity) {
                $run = $service->pullEntity($entity, 'manual', $backfill);
                $this->report($entity, $run);
            } else {
                foreach ($service->pullAll('manual', $backfill) as $set => $run) {
                    $this->report($set, $run);
                }
            }
        } catch (\Throwable $e) {
            $this->error("Pull failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function report(string $entity, \App\Models\UpstreamSyncRun $run): void
    {
        $this->line(sprintf(
            '%-22s pulled=%d upserted=%d status=%s watermark=%s',
            $entity,
            $run->rows_pulled,
            $run->rows_upserted,
            $run->status,
            $run->watermark_to ?? '(none)'
        ));
    }
}
