<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Essential", "Premium"
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('price'); // in cents (e.g. 29900 = R299.00)
            $table->string('billing_cycle')->default('monthly'); // monthly, annually
            $table->unsignedInteger('cycle_frequency')->default(1); // every X months

            // Plan features/limits
            $table->unsignedInteger('consultations_per_month')->default(0); // 0 = unlimited
            $table->boolean('includes_chronic_renewals')->default(false);
            $table->boolean('includes_priority_booking')->default(false);
            $table->boolean('includes_messaging')->default(true);
            $table->json('features')->nullable(); // flexible feature list for display

            // Status
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
