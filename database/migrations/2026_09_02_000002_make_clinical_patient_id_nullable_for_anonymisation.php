<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POPIA anonymisation (retention disposal + right-to-erasure) nulls patient_id
 * to de-identify a record while retaining it for legal/clinical retention.
 * The original schema made these columns NOT NULL, which made both the new
 * RetentionService AND the existing PopiaService::processDataDeletion() fail on
 * strict schemas. Make them nullable so anonymisation works as intended.
 *
 * Uses change() — requires doctrine/dbal on some setups; guarded for SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite (used in tests) allows this rebuild transparently.
        Schema::table('consultations', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->change();
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not reverting to NOT NULL: anonymised rows may already hold NULLs,
        // and reverting would fail. Intentionally a no-op.
    }
};
