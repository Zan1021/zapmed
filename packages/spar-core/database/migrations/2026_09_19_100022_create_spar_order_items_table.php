<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Health Coach v1 (spec spar-health-coach §2.4). Line items on a SPAR order —
 * the "basket". A SparOrder previously wrapped a single dispense with no lines;
 * coach-suggested extras attach here so one order can hold a dispense + extras
 * without perverting the dispense model. Source distinguishes provenance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spar_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spar_order_id')->constrained('spar_orders')->cascadeOnDelete();
            $table->string('source')->default('coach_suggestion'); // coach_suggestion | manual
            $table->string('product_name');
            $table->unsignedInteger('price_cents')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('spar_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_order_items');
    }
};
