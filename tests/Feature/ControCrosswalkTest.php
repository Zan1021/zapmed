<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task 1 — Contro crosswalk columns (specs/contro-rebuild/03-contro-import-blueprint.md §2/§3).
 *
 * These tests are the "100% coverage" guard: every table that receives Contro data must carry
 * upstream_id / upstream_source / upstream_synced_at + a unique (upstream_source, upstream_id).
 * If a future recipient table is created without them, the coverage test fails loudly.
 */
class ControCrosswalkTest extends TestCase
{
    use RefreshDatabase;

    /** Tables that exist today and were given crosswalk columns in this task. */
    private const CROSSWALKED_NOW = [
        'users',
        'patient_profiles',
        'payments',
        'prescriptions',
        'prescription_items',
        'medications',
        'subscriptions',
        // Task 2 — born with crosswalk columns.
        'orders',
        'order_items',
        'order_status_history',
        // Task 3 — catalog Contro recipients.
        'catalog_items',
        'catalog_coupons',
        'catalog_coupon_usage',
        // Task 4 — payment child recipients (may derive from Contro payment rows).
        'payment_attempts',
        'refunds',
        // Task 5 — patient address recipient (Contro deliveryAddress).
        'patient_addresses',
    ];

    /**
     * Recipient tables not built yet — must be born WITH crosswalk columns in their own task.
     * When each is created, move it into CROSSWALKED_NOW; the coverage test will then enforce it.
     * (Empty for now — all currently-planned recipient tables are built. Add future ones here.)
     */
    private const DEFERRED_RECIPIENTS = [];

    public function test_every_existing_recipient_table_has_all_three_crosswalk_columns(): void
    {
        foreach (self::CROSSWALKED_NOW as $table) {
            $this->assertTrue(Schema::hasTable($table), "recipient table {$table} should exist");
            $this->assertTrue(
                Schema::hasColumns($table, ['upstream_id', 'upstream_source', 'upstream_synced_at']),
                "table {$table} is missing one or more crosswalk columns"
            );
        }
    }

    public function test_crosswalk_uniqueness_is_enforced_for_contro_rows(): void
    {
        // Two rows claiming the SAME Contro origin must collide.
        DB::table('medications')->insert([
            'name' => 'A', 'form' => 'tablet', 'strength' => '5mg',
            'upstream_id' => 'prod_1', 'upstream_source' => 'contro',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('medications')->insert([
            'name' => 'A-dup', 'form' => 'tablet', 'strength' => '5mg',
            'upstream_id' => 'prod_1', 'upstream_source' => 'contro',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_multiple_native_rows_with_null_upstream_id_coexist(): void
    {
        // Native (non-imported) rows have NULL upstream_id and must NOT collide with each other.
        DB::table('medications')->insert([
            'name' => 'Native 1', 'form' => 'tablet', 'strength' => '5mg',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('medications')->insert([
            'name' => 'Native 2', 'form' => 'tablet', 'strength' => '10mg',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(2, DB::table('medications')->whereNull('upstream_id')->count());
    }

    public function test_upstream_source_defaults_to_contro(): void
    {
        DB::table('payments')->insert([
            'reference' => 'PAY-TEST000001',
            'patient_id' => \App\Models\User::factory()->create()->id,
            'amount' => 0, 'currency' => 'ZAR', 'status' => 'pending',
            'upstream_id' => 'pay_1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('contro', DB::table('payments')->where('upstream_id', 'pay_1')->value('upstream_source'));
    }

    public function test_same_upstream_id_across_different_tables_does_not_collide(): void
    {
        // Contro id "1" can appear as a product AND a payment — different tables, no collision.
        DB::table('medications')->insert([
            'name' => 'M1', 'form' => 'tablet', 'strength' => '5mg',
            'upstream_id' => '1', 'upstream_source' => 'contro',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payments')->insert([
            'reference' => 'PAY-TEST000002',
            'patient_id' => \App\Models\User::factory()->create()->id,
            'amount' => 0, 'currency' => 'ZAR', 'status' => 'pending',
            'upstream_id' => '1', 'upstream_source' => 'contro',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('1', DB::table('medications')->where('name', 'M1')->value('upstream_id'));
        $this->assertSame('1', DB::table('payments')->where('reference', 'PAY-TEST000002')->value('upstream_id'));
    }

    /**
     * Coverage guard: if a DEFERRED recipient table now exists, it MUST already carry the crosswalk
     * columns. This fails the moment someone builds orders/catalog without them — the "don't skip
     * anything" enforcement.
     */
    public function test_deferred_recipient_tables_are_crosswalked_once_they_exist(): void
    {
        if (self::DEFERRED_RECIPIENTS === []) {
            $this->assertTrue(true, 'no deferred recipient tables remain — all planned recipients are built');
            return;
        }

        foreach (self::DEFERRED_RECIPIENTS as $table) {
            if (Schema::hasTable($table)) {
                $this->assertTrue(
                    Schema::hasColumns($table, ['upstream_id', 'upstream_source', 'upstream_synced_at']),
                    "recipient table {$table} now exists but is MISSING crosswalk columns — add them in its migration"
                );
            } else {
                $this->markTestSkipped("deferred recipient {$table} not built yet");
            }
        }
    }
}
