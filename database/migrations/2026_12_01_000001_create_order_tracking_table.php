<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Courier tracking for an order (Craig's guide 6.2): tracking number, courier, status, link, pharmacy reference and when it
 * was last updated, shown to Operations and to the patient. Today Operations type it in; the DPS Laser feed can write the same
 * rows later (source = dps). Every update is a new row, so the history is kept and the newest row is what is shown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('courier', 60);
            $table->string('tracking_number', 60);
            $table->string('status', 20);
            $table->string('url', 500)->nullable();
            $table->string('pharmacy_reference', 60)->nullable();
            $table->string('source', 8)->default('manual');       // manual | dps
            $table->timestamp('tracked_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tracking');
    }
};
