<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add price to medications
        Schema::table('medications', function (Blueprint $table) {
            $table->unsignedInteger('price')->default(0)->after('nappi_code'); // price in cents
        });

        // Add unit price to prescription items
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->unsignedInteger('unit_price')->default(0)->after('quantity'); // price per unit in cents
            $table->unsignedInteger('line_total')->default(0)->after('unit_price'); // quantity * unit_price
        });

        // Add payment and pharmacy fields to prescriptions
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->unsignedInteger('total_amount')->default(0)->after('notes'); // total medication cost in cents
            $table->string('payment_status')->default('unpaid')->after('total_amount');
            // unpaid, pending, paid, failed
            $table->string('payment_reference')->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_reference');

            // Delivery address (where pharmacy delivers medication)
            $table->text('delivery_address')->nullable()->after('paid_at');
            $table->string('delivery_city', 100)->nullable()->after('delivery_address');
            $table->string('delivery_province', 50)->nullable()->after('delivery_city');
            $table->string('delivery_postal_code', 10)->nullable()->after('delivery_province');
            $table->string('delivery_phone', 20)->nullable()->after('delivery_postal_code');
            $table->text('delivery_instructions')->nullable()->after('delivery_phone');

            // Pharmacy dispatch
            $table->string('pharmacy_status')->default('pending')->after('delivery_instructions');
            // pending, dispatched, confirmed, failed
            $table->string('pharmacy_reference')->nullable()->after('pharmacy_status');
            $table->timestamp('dispatched_at')->nullable()->after('pharmacy_reference');
            $table->json('pharmacy_response')->nullable()->after('dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn([
                'total_amount', 'payment_status', 'payment_reference',
                'paid_at', 'delivery_address', 'delivery_city',
                'delivery_province', 'delivery_postal_code',
                'delivery_phone', 'delivery_instructions',
                'pharmacy_status', 'pharmacy_reference',
                'dispatched_at', 'pharmacy_response',
            ]);
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'line_total']);
        });

        Schema::table('medications', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
