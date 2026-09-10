<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7.1 — extend standalone staff accounts for the 4-tier hierarchy
 * (spec FR-15). Adds an optional `group_id` (for group_admin scope) and widens
 * the documented role set. Roles are stored as strings:
 *   super_admin | group_admin | pharmacy_admin | pharmacy_staff
 * (the legacy 'admin' value is treated as super_admin by the identity provider).
 *
 * `group_id` is a plain nullable column — the spar_pharmacy_groups table is
 * created by the spar-core package migrations (possibly a different pass), so
 * no cross-table DB FK is declared, matching the pharmacy_users convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pharmacy_users') && !Schema::hasColumn('pharmacy_users', 'group_id')) {
            Schema::table('pharmacy_users', function (Blueprint $table) {
                $table->unsignedBigInteger('group_id')->nullable()->after('spar_pharmacy_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pharmacy_users') && Schema::hasColumn('pharmacy_users', 'group_id')) {
            Schema::table('pharmacy_users', function (Blueprint $table) {
                $table->dropColumn('group_id');
            });
        }
    }
};
