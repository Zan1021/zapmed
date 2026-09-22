<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPAR Close-the-Loop, Wave C1 (spar-close-the-loop design §1.4, FR-C1).
 *
 * The patient "Order next meds" flow lets a member choose HOW they want the
 * order handled and captures a lightweight payment intent. We keep the existing
 * `type` column (collection|delivery) as the coarse fulfilment channel and add:
 *
 *   fulfilment_mode — collect_pay_now | deliver_pay_now | collect_pay_store
 *                     (the dropdown the patient picks; drives copy + payment intent)
 *   payment_status  — unpaid | pay_at_store | paid  (demo-grade intent only —
 *                     NFR-5: no real gateway wiring, we only capture the choice)
 *
 * Orders already carry the Wave B action_status lifecycle columns, so they
 * auto-drop from the pharmacy dashboard when the underlying dispense is
 * reconciled (FR-B5). Package-pure, SQLite-safe, idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spar_orders')) {
            return;
        }

        Schema::table('spar_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('spar_orders', 'fulfilment_mode')) {
                $table->string('fulfilment_mode')->nullable()->after('type');
            }
            if (! Schema::hasColumn('spar_orders', 'payment_status')) {
                $table->string('payment_status')->default('unpaid')->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('spar_orders')) {
            return;
        }

        Schema::table('spar_orders', function (Blueprint $table) {
            foreach (['fulfilment_mode', 'payment_status'] as $column) {
                if (Schema::hasColumn('spar_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
