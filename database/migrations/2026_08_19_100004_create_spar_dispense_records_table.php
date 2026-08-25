<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spar_dispense_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_id')->constrained('spar_prescription_journeys')->cascadeOnDelete();
            $table->foreignId('spar_patient_id')->constrained('spar_patients');
            $table->integer('dispense_number')->comment('1-6 within the journey');
            $table->string('status')->default('upcoming')->comment('upcoming, reminded, collected, delivered, missed');
            $table->date('due_date');
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('collection_requested_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('fulfillment_type')->nullable()->comment('collection, delivery');
            $table->string('document_number')->nullable()->comment('SPAR transaction document number');
            $table->integer('sales_value')->nullable()->comment('In cents');
            $table->json('items')->nullable()->comment('Medications dispensed this cycle');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_dispense_records');
    }
};
