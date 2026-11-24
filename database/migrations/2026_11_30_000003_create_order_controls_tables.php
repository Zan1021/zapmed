<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operations controls on one order (Craig's guide 6.2):
 *  - order_discounts: a coupon on the monthly SERVICE fee of one order, for 1 to 6 months or until switched off. It can
 *    never reduce the medication fee.
 *  - order_audit_events: the chronological trail of what Operations did to the order (who, what, old and new, why).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('type', 8);                                // percent | fixed
            $table->unsignedInteger('value');                         // percent: basis points (1000 = 10%); fixed: cents off per month
            $table->unsignedTinyInteger('months')->nullable();        // 1 to 6, null = until switched off
            $table->date('starts_on');
            $table->date('ends_on')->nullable();                      // null when months is null
            $table->boolean('is_active')->default(true);
            $table->string('reason', 255);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('disabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disabled_at')->nullable();
            $table->string('disabled_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['order_id', 'is_active']);
        });

        Schema::create('order_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 32);                              // discount_added | discount_disabled | manual_payment | repeat_reset ...
            $table->string('summary', 255);
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_audit_events');
        Schema::dropIfExists('order_discounts');
    }
};
