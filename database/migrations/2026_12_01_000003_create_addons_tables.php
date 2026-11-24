<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add-ons on an order (Craig's guide 6.2): extras such as health coach support. Operations keep the list of add-ons and the
 * service categories each one is offered for; an order can then have add-ons that are active, paused or cancelled. The price is
 * copied onto the order's add-on when it is added, so a later price change never alters an existing order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedInteger('price_cents');                 // per month
            $table->json('categories')->nullable();                 // words a service category must contain; empty = offered everywhere
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('order_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('addons')->restrictOnDelete();
            $table->unsignedInteger('price_cents');                 // copied from the add-on when it was added
            $table->string('status', 10)->default('active');        // active | paused | cancelled
            $table->date('started_on');
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('reason', 255)->nullable();              // why it was paused or cancelled
            $table->string('initiator', 12)->nullable();            // patient | operations
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_addons');
        Schema::dropIfExists('addons');
    }
};
