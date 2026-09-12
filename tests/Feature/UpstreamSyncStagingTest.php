<?php

namespace Tests\Feature;

use App\Models\UpstreamIngestedRow;
use App\Models\UpstreamSyncRun;
use App\Models\UpstreamSyncState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contro ingestion staging layer (specs/contro-rebuild/03-contro-import-blueprint.md).
 *
 * These guard the invariants the import depends on:
 *  - one row per (entity_set, upstream_id) — idempotent, newer-wins re-pulls
 *  - raw payload survives round-trip as an array (json cast)
 *  - the 7 Contro entities are configured with the EXACT (deliberately inconsistent) watermark casing
 *  - the owned-system gateway is PayFast (Contro 'peach' maps to it)
 */
class UpstreamSyncStagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingested_row_is_idempotent_per_entity_and_upstream_id(): void
    {
        $run = UpstreamSyncRun::create(['entity_set' => 'patients', 'trigger' => 'backfill']);
        $key = ['entity_set' => 'patients', 'upstream_id' => 'hash_abc'];

        UpstreamIngestedRow::updateOrCreate($key, [
            'payload' => ['firstName' => 'A'],
            'upstream_updated_at' => now()->subDay(),
            'sync_run_id' => $run->id,
        ]);
        // Re-pull of the SAME Contro record must update in place, not duplicate.
        UpstreamIngestedRow::updateOrCreate($key, [
            'payload' => ['firstName' => 'B'],
            'upstream_updated_at' => now(),
            'sync_run_id' => $run->id,
        ]);

        $this->assertSame(1, UpstreamIngestedRow::where($key)->count());
        $this->assertSame('B', UpstreamIngestedRow::where($key)->first()->payload['firstName']);
    }

    public function test_same_upstream_id_across_different_entities_coexists(): void
    {
        // A products row and a payments row can share the numeric id "42" — they must NOT collide.
        UpstreamIngestedRow::create([
            'entity_set' => 'products', 'upstream_id' => '42', 'payload' => ['productName' => 'X'],
        ]);
        UpstreamIngestedRow::create([
            'entity_set' => 'payments', 'upstream_id' => '42', 'payload' => ['amount' => 100],
        ]);

        $this->assertSame(2, UpstreamIngestedRow::where('upstream_id', '42')->count());
    }

    public function test_payload_round_trips_as_array(): void
    {
        $row = UpstreamIngestedRow::create([
            'entity_set' => 'orders',
            'upstream_id' => 'ord_1',
            'payload' => ['orderNumber' => 'ZM-2026-000001', 'nested' => ['a' => 1]],
        ]);

        $fresh = $row->fresh();
        $this->assertIsArray($fresh->payload);
        $this->assertSame('ZM-2026-000001', $fresh->payload['orderNumber']);
        $this->assertSame(1, $fresh->payload['nested']['a']);
    }

    public function test_run_lifecycle_and_relation(): void
    {
        $run = UpstreamSyncRun::create(['entity_set' => 'orders', 'trigger' => 'manual']);
        $row = UpstreamIngestedRow::create([
            'entity_set' => 'orders', 'upstream_id' => 'ord_2', 'payload' => [], 'sync_run_id' => $run->id,
        ]);

        $this->assertSame('running', $run->status);
        $run->markCompleted();
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertNotNull($run->fresh()->finished_at);
        $this->assertSame($run->id, $row->syncRun->id);
    }

    public function test_sync_state_is_unique_per_entity(): void
    {
        UpstreamSyncState::create(['entity_set' => 'patients', 'last_high_watermark' => '2026-01-01']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        UpstreamSyncState::create(['entity_set' => 'patients', 'last_high_watermark' => '2026-02-01']);
    }

    public function test_contro_config_has_all_seven_entities_with_verbatim_watermarks(): void
    {
        $entities = config('contro.entities');
        $this->assertCount(7, $entities);

        // Deliberately inconsistent casing straight from Contro — must be preserved exactly.
        $this->assertSame('userHash', $entities['patients']['id_field']);
        $this->assertSame('lastModifiedDT', $entities['patients']['watermark_field']);      // upper T
        $this->assertSame('lastModifiedDt', $entities['prescriptions']['watermark_field']); // lower t
        $this->assertSame('dtCreatedModified', $entities['orders']['watermark_field']);
        $this->assertSame('changedAt', $entities['order_status_history']['watermark_field']);

        // Owned-system gateway confirmed PayFast.
        $this->assertSame('payfast', config('contro.payment_gateway'));
    }
}
