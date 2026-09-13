<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 11 — live write-path adoption. Additive link so a live-flow Order can be traced back to its
 * originating aggregate (Payment / Appointment / Subscription) and a duplicate PayFast ITN can never
 * double-create an Order.
 *
 * (source_type, source_ref) is unique when set. Nullable so imported/legacy orders are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'source_type')) {
                $table->string('source_type', 32)->nullable()->after('upstream_synced_at'); // appointment|subscription|payment
            }
            if (! Schema::hasColumn('orders', 'source_ref')) {
                $table->string('source_ref')->nullable()->after('source_type');
            }
        });

        // Unique per (source_type, source_ref) when both set — the idempotency backbone for live mirror.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            Schema::getConnection()->statement(
                'CREATE UNIQUE INDEX uq_orders_source ON orders (source_type, source_ref) WHERE source_ref IS NOT NULL'
            );
        } else {
            Schema::table('orders', fn (Blueprint $t) => $t->unique(['source_type', 'source_ref']));
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            Schema::getConnection()->statement('DROP INDEX IF EXISTS uq_orders_source');
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach (['source_type', 'source_ref'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
