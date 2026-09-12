<?php

namespace Tests\Feature;

use App\Models\UpstreamIngestedRow;
use App\Models\UpstreamSyncState;
use App\Services\Contro\ControClient;
use App\Services\Contro\ControPullService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Task 6 — Contro pull service, tested against FIXTURES via Http::fake (no live calls).
 * specs/contro-rebuild/03-contro-import-blueprint.md §1/§3.
 */
class ControPullServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('contro.api.base_url', 'https://contro.example');
        config()->set('contro.api.email', 'svc@zapmed');
        config()->set('contro.api.password', 'secret');
        config()->set('contro.api.page_size', 2); // small pages to exercise pagination
    }

    private function service(): ControPullService
    {
        return new ControPullService(ControClient::fromConfig());
    }

    private function fakeAuth(): array
    {
        return ['https://contro.example/api/crm/auth/login' => Http::response(['token' => 'tok-123'], 200)];
    }

    public function test_pulls_paginated_products_into_staging_and_advances_watermark(): void
    {
        Http::fake(array_merge($this->fakeAuth(), [
            // products path: two pages (page size 2) then an empty page terminates paging.
            'https://contro.example/api/crm/products*' => Http::sequence()
                ->push([
                    ['id' => '1', 'productName' => 'A', 'dtCreatedModified' => '2026-01-01T00:00:00Z'],
                    ['id' => '2', 'productName' => 'B', 'dtCreatedModified' => '2026-01-02T00:00:00Z'],
                ], 200)
                ->push([
                    ['id' => '3', 'productName' => 'C', 'dtCreatedModified' => '2026-01-03T00:00:00Z'],
                ], 200)
                ->whenEmpty(Http::response([], 200)),
        ]));

        $run = $this->service()->pullEntity('products', 'manual', true);

        $this->assertSame('completed', $run->status);
        $this->assertSame(3, $run->rows_upserted);
        $this->assertSame(3, UpstreamIngestedRow::forEntity('products')->count());
        // Watermark advanced to the max seen.
        $this->assertSame('2026-01-03T00:00:00Z', UpstreamSyncState::where('entity_set', 'products')->value('last_high_watermark'));
    }

    public function test_landing_is_idempotent_on_repull(): void
    {
        Http::fake(array_merge($this->fakeAuth(), [
            'https://contro.example/api/crm/patients*' => Http::response([
                ['userHash' => 'hash_a', 'firstName' => 'Priya', 'lastModifiedDT' => '2026-02-01T00:00:00Z'],
            ], 200),
        ]));

        $this->service()->pullEntity('patients', 'manual', true);
        $this->service()->pullEntity('patients', 'manual', true); // re-pull same data

        // One row, not two — keyed on (entity_set, upstream_id=userHash).
        $this->assertSame(1, UpstreamIngestedRow::forEntity('patients')->count());
        $row = UpstreamIngestedRow::forEntity('patients')->first();
        $this->assertSame('hash_a', $row->upstream_id);
        $this->assertSame('Priya', $row->payload['firstName']);
    }

    public function test_patient_is_keyed_by_userhash_not_a_numeric_id(): void
    {
        Http::fake(array_merge($this->fakeAuth(), [
            'https://contro.example/api/crm/patients*' => Http::response([
                ['userHash' => 'abc-hash', 'firstName' => 'X', 'lastModifiedDT' => '2026-03-01T00:00:00Z'],
            ], 200),
        ]));

        $this->service()->pullEntity('patients', 'manual', true);

        $this->assertNotNull(UpstreamIngestedRow::where('upstream_id', 'abc-hash')->first());
    }

    public function test_delta_pull_uses_stored_watermark_in_filter(): void
    {
        UpstreamSyncState::create(['entity_set' => 'orders', 'last_high_watermark' => '2026-05-01T00:00:00Z']);

        Http::fake(array_merge($this->fakeAuth(), [
            'https://contro.example/api/crm/orders*' => Http::response([], 200),
        ]));

        $this->service()->pullEntity('orders', 'manual', false);

        // Assert the outgoing request carried the $filter with the stored watermark.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/crm/orders')
                && str_contains(urldecode($request->url()), 'dtCreatedModified gt 2026-05-01T00:00:00Z');
        });
    }

    public function test_rows_without_id_are_skipped_not_fatal(): void
    {
        Http::fake(array_merge($this->fakeAuth(), [
            'https://contro.example/api/crm/coupons*' => Http::sequence()
                ->push([
                    ['id' => 'coup_1', 'code' => 'OK', 'lastModifiedDt' => '2026-01-01T00:00:00Z'],
                    ['code' => 'NO_ID'], // missing id — must be skipped, not crash
                ], 200)
                ->whenEmpty(Http::response([], 200)),
        ]));

        $run = $this->service()->pullEntity('coupons', 'manual', true);

        $this->assertSame('completed', $run->status);
        $this->assertSame(1, UpstreamIngestedRow::forEntity('coupons')->count());
    }

    public function test_pull_does_not_write_any_canonical_tables(): void
    {
        Http::fake(array_merge($this->fakeAuth(), [
            'https://contro.example/api/crm/orders*' => Http::response([
                ['id' => '99', 'orderNumber' => 'ZM-1', 'dtCreatedModified' => '2026-01-01T00:00:00Z'],
            ], 200),
        ]));

        $this->service()->pullEntity('orders', 'manual', true);

        // Staging got the row; canonical `orders` table stays empty (no reconcile, no side-effects).
        $this->assertSame(1, UpstreamIngestedRow::forEntity('orders')->count());
        $this->assertSame(0, \App\Models\Order::count());
    }
}
