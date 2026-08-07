<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();

            $table->string('status')->default('pending');
            // pending, active, paused, cancelled, expired, payment_failed

            // PayFast subscription data
            $table->string('payfast_token')->nullable(); // PayFast subscription token
            $table->string('payment_reference')->nullable()->unique();

            // Billing cycle tracking
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('next_billing_date')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable(); // when subscription actually terminates

            // Usage tracking
            $table->unsignedInteger('consultations_used_this_period')->default(0);

            // Payment history
            $table->unsignedInteger('total_paid')->default(0); // total in cents
            $table->unsignedInteger('payment_count')->default(0);
            $table->timestamp('last_payment_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('payfast_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
