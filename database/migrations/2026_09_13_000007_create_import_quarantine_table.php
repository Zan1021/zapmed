<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Import quarantine (blueprint §3 rule 5): anomalies are FLAGGED, never silently dropped.
 *
 * The reconciler writes a quarantine row whenever a staged record cannot be safely reconciled —
 * unmatched reference, disallowed status transition, unparseable/invalid data, etc. Nothing is
 * discarded; each row keeps the raw payload + a reason for manual review with Craig/Dave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_quarantine', function (Blueprint $table) {
            $table->id();
            $table->string('entity_set', 64);          // orders, patients, ...
            $table->string('upstream_id')->nullable();  // Contro id / userHash (if known)
            $table->string('reason', 64);               // machine reason code
            $table->text('detail')->nullable();         // human explanation
            $table->json('payload')->nullable();        // the raw staged row for review
            $table->string('status', 20)->default('open'); // open|resolved|ignored
            $table->foreignId('sync_run_id')->nullable()->constrained('upstream_sync_runs')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['entity_set', 'status']);
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_quarantine');
    }
};
