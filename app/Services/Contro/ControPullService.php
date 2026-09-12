<?php

namespace App\Services\Contro;

use App\Models\UpstreamIngestedRow;
use App\Models\UpstreamSyncRun;
use App\Models\UpstreamSyncState;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Throwable;

/**
 * Contro ELT pull (specs/contro-rebuild/03-contro-import-blueprint.md §3, phase E-L).
 *
 * Pulls an entity set page-by-page (watermark-filtered) and lands RAW rows into upstream_ingested_rows
 * (newer-wins upsert on entity_set+upstream_id). Advances upstream_sync_states watermark and records a
 * upstream_sync_run audit. This is EXTRACT+LOAD only — NO reconcile into canonical tables here, and NO
 * side-effects (no emails/payments/pharmacy). Reconcile is a separate, later step (Task 7).
 *
 * Idempotent: re-running a pull re-lands the same rows (upsert), never duplicates.
 */
class ControPullService
{
    public function __construct(private readonly ControClient $client)
    {
    }

    /**
     * Pull a single entity set. If $backfill, ignores the stored watermark (starts from the beginning).
     */
    public function pullEntity(string $entitySet, string $trigger = 'manual', bool $backfill = false): UpstreamSyncRun
    {
        $entities = config('contro.entities');
        if (!isset($entities[$entitySet])) {
            throw new InvalidArgumentException("Unknown Contro entity set: {$entitySet}");
        }

        $cfg = $entities[$entitySet];
        $idField = $cfg['id_field'];
        $watermarkField = $cfg['watermark_field'];
        $pageSize = (int) config('contro.api.page_size', 100);

        $state = UpstreamSyncState::firstOrCreate(['entity_set' => $entitySet]);
        $since = $backfill ? null : $state->last_high_watermark;

        $run = UpstreamSyncRun::create([
            'entity_set' => $entitySet,
            'trigger' => $backfill ? 'backfill' : $trigger,
            'watermark_from' => $since,
        ]);

        try {
            $skip = 0;
            $pulled = 0;
            $upserted = 0;
            $highWatermark = $since;
            $maxPages = (int) config('contro.api.max_pages', 100000); // safety cap against runaway paging
            $page = 0;

            do {
                $rows = $this->client->fetchPage($cfg['path'], $watermarkField, $since, $skip, $pageSize);
                $count = count($rows);

                if ($count === 0) {
                    break; // no more rows
                }

                foreach ($rows as $row) {
                    $upstreamId = $this->stringId(Arr::get($row, $idField));
                    if ($upstreamId === null) {
                        // Missing id — cannot key it; skip (reconciler-independent guard).
                        continue;
                    }
                    $rowWatermark = $this->stringId(Arr::get($row, $watermarkField));

                    UpstreamIngestedRow::updateOrCreate(
                        ['entity_set' => $entitySet, 'upstream_id' => $upstreamId],
                        [
                            'payload' => $row,
                            'upstream_updated_at' => $this->parseTimestamp($rowWatermark),
                            'sync_run_id' => $run->id,
                            'pulled_at' => now(),
                        ],
                    );
                    $upserted++;

                    // Track the max watermark seen (rows are ordered asc, so last wins).
                    if ($rowWatermark !== null && ($highWatermark === null || $rowWatermark > $highWatermark)) {
                        $highWatermark = $rowWatermark;
                    }
                }

                $pulled += $count;
                $skip += $pageSize;
                $page++;
            } while ($count === $pageSize && $page < $maxPages); // full page => maybe more (capped)

            // Advance the watermark only forward.
            if ($highWatermark !== null && $highWatermark !== $since) {
                $state->update(['last_high_watermark' => $highWatermark, 'last_synced_at' => now()]);
            } else {
                $state->update(['last_synced_at' => now()]);
            }

            $run->update([
                'rows_pulled' => $pulled,
                'rows_upserted' => $upserted,
                'watermark_to' => $highWatermark,
            ]);
            $run->markCompleted();
        } catch (Throwable $e) {
            $run->markFailed($e->getMessage());
            throw $e;
        }

        return $run->fresh();
    }

    /** Pull every configured entity set (dependency order is irrelevant for staging — raw land). */
    public function pullAll(string $trigger = 'manual', bool $backfill = false): array
    {
        $runs = [];
        foreach (array_keys(config('contro.entities')) as $entitySet) {
            $runs[$entitySet] = $this->pullEntity($entitySet, $trigger, $backfill);
        }

        return $runs;
    }

    /** Contro int64 ids arrive as strings; coerce everything to string (or null) — never to int. */
    private function stringId(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function parseTimestamp(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateTimeString();
        } catch (Throwable) {
            return null; // unparseable watermark — keep the raw string as the watermark, but null the ts column
        }
    }
}
