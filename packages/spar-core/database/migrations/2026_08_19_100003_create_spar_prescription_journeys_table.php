<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spar-core package migration. Idempotent (Schema::hasTable guard).
 *
 * NOTE (portability): `zapmed_prescription_id` is a PLAIN nullable column —
 * NOT a foreign key to `prescriptions`. Standalone has no prescriptions table;
 * the renewal loop-closer is application-level and integration-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spar_prescription_journeys')) {
            return;
        }

        Schema::create('spar_prescription_journeys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spar_patient_id')->constrained('spar_patients')->cascadeOnDelete();
            $table->foreignId('spar_pharmacy_id')->constrained('spar_pharmacies');
            $table->string('script_number')->nullable()->comment('SPAR prescription/script number');
            $table->string('status')->default('active')->comment('active, renewal_due, renewed, expired, cancelled');
            $table->integer('total_dispenses')->default(6)->comment('Total dispenses in this cycle');
            $table->integer('dispenses_completed')->default(0);
            $table->date('start_date');
            $table->date('next_dispense_date')->nullable();
            $table->date('renewal_due_date')->nullable();
            $table->string('renewal_route')->nullable()->comment('primary_doctor, zapmed, null');
            // Integration-only link (no DB FK — standalone has no prescriptions table).
            $table->unsignedBigInteger('zapmed_prescription_id')->nullable();
            $table->string('doctor_name')->nullable();
            $table->string('doctor_bhf')->nullable();
            $table->json('medications')->nullable()->comment('Array of medications in this journey');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_prescription_journeys');
    }
};
