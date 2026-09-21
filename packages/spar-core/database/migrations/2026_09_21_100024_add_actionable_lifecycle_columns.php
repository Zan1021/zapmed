<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPAR Close-the-Loop, Wave B1 (spar-close-the-loop design §1.1, option B).
 *
 * Adds the shared "actionable item" lifecycle columns to every subject the
 * engine drives — prescription journeys, dispense records and orders — rather
 * than introducing a separate polymorphic table. Behaviour is shared via the
 * ResolvesActionable trait; state is typed by the SparActionableStatus enum.
 *
 *   action_status  — open|actioned|awaiting_patient|snoozed|resolved
 *   snoozed_until  — when a snoozed item wakes back into due lists
 *   last_action_at — last staff action (nudge/snooze/message)
 *   resolved_at    — when the loop closed (also set by the import reconciler)
 *
 * Package-pure: no host tables referenced; SQLite-safe (no post-create index
 * that SQLite can't build). Idempotent guards allow re-run on partial state.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $tables = [
        'spar_prescription_journeys',
        'spar_dispense_records',
        'spar_orders',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'action_status')) {
                    $table->string('action_status')->default('open')->index();
                }
                if (! Schema::hasColumn($tableName, 'snoozed_until')) {
                    $table->timestamp('snoozed_until')->nullable()->index();
                }
                if (! Schema::hasColumn($tableName, 'last_action_at')) {
                    $table->timestamp('last_action_at')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'resolved_at')) {
                    $table->timestamp('resolved_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['action_status', 'snoozed_until', 'last_action_at', 'resolved_at'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
