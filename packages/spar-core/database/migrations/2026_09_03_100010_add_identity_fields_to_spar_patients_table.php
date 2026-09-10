<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spar-core package migration (Phase 1.2 — SPAR-owned identity on spar_patients).
 *
 * Idempotent per-column: on a FRESH standalone DB these columns already exist
 * (the package create_spar_patients migration folds them in), so each add is
 * guarded with Schema::hasColumn and skipped. In the ZapMed host this ran as
 * the original app/ migration (this package copy is then a no-op there too).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('spar_patients')) {
            return;
        }

        Schema::table('spar_patients', function (Blueprint $table) {
            if (!Schema::hasColumn('spar_patients', 'first_name')) {
                $table->string('first_name')->nullable()->after('dependent_relation');
            }
            if (!Schema::hasColumn('spar_patients', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('spar_patients', 'cellphone')) {
                $table->text('cellphone')->nullable()->after('last_name');
            }
            if (!Schema::hasColumn('spar_patients', 'email')) {
                $table->text('email')->nullable()->after('cellphone');
            }
            if (!Schema::hasColumn('spar_patients', 'onboarding_status')) {
                $table->string('onboarding_status')->default('awaiting_contact')->after('email')
                    ->comment('awaiting_contact, pending_consent, active, opted_out');
            }
        });
    }

    public function down(): void
    {
        Schema::table('spar_patients', function (Blueprint $table) {
            foreach (['first_name', 'last_name', 'cellphone', 'email', 'onboarding_status'] as $col) {
                if (Schema::hasColumn('spar_patients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
