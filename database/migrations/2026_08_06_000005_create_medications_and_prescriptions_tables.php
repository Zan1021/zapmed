<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Medication database (reference table)
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Amlodipine"
            $table->string('generic_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('form', 50); // tablet, capsule, syrup, injection, cream, drops
            $table->string('strength', 50); // e.g. "5mg", "500mg/5ml"
            $table->string('schedule', 10)->nullable(); // S1-S8 (SA scheduling)
            $table->string('nappi_code', 20)->nullable(); // NAPPI code for SA
            $table->text('contraindications')->nullable();
            $table->text('side_effects')->nullable();
            $table->text('interactions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('nappi_code');
            $table->index('schedule');
        });

        // Prescriptions
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 15)->unique(); // RX-XXXXXXXXX
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained('users');
            $table->foreignId('doctor_id')->constrained('users');
            $table->string('status', 20)->default('draft'); // draft, signed, dispensed, expired, cancelled
            $table->text('diagnosis')->nullable();
            $table->text('notes')->nullable(); // pharmacist instructions
            $table->boolean('is_chronic')->default(false);
            $table->integer('repeats')->default(0); // number of repeats allowed
            $table->integer('repeats_used')->default(0);
            $table->date('valid_until')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_hash')->nullable(); // digital signature verification
            $table->string('pdf_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
            $table->index(['doctor_id', 'created_at']);
        });

        // Prescription items (line items)
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medication_id')->nullable()->constrained()->nullOnDelete();
            $table->string('medication_name'); // denormalized for history
            $table->string('strength', 50);
            $table->string('form', 50);
            $table->string('dosage'); // e.g. "1 tablet"
            $table->string('frequency'); // e.g. "twice daily"
            $table->string('route', 30)->default('oral'); // oral, topical, IV, IM, etc
            $table->integer('duration_days')->nullable();
            $table->integer('quantity');
            $table->text('instructions')->nullable(); // e.g. "Take with food"
            $table->boolean('substitution_allowed')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('medications');
    }
};
