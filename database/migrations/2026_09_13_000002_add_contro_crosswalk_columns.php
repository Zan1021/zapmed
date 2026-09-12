<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 1 — Contro crosswalk columns.
 *
 * Adds the idempotency backbone for the Contro import to EVERY existing table that will receive
 * Contro data (specs/contro-rebuild/03-contro-import-blueprint.md §2/§3). Each recipient row can be
 * linked back to its Contro origin and re-imported safely (upsert on (upstream_source, upstream_id)).
 *
 * Columns added to each recipient table:
 *   - upstream_id         string, nullable  — Contro id (int64-as-STRING) or, for patients/users, the
 *                                             opaque userHash. NEVER an integer; stored verbatim.
 *   - upstream_source     string, 'contro'  — provenance; future-proofs for other upstreams.
 *   - upstream_synced_at  timestamp, null   — when this row was last reconciled from upstream.
 * Plus a composite UNIQUE(upstream_source, upstream_id) per table.
 *
 * NULL handling: our own native (non-imported) rows have upstream_id = NULL. Both SQLite and Postgres
 * treat NULLs as DISTINCT in a unique index, so any number of native rows coexist; the uniqueness only
 * bites for actual Contro-linked rows. (This is why we do NOT use a Postgres partial index — the
 * standard multi-column unique is portable and behaves correctly on both drivers.)
 *
 * RECIPIENT TABLES THAT EXIST NOW (covered by this migration):
 *   users, patient_profiles, payments, prescriptions, prescription_items, medications, subscriptions
 *
 * RECIPIENT TABLES THAT DO NOT EXIST YET — they are BORN WITH these columns in their own build tasks,
 * so they are intentionally NOT touched here (documented so nothing is silently missed):
 *   - orders, order_items, order_status_history  -> Task 2 (Order aggregate)
 *   - catalog_coupons, catalog_coupon_usage      -> Task 3 (Catalog)
 * See the coverage assertion in tests/Feature/ControCrosswalkTest.php which fails if a NEW recipient
 * table is added later without a crosswalk.
 *
 * WHY users AND patient_profiles both get it:
 *   Contro keys patients AND doctors by userHash. Doctors are `users` (no patient_profile). Orders carry
 *   assignedDoctorUserHash. So the principal-level crosswalk lives on `users` (matches every principal),
 *   and patient_profiles ALSO carries it because that is the natural patient record we reconcile Contro
 *   PatientDto into. Both are needed; neither is redundant.
 */
return new class extends Migration
{
    /**
     * Existing recipient tables. Each gets the same three columns + composite unique index.
     * Keyed by table => column to place the crosswalk block after (for readable schema; cosmetic only).
     */
    private array $tables = [
        'users' => 'id',
        'patient_profiles' => 'id',
        'payments' => 'id',
        'prescriptions' => 'id',
        'prescription_items' => 'id',
        'medications' => 'id',
        'subscriptions' => 'id',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName => $after) {
            Schema::table($tableName, function (Blueprint $table) use ($after) {
                $table->string('upstream_id')->nullable()->after($after);
                $table->string('upstream_source', 32)->default('contro')->after('upstream_id');
                $table->timestamp('upstream_synced_at')->nullable()->after('upstream_source');

                $table->unique(['upstream_source', 'upstream_id'], "{$this->indexName($table)}");
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropUnique("{$tableName}_upstream_source_upstream_id_unique");
                $table->dropColumn(['upstream_id', 'upstream_source', 'upstream_synced_at']);
            });
        }
    }

    /**
     * Deterministic index name matching Laravel's default convention so down() can drop it reliably.
     */
    private function indexName(Blueprint $table): string
    {
        return "{$table->getTable()}_upstream_source_upstream_id_unique";
    }
};
