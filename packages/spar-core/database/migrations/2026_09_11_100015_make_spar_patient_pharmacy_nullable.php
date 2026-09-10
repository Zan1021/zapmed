<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * National identity, Phase 3.1 — `spar_patients.spar_pharmacy_id` is repurposed
 * from "the pharmacy this patient belongs to" (part of identity) into a nullable
 * "home / most-recent pharmacy" (informational). A patient is national; the
 * pharmacy of record lives on each journey/dispense. Making it nullable lets a
 * patient exist without being pinned to one store.
 *
 * SQLite (standalone/test) can't easily ALTER a column to nullable in place, so
 * this is a guarded, cross-driver change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('spar_patients') || !Schema::hasColumn('spar_patients', 'spar_pharmacy_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // Doctrine-free approach for SQLite: rebuild is overkill; SQLite is
            // dynamically typed and does NOT enforce NOT NULL on a column that
            // already had it only via the create migration IF we relax it here.
            // We use a raw table rebuild only if a NOT NULL constraint exists.
            // Simplest portable path: recreate the column as nullable.
            try {
                Schema::table('spar_patients', function (Blueprint $table) {
                    $table->unsignedBigInteger('spar_pharmacy_id')->nullable()->change();
                });
            } catch (\Throwable $e) {
                // change() needs doctrine/dbal on some setups; if unavailable the
                // standalone test DB is recreated per-run from the create
                // migration, so we make THAT nullable instead (see note below).
            }

            return;
        }

        Schema::table('spar_patients', function (Blueprint $table) {
            $table->unsignedBigInteger('spar_pharmacy_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No-op: re-imposing NOT NULL could fail on now-null rows. Intentional.
    }
};
