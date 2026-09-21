<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPAR Close-the-Loop, Wave B3 (spar-close-the-loop design §1.2).
 *
 * Append-only patient response signals — the audit trail, the reminder-schedule
 * input, and the lost-customer / insight feed all in one. A signal is NEVER
 * updated; the LATEST signal per subject drives the reminder cadence.
 *
 * signal values (SparPatientSignalType):
 *   yes_collect | yes_deliver | remind_in_days | remind_next_cycle |
 *   stop_reminders | ignore_month | ignore_future
 *
 * `payload` holds signal-specific data (e.g. {"days": 30}). It carries no PHI
 * (the medication/subject is referenced by id), so it is not encrypted. The
 * subject is a soft polymorphic reference (type + id) — no DB-level FK to keep
 * the table independent of which subject produced the signal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spar_patient_signals')) {
            return;
        }

        Schema::create('spar_patient_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spar_patient_id')->constrained('spar_patients')->cascadeOnDelete();

            // Soft polymorphic subject (journey / dispense / order). Nullable so
            // a global preference signal (not tied to one subject) is possible.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('signal');               // SparPatientSignalType value
            $table->json('payload')->nullable();     // e.g. {"days": 30}
            $table->string('channel')->default('inapp'); // where the response came from

            $table->timestamps();

            $table->index(['spar_patient_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('signal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_patient_signals');
    }
};
