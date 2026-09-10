<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** spar-core package migration. Idempotent (Schema::hasTable guard). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spar_orders')) {
            return;
        }

        Schema::create('spar_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('spar_patient_id')->constrained('spar_patients');
            $table->foreignId('spar_pharmacy_id')->constrained('spar_pharmacies');
            $table->foreignId('dispense_record_id')->nullable()->constrained('spar_dispense_records')->nullOnDelete();
            $table->string('type')->comment('collection, delivery');
            $table->string('status')->default('requested')->comment('requested, preparing, ready, completed, cancelled');
            $table->string('delivery_address')->nullable();
            $table->string('delivery_city')->nullable();
            $table->string('delivery_postal_code')->nullable();
            $table->string('delivery_phone')->nullable();
            $table->date('delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_orders');
    }
};
