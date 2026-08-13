<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->string('category', 50)->nullable()->after('nappi_code'); // weight-loss, skincare, sexual-health, etc.
            $table->integer('price_cents')->nullable()->after('category'); // price in cents (e.g. 121000 = R1,210.00)
            $table->integer('repeat_cycle_days')->nullable()->after('price_cents'); // 30 = monthly, 90 = quarterly
            $table->boolean('is_subscription')->default(false)->after('repeat_cycle_days'); // ongoing repeat med
            $table->text('description')->nullable()->after('is_subscription'); // patient-facing description
            $table->text('dosage_instructions')->nullable()->after('description'); // e.g. "Take once daily with food"
            $table->string('manufacturer')->nullable()->after('dosage_instructions');
            $table->integer('sort_order')->default(0)->after('manufacturer');
        });
    }

    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->dropColumn([
                'category', 'price_cents', 'repeat_cycle_days',
                'is_subscription', 'description', 'dosage_instructions',
                'manufacturer', 'sort_order',
            ]);
        });
    }
};
