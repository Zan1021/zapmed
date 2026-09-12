<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2 — Order aggregate + immutable status history (THE SPINE).
 *
 * Mirrors Mark's orders module (specs/contro-rebuild/01-system-design-dossier.md §4,
 * 03-contro-import-blueprint.md §2.4/2.5). The order lifecycle is a 25-status state machine whose
 * transition RULES are seeded as DATA (see database/seeders/OrderStatusTransitionSeeder + config/orders.php),
 * not hard-coded, so Contro's history can be validated against them at import time.
 *
 * Money is stored in MINOR units (cents, integer), currency ZAR — consistent with the rest of the app
 * (payments.amount, prescriptions.total_amount are all cents).
 *
 * Contro status spelling is preserved VERBATIM, including the misspelling 'PendingConsulation'.
 *
 * These tables are BORN WITH the Contro crosswalk columns (Task 1 idempotency backbone) — the coverage
 * guard in tests/Feature/ControCrosswalkTest.php enforces this the moment the tables exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- orders: the aggregate root ---------------------------------------------------------
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();            // ZM-YYYY-NNNNNN (our numbering)
            $table->string('contro_order_number')->nullable()->index(); // Contro orderNumber (verbatim)

            $table->foreignId('patient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();

            // Current status (validated against seeded transitions). Verbatim Contro spelling.
            $table->string('status', 40)->default('PendingPayment')->index();

            // Money snapshot (minor units, ZAR).
            $table->integer('service_fee_minor')->default(0);
            $table->integer('medication_fee_minor')->default(0);
            $table->integer('total_minor')->default(0);
            $table->string('currency', 3)->default('ZAR');

            // Contro passthrough / classification.
            $table->boolean('is_subscription')->default(false);
            $table->string('service_category')->nullable();       // Contro passthrough label (NOT an FK)
            $table->string('payment_type')->nullable();           // Contro: card/medical_aid/eft/...

            // Repeat / lifecycle.
            $table->date('next_repeat_date')->nullable();
            $table->string('pharmacy_script_ref')->nullable();

            // Cancellation / pause.
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('paused_at')->nullable();

            // Delivery snapshot (denormalised at order time).
            $table->text('delivery_address')->nullable();
            $table->string('delivery_city', 100)->nullable();
            $table->string('delivery_province', 50)->nullable();
            $table->string('delivery_postal_code', 10)->nullable();
            $table->string('delivery_phone', 20)->nullable();
            $table->text('delivery_instructions')->nullable();

            $table->timestamp('ordered_at')->nullable();          // Contro orderDate
            $table->json('metadata')->nullable();

            // --- Contro crosswalk (Task 1 backbone) ---
            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index(['patient_id', 'status']);
        });

        // ---- order_items: catalog + tax snapshot per line ---------------------------------------
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medication_id')->nullable()->constrained()->nullOnDelete(); // catalog ref (soft)
            $table->string('description');                        // denormalised name at order time
            $table->string('nappi_code', 20)->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('unit_price_minor')->default(0);
            $table->integer('line_total_minor')->default(0);
            // Tax snapshot (basis points; ZA VAT 15% = 1500 bps).
            $table->unsignedInteger('tax_bps')->default(0);
            $table->integer('tax_minor')->default(0);
            $table->json('metadata')->nullable();

            // --- Contro crosswalk ---
            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index('order_id');
        });

        // ---- order_status_history: IMMUTABLE audit ----------------------------------------------
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();        // null = initial placement
            $table->string('to_status', 40);
            $table->string('trigger_type', 20);                   // payment|calendar|doctor|system_timer|patient|rxhub|admin
            $table->string('triggered_by')->nullable();           // principal / actor ref
            $table->text('notes')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            // --- Contro crosswalk ---
            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            // NOTE: append-only. No updated_at — history rows are never mutated.
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index(['order_id', 'occurred_at']);
        });

        // ---- order_status_transitions: the state machine RULES as DATA ---------------------------
        // Seeded from config/orders.php by OrderStatusTransitionSeeder. The importer validates Contro
        // history against these rows (and the OrderStatusMachine service reads the same config).
        Schema::create('order_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('from_status', 40);
            $table->string('to_status', 40);
            $table->string('trigger_type', 20);
            $table->timestamps();

            $table->unique(['from_status', 'to_status', 'trigger_type']);
        });

        // ---- order_rxhub_event_map: inbound RxHub event code -> resulting status ------------------
        Schema::create('order_rxhub_event_map', function (Blueprint $table) {
            $table->id();
            $table->string('event_code', 40)->unique();
            $table->string('to_status', 40);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_rxhub_event_map');
        Schema::dropIfExists('order_status_transitions');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
