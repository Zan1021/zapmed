<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * spar-core package migration (Phase 7.1, spec FR-14). Introduces the pharmacy
 * GROUP tier above pharmacies and links every pharmacy to a group.
 *
 * Idempotent: guarded with Schema::hasTable / hasColumn so it is safe on a
 * fresh standalone DB, safe to re-run, and a no-op where already applied.
 *
 * Every pharmacy must belong to exactly one group (FR-14.2). Existing
 * pharmacies are migrated into a single "Default Group" so no row is orphaned.
 * `group_id` is a PLAIN nullable column (no cross-table DB FK) to match the
 * package's portability convention — integrity is enforced at the app layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('spar_pharmacy_groups')) {
            Schema::create('spar_pharmacy_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('region')->nullable();
                $table->string('logo_path')->nullable();
                $table->string('contact_name')->nullable();
                $table->string('contact_email')->nullable();
                $table->string('contact_phone')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('spar_pharmacies') && !Schema::hasColumn('spar_pharmacies', 'group_id')) {
            Schema::table('spar_pharmacies', function (Blueprint $table) {
                // Plain nullable column (no DB FK) — app-level integrity, portable.
                $table->unsignedBigInteger('group_id')->nullable()->after('id')->index();
            });
        }

        // Backfill: ensure a default group exists and every pharmacy belongs to it.
        $this->backfillDefaultGroup();
    }

    private function backfillDefaultGroup(): void
    {
        if (!Schema::hasTable('spar_pharmacy_groups') || !Schema::hasTable('spar_pharmacies')) {
            return;
        }

        // Only backfill if there are ungrouped pharmacies.
        $ungrouped = DB::table('spar_pharmacies')->whereNull('group_id')->count();
        if ($ungrouped === 0) {
            return;
        }

        $groupId = DB::table('spar_pharmacy_groups')->where('slug', 'default')->value('id');
        if (!$groupId) {
            $groupId = DB::table('spar_pharmacy_groups')->insertGetId([
                'name' => 'Default Group',
                'slug' => 'default',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('spar_pharmacies')->whereNull('group_id')->update(['group_id' => $groupId]);
    }

    public function down(): void
    {
        if (Schema::hasTable('spar_pharmacies') && Schema::hasColumn('spar_pharmacies', 'group_id')) {
            Schema::table('spar_pharmacies', function (Blueprint $table) {
                $table->dropColumn('group_id');
            });
        }

        Schema::dropIfExists('spar_pharmacy_groups');
    }
};
