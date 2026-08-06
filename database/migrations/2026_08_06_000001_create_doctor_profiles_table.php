<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('hpcsa_number', 20)->unique(); // Health Professions Council SA
            $table->string('speciality', 100)->default('General Practitioner');
            $table->string('qualification', 255); // e.g. MBChB, MMed
            $table->string('university', 255)->nullable();
            $table->year('year_qualified')->nullable();
            $table->text('bio')->nullable();
            $table->integer('consultation_fee')->default(45000); // in cents (R450.00)
            $table->integer('followup_fee')->default(30000); // R300.00
            $table->integer('consultation_duration')->default(30); // minutes
            $table->json('available_days')->nullable(); // ["mon","tue","wed","thu","fri"]
            $table->time('available_from')->default('08:00');
            $table->time('available_to')->default('17:00');
            $table->string('signature_path')->nullable(); // digital signature for scripts
            $table->boolean('accepts_new_patients')->default(true);
            $table->boolean('is_verified')->default(false); // admin must verify
            $table->timestamps();

            $table->index('speciality');
            $table->index('is_verified');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_profiles');
    }
};
