<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('medical_aid_name')->nullable();
            $table->string('medical_aid_number')->nullable();
            $table->string('medical_aid_plan')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relationship', 50)->nullable();
            $table->string('blood_type', 5)->nullable(); // A+, O-, etc
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->boolean('is_smoker')->default(false);
            $table->boolean('consumes_alcohol')->default(false);
            $table->text('surgical_history')->nullable();
            $table->text('family_history')->nullable();
            $table->boolean('onboarding_complete')->default(false);
            $table->boolean('consent_given')->default(false);
            $table->timestamp('consent_given_at')->nullable();
            $table->timestamps();
        });

        // Allergies (many per patient)
        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_profile_id')->constrained()->cascadeOnDelete();
            $table->string('allergen'); // e.g. "Penicillin"
            $table->string('severity', 20)->default('moderate'); // mild, moderate, severe
            $table->text('reaction')->nullable();
            $table->timestamps();
        });

        // Chronic conditions
        Schema::create('patient_chronic_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_profile_id')->constrained()->cascadeOnDelete();
            $table->string('condition_name'); // e.g. "Hypertension"
            $table->date('diagnosed_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_chronic_conditions');
        Schema::dropIfExists('patient_allergies');
        Schema::dropIfExists('patient_profiles');
    }
};
