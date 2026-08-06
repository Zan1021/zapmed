<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 15)->unique(); // MC-XXXXXXXXX
            $table->foreignId('consultation_id')->constrained();
            $table->foreignId('patient_id')->constrained('users');
            $table->foreignId('doctor_id')->constrained('users');
            $table->string('type', 30); // sick_note, fitness_certificate, medical_certificate, referral_letter
            $table->date('date_from');
            $table->date('date_to');
            $table->integer('days_off')->default(1);
            $table->text('diagnosis')->nullable(); // only if patient consents to disclosure
            $table->boolean('diagnosis_disclosed')->default(false);
            $table->text('recommendations')->nullable();
            $table->text('notes')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_hash')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'type']);
        });

        // Referrals (separate from certificates)
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained();
            $table->foreignId('patient_id')->constrained('users');
            $table->foreignId('referring_doctor_id')->constrained('users');
            $table->string('referred_to_name'); // specialist name
            $table->string('referred_to_speciality')->nullable();
            $table->string('referred_to_practice')->nullable();
            $table->string('referred_to_phone', 20)->nullable();
            $table->string('urgency', 20)->default('routine'); // routine, urgent, emergency
            $table->text('reason');
            $table->text('clinical_information')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('medical_certificates');
    }
};
