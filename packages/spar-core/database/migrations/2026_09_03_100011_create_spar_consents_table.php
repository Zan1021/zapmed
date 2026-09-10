<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spar-core package migration (Phase 1.7 — POPIA consent evidence trail).
 * SPAR-owned (not ZapMed User-coupled). Idempotent (Schema::hasTable guard).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spar_consents')) {
            return;
        }

        Schema::create('spar_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spar_patient_id')->constrained('spar_patients')->cascadeOnDelete();
            $table->string('consent_type')->default('chronic_medication')
                ->comment('chronic_medication, marketing, data_processing');
            $table->string('version')->comment('exact wording/version shown to the patient');
            $table->boolean('granted')->default(false);
            $table->string('channel')->nullable()->comment('web, whatsapp, sms, in_store');
            $table->string('source')->nullable()->comment('patient, pharmacist:<id>, import');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['spar_patient_id', 'consent_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_consents');
    }
};
