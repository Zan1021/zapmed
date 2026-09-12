<?php

namespace Tests\Feature;

use App\Models\CatalogCoupon;
use App\Models\CatalogCouponUsage;
use App\Models\CatalogItem;
use App\Models\CatalogPrice;
use App\Models\Medication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task 3 — Catalog (products + coupons + coupon usage).
 * specs/contro-rebuild/03-contro-import-blueprint.md §2.2/2.3.
 */
class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_item_holds_contro_product_fields(): void
    {
        $item = CatalogItem::create([
            'name' => 'Metformin 500 (30 pack)',
            'item_type' => 'product',
            'nappi_code' => '703950001',
            'price_minor' => 12100,
            'pack_qty' => 30,
            'is_master_bundle' => false,
            'requires_subscription' => true,
            'status' => 'active',
            'upstream_id' => 'prod_1',
        ]);

        $fresh = $item->fresh();
        $this->assertSame(12100, $fresh->price_minor);
        $this->assertTrue($fresh->requires_subscription);
        $this->assertSame('contro', $fresh->upstream_source);
        $this->assertSame('30.000', (string) $fresh->pack_qty);
    }

    public function test_catalog_item_may_link_to_a_medication_but_need_not(): void
    {
        $med = Medication::create(['name' => 'Metformin', 'form' => 'tablet', 'strength' => '500mg']);
        $linked = CatalogItem::create(['name' => 'Metformin SKU', 'medication_id' => $med->id, 'price_minor' => 100]);
        $fee = CatalogItem::create(['name' => 'Delivery', 'item_type' => 'delivery_fee', 'price_minor' => 5000]);

        $this->assertSame($med->id, $linked->medication->id);
        $this->assertNull($fee->medication);
    }

    public function test_time_versioned_prices(): void
    {
        $item = CatalogItem::create(['name' => 'X', 'price_minor' => 100]);
        $item->prices()->create(['price_minor' => 100, 'effective_from' => now()->subMonth(), 'effective_to' => now()->subDay()]);
        $item->prices()->create(['price_minor' => 120, 'effective_from' => now()]);

        $this->assertSame(2, $item->prices()->count());
        // Ordered desc by effective_from — current price first.
        $this->assertSame(120, $item->prices()->first()->price_minor);
    }

    public function test_coupon_with_usage_children(): void
    {
        $coupon = CatalogCoupon::create([
            'code' => 'WELCOME10', 'type' => 'percent', 'value' => 1000, // 10% in bps
            'max_uses' => 100, 'upstream_id' => 'coup_1',
        ]);
        $coupon->usages()->create([
            'pre_value_minor' => 10000, 'post_value_minor' => 9000, 'is_redeemed' => true,
            'payment_reference_id' => 'PMT-1', 'upstream_id' => 'cu_1',
        ]);

        $this->assertSame(1, $coupon->usages()->count());
        $this->assertTrue($coupon->usages->first()->is_redeemed);
        $this->assertSame(1000, $coupon->fresh()->value);
    }

    public function test_coupon_crosswalk_is_idempotent(): void
    {
        CatalogCoupon::create(['code' => 'A', 'upstream_id' => 'coup_x', 'upstream_source' => 'contro']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        CatalogCoupon::create(['code' => 'A-dup', 'upstream_id' => 'coup_x', 'upstream_source' => 'contro']);
    }

    public function test_all_catalog_recipient_tables_carry_crosswalk_columns(): void
    {
        foreach (['catalog_items', 'catalog_coupons', 'catalog_coupon_usage'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertTrue(
                Schema::hasColumns($table, ['upstream_id', 'upstream_source', 'upstream_synced_at']),
                "{$table} missing crosswalk columns"
            );
        }
    }
}
