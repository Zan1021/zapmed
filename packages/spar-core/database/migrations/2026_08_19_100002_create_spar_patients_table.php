<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spar-core package migration. Idempotent (Schema::hasTable guard).
 *
 * NOTE (portability): `user_id` is a PLAIN nullable column here — NOT a foreign
 * key to `users`. The standalone SPAR app has no `users` table; the only real
 * telehealth coupling is application-level and integration-only. The ZapMed
 * host keeps its own FK via its original app/ migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spar_patients')) {
            return;
        }

        Schema::create('spar_patients', function (Blueprint $table) {
            $table->id();
            // Integration-only link (no DB FK — standalone has no users table).
            $table->unsignedBigInteger('user_id')->nullable();
            // National identity (Phase 3): pharmacy is NOT part of patient
            // identity — it's a nullable "home/most-recent" pointer. Patients are
            // matched nationally via the profile-code blind index; the pharmacy
            // of record lives on each journey/dispense.
            $table->foreignId('spar_pharmacy_id')->nullable()->constrained('spar_pharmacies')->nullOnDelete();
            $table->string('profile_code')->comment('SPAR patient profile code');
            $table->string('dependent_code')->nullable();
            $table->string('dependent_relation')->nullable();

            // SPAR-owned identity (Phase 1.2 — folded into the create for a fresh
            // standalone DB). Encrypted PII columns are TEXT.
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->text('cellphone')->nullable();
            $table->text('email')->nullable();
            $table->string('onboarding_status')->default('awaiting_contact')
                ->comment('awaiting_contact, pending_consent, active, opted_out');

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

            // NOTE: no unique on (pharmacy, profile_code, dependent_code) — that
            // was the old per-store identity and conflicts with national de-dup
            // (a patient may appear at multiple stores). Identity is enforced in
            // the import via the profile_code blind index instead.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_patients');
    }
};
