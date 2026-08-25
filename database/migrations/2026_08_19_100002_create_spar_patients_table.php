<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spar_patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('spar_pharmacy_id')->constrained('spar_pharmacies')->cascadeOnDelete();
            $table->string('profile_code')->comment('SPAR patient profile code');
            $table->string('dependent_code')->nullable();
            $table->string('dependent_relation')->nullable();
            $table->string('medical_aid_name')->nullable();
            $table->string('medical_aid_option')->nullable();
            $table->string('consent_status')->default('pending')->comment('pending, opted_in, opted_out');
            $table->timestamp('consent_given_at')->nullable();
            $table->timestamp('consent_revoked_at')->nullable();
            $table->string('consent_channel')->nullable()->comment('whatsapp, sms, web');
            $table->boolean('is_primary_member')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable()->comment('Flexible field for additional SPAR data');
            $table->timestamps();

            $table->unique(['spar_pharmacy_id', 'profile_code', 'dependent_code'], 'spar_patient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_patients');
    }
};
