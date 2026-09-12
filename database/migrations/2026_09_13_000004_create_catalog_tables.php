<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 3 — Catalog: products (catalog_items) + coupons + coupon usage.
 *
 * Receives Contro `products` and `coupons` (specs/contro-rebuild/03-contro-import-blueprint.md §2.2/2.3).
 * Money in MINOR units (cents), ZAR.
 *
 * RELATIONSHIP TO `medications`:
 *   `medications` remains the CLINICAL reference table (schedule, contraindications, interactions — what
 *   a doctor prescribes). `catalog_items` is the COMMERCE catalog (sellable SKUs/bundles/fees with
 *   pricing) that Contro `products` import into, per the blueprint's `catalog_item` mapping. A catalog
 *   item MAY link to a medication (nullable FK) but need not (e.g. delivery_fee, service_fee, bundles).
 *   This mirrors Mark's split (catalog module distinct from clinical). We do NOT overload medications.
 *
 * Born WITH Contro crosswalk columns (Task 1 backbone); the coverage guard in ControCrosswalkTest
 * enforces this for catalog_coupons + catalog_coupon_usage once they exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- catalog_items: sellable products / SKUs / bundles / fees ----------------------------
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('item_type', 30)->default('product');
            // product|prescription|consultation|bundle|service_fee|delivery_fee|other
            $table->foreignId('medication_id')->nullable()->constrained()->nullOnDelete(); // optional clinical link
            $table->string('nappi_code', 20)->nullable();
            $table->string('schedule_class', 10)->nullable();  // S0-S8
            $table->integer('price_minor')->default(0);        // current price (cents); see catalog_prices for history
            $table->string('currency', 3)->default('ZAR');
            $table->string('tax_class', 20)->default('standard'); // standard|zero|exempt|medical
            $table->decimal('pack_qty', 12, 3)->nullable();    // Contro packQty (numeric)
            $table->boolean('is_master_bundle')->default(false);
            $table->boolean('requires_subscription')->default(false);
            $table->string('status', 20)->default('active');   // active|retired|draft|archived (isEnabled->active/retired)
            $table->json('metadata')->nullable();

            // --- Contro crosswalk ---
            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index('nappi_code');
            $table->index('status');
        });

        // ---- catalog_prices: time-versioned price history ---------------------------------------
        // Blueprint §2.2 "price -> price (minor, time-versioned)". Keeps the price a product had at any
        // point (order_items snapshot the price used, but the catalog keeps the timeline).
        Schema::create('catalog_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->integer('price_minor');
            $table->string('currency', 3)->default('ZAR');
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable(); // null = current
            $table->timestamps();

            $table->index(['catalog_item_id', 'effective_from']);
        });

        // ---- catalog_coupons ---------------------------------------------------------------------
        Schema::create('catalog_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->index();
            $table->string('type', 20)->default('percent'); // percent|fixed|free_shipping
            $table->integer('value')->default(0);            // bps for percent, minor units for fixed
            $table->timestamp('expiry_dt')->nullable();
            $table->unsignedInteger('number_of_uses')->default(0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->timestamp('contro_created_dt')->nullable();
            $table->json('metadata')->nullable();

            // --- Contro crosswalk ---
            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
        });

        // ---- catalog_coupon_usage (Contro CouponDto.usage[]) -------------------------------------
        Schema::create('catalog_coupon_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_coupon_id')->constrained()->cascadeOnDelete();
            $table->integer('pre_value_minor')->nullable();
            $table->integer('post_value_minor')->nullable();
            $table->boolean('is_redeemed')->default(false);
            $table->string('payment_reference_id')->nullable();
            $table->timestamp('expiry_dt')->nullable();
            $table->timestamp('contro_create_dt')->nullable();

            // --- Contro crosswalk ---
            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index('catalog_coupon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_coupon_usage');
        Schema::dropIfExists('catalog_coupons');
        Schema::dropIfExists('catalog_prices');
        Schema::dropIfExists('catalog_items');
    }
};
