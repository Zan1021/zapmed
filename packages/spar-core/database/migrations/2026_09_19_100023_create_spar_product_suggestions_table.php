<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Health Coach v1 (spec spar-health-coach §2.3). A product the coach (pharmacy
 * staff) suggests in-thread. Kept in its own table (1:1 with the suggestion
 * message) so upsell accept/decline analytics stay clean (OQ-4) and messages
 * stay lean. On accept, links to the order + line item it created.
 *
 * `product_name`/`note` are marketing copy, NOT PHI → not encrypted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spar_product_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spar_message_id')->constrained('spar_messages')->cascadeOnDelete();
            $table->foreignId('spar_conversation_id')->constrained('spar_conversations')->cascadeOnDelete();
            $table->string('product_name');
            $table->unsignedInteger('price_cents')->nullable();
            $table->string('note')->nullable();
            $table->string('status')->default('offered'); // offered | accepted | declined
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->foreignId('spar_order_id')->nullable()->constrained('spar_orders')->nullOnDelete();
            $table->foreignId('spar_order_item_id')->nullable()->constrained('spar_order_items')->nullOnDelete();
            $table->timestamps();

            $table->index(['spar_conversation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_product_suggestions');
    }
};
