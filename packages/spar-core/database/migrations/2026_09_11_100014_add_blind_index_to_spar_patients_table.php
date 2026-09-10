<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spar-core package migration — National Patient Identity, Phase 1.1.
 *
 * Adds a BLIND INDEX (keyed HMAC) of the encrypted identity fields so a patient
 * can be matched across pharmacies WITHOUT decrypting (profile_code / cellphone
 * stay ciphertext at rest via EncryptsSensitiveFields). Plus review flags used
 * when a profile match arrives with a materially different phone.
 *
 * Idempotent per-column (Schema::hasColumn) so it is safe on both hosts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('spar_patients')) {
            return;
        }

        Schema::table('spar_patients', function (Blueprint $table) {
            // Deterministic keyed hashes — matchable in SQL, non-reversible, no PHI.
            if (!Schema::hasColumn('spar_patients', 'profile_code_hash')) {
                $table->string('profile_code_hash', 64)->nullable()->index()->after('profile_code');
            }
            if (!Schema::hasColumn('spar_patients', 'cellphone_hash')) {
                $table->string('cellphone_hash', 64)->nullable()->index()->after('cellphone');
            }
            // Identity-review flag: set when a profile match has a conflicting phone
            // (national de-dup FR-3) — attach the dispense but flag for a human.
            if (!Schema::hasColumn('spar_patients', 'needs_identity_review')) {
                $table->boolean('needs_identity_review')->default(false)->after('onboarding_status');
            }
            if (!Schema::hasColumn('spar_patients', 'identity_review_reason')) {
                $table->string('identity_review_reason')->nullable()->after('needs_identity_review');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spar_patients', function (Blueprint $table) {
            foreach (['profile_code_hash', 'cellphone_hash', 'needs_identity_review', 'identity_review_reason'] as $col) {
                if (Schema::hasColumn('spar_patients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
