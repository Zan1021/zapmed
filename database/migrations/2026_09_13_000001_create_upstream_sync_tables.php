<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contro ingestion staging layer (ELT).
 *
 * Mirrors Mark's `upstream_sync` module (see specs/contro-rebuild/03-contro-import-blueprint.md).
 * Contro is the source of truth; we pull its data into these RAW staging tables first, then a
 * separate, replayable reconcile step maps staging -> canonical. Never API -> canonical directly.
 *
 * Design notes:
 *  - Kept driver-agnostic: local dev is SQLite, staging/prod is Postgres. We use json() (not the
 *    pg-only jsonb()) and avoid citext / raw pg types so a single migration runs on both.
 *  - `ingested_row` is keyed on (entity_set, upstream_id) with a unique index so re-pulls are an
 *    idempotent, newer-wins upsert (guard on upstream_updated_at at write time).
 *  - Contro int64 ids arrive as STRINGS (IEEE754Compatible header); patients are keyed by userHash.
 *    So upstream_id is a string, never an integer.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Per-pull audit. Created first because ingested_rows references it.
        Schema::create('upstream_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_set', 64)->nullable(); // null = multi-entity run
            $table->string('status', 32)->default('running'); // running|completed|failed
            $table->string('trigger', 32)->default('manual');  // manual|scheduled|backfill
            $table->unsignedInteger('rows_pulled')->default(0);
            $table->unsignedInteger('rows_upserted')->default(0);
            $table->unsignedInteger('rows_quarantined')->default(0);
            $table->string('watermark_from')->nullable();
            $table->string('watermark_to')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['entity_set', 'status']);
        });

        // Raw decrypted Contro rows, one per (entity_set, upstream_id). Newer-wins on watermark.
        Schema::create('upstream_ingested_rows', function (Blueprint $table) {
            $table->id();
            $table->string('entity_set', 64);          // orders, patients, payments, ...
            $table->string('upstream_id');              // Contro id (string) or patient userHash
            $table->json('payload');                    // raw Contro DTO as received
            $table->timestamp('upstream_updated_at')->nullable(); // parsed watermark value
            $table->foreignId('sync_run_id')->nullable()->constrained('upstream_sync_runs')->nullOnDelete();
            $table->timestamp('pulled_at')->useCurrent();
            $table->timestamps();

            $table->unique(['entity_set', 'upstream_id']); // idempotency key
            $table->index(['entity_set', 'upstream_updated_at']);
        });

        // Incremental pull watermark, one row per entity_set.
        Schema::create('upstream_sync_states', function (Blueprint $table) {
            $table->id();
            $table->string('entity_set', 64)->unique();
            $table->string('last_high_watermark')->nullable(); // opaque string ($filter gt :hwm)
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Drop child first (FK on sync_run_id).
        Schema::dropIfExists('upstream_ingested_rows');
        Schema::dropIfExists('upstream_sync_states');
        Schema::dropIfExists('upstream_sync_runs');
    }
};
