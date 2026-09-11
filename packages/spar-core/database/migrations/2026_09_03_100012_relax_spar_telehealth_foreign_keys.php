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
 *
 * IMPORTANT (pgsql): a failed `DROP CONSTRAINT` inside a transaction aborts the
 * WHOLE transaction (SQLSTATE 25P02) — a surrounding try/catch cannot rescue it,
 * and the next statement (e.g. the migrations-table insert) then dies. So we must
 * CHECK the constraint exists (via information_schema) BEFORE issuing the drop,
 * rather than attempt-then-catch. This makes the migration a true no-op on a
 * fresh standalone Postgres DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['pgsql', 'mysql'], true)) {
            // SQLite / standalone: columns never had FKs — no-op.
            return;
        }

        $this->dropForeignIfExists('spar_prescription_journeys', 'zapmed_prescription_id');
        $this->dropForeignIfExists('spar_patients', 'user_id');
    }

    public function down(): void
    {
        // Intentionally irreversible: the package definition never re-adds host
        // FKs (host tables may not exist). Host-side re-creation, if ever needed,
        // is the host's own migration responsibility.
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Look up any FK constraint on this table/column, then drop by name.
            // Checking first avoids the 25P02 "transaction aborted" trap.
            $rows = DB::select(
                "SELECT tc.constraint_name
                   FROM information_schema.table_constraints tc
                   JOIN information_schema.key_column_usage kcu
                     ON tc.constraint_name = kcu.constraint_name
                    AND tc.table_schema = kcu.table_schema
                  WHERE tc.constraint_type = 'FOREIGN KEY'
                    AND tc.table_name = ?
                    AND kcu.column_name = ?",
                [$table, $column]
            );

            foreach ($rows as $row) {
                DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT %s', $table, $row->constraint_name));
            }

            return;
        }

        // mysql: conventional FK name; guarded (mysql does not abort the tx the
        // way pgsql does, so try/catch is acceptable here).
        try {
            Schema::table($table, function ($t) use ($column) {
                $t->dropForeign([$column]);
            });
        } catch (\Throwable $e) {
            // Constraint may already be absent; safe to ignore.
        }
    }
};
