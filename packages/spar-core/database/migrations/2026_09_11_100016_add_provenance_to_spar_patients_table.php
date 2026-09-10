<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff patient detail, Phase 1 — onboarding provenance on spar_patients:
 *   - onboarding_pharmacy_id : the store the patient was FIRST onboarded at
 *     (distinct from spar_pharmacy_id which is now "home/most-recent").
 *   - captured_by_id / captured_at : who onboarded them + when (pharmacist).
 *
 * captured_by_id is a PLAIN nullable id (no FK / no host class — the standalone
 * PharmacyUser and integrated User differ; resolved to a name host-side).
 * Idempotent per-column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('spar_patients')) {
            return;
        }

        Schema::table('spar_patients', function (Blueprint $table) {
            if (!Schema::hasColumn('spar_patients', 'onboarding_pharmacy_id')) {
                $table->unsignedBigInteger('onboarding_pharmacy_id')->nullable()->after('spar_pharmacy_id');
            }
            if (!Schema::hasColumn('spar_patients', 'captured_by_id')) {
                $table->unsignedBigInteger('captured_by_id')->nullable()->after('onboarding_pharmacy_id');
            }
            if (!Schema::hasColumn('spar_patients', 'captured_at')) {
                $table->timestamp('captured_at')->nullable()->after('captured_by_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spar_patients', function (Blueprint $table) {
            foreach (['onboarding_pharmacy_id', 'captured_by_id', 'captured_at'] as $col) {
                if (Schema::hasColumn('spar_patients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
