<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * assessments.answers is written through the `encrypted:array` cast, which stores an opaque
 * ciphertext string - NOT valid JSON. PostgreSQL's json type rejects it (SQLSTATE 22P02), which
 * made every question-bank booking 500 when the assessment row was created. SQLite is
 * type-agnostic, so the test suite never caught it. Store the column as text.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE assessments ALTER COLUMN answers TYPE text USING answers::text');
            DB::statement('ALTER TABLE assessments ALTER COLUMN answers DROP NOT NULL');
        }
        // SQLite/MySQL: column type is not enforced the same way; nothing to do for the test DB.
    }

    public function down(): void
    {
        // Intentionally irreversible: ciphertext cannot be cast back to json.
    }
};
