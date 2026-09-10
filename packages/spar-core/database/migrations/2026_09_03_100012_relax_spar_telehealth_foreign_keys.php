<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * spar-core package migration (Phase 2.4).
 *
 * In the package the SPAR tables are created WITHOUT DB-level FKs to host
 * tables (`user_id`, `zapmed_prescription_id`, `imported_by` are plain nullable
 * columns), so for a fresh standalone DB there is nothing to relax — this is a
 * safe no-op. In the ZapMed host it drops the legacy FK constraints on
 * pgsql/mysql where they were created by the host's original migrations.
 * Guarded + try/catch so it never fails if a constraint is already absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['pgsql', 'mysql'], true)) {
            if (Schema::hasTable('spar_prescription_journeys')) {
                $this->dropForeignIfExists('spar_prescription_journeys', ['zapmed_prescription_id']);
            }
            if (Schema::hasTable('spar_patients')) {
                $this->dropForeignIfExists('spar_patients', ['user_id']);
            }
        }

        // SQLite / standalone: no-op (columns already have no FK).
    }

    public function down(): void
    {
        // Intentionally irreversible: the package definition never re-adds host
        // FKs (host tables may not exist). Host-side re-creation, if ever needed,
        // is the host's own migration responsibility.
    }

    private function dropForeignIfExists(string $table, array $columns): void
    {
        try {
            Schema::table($table, function ($t) use ($columns) {
                $t->dropForeign($columns);
            });
        } catch (\Throwable $e) {
            // Constraint may already be absent; safe to ignore.
        }
    }
};
