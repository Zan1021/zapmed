<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 5 — Coaching (parity with Mark's coaching module, coaching/migrations/0001_init.up.sql +
 * 0002_check_constraints). specs/contro-rebuild/08 §2.4.
 *
 * Health-coach interactions: assignments, touchpoints, cross-sell offers. NON-clinical lifecycle +
 * retention work — distinct from consultations (clinical). Patients/coaches are Users; the health_coach
 * role was added to UserRole this task.
 *
 * Fidelity notes: their uuid principals → our users FKs; Postgres enums → string columns validated at
 * the app layer via App\Enums\*. The "one active assignment per patient" rule is a partial-unique index
 * (ended_at IS NULL), exactly as the reference. Crosswalk columns included for import fidelity.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── coaching_assignments: one active coach per patient ─────────────────────────────────────
        Schema::create('coaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason')->nullable();          // why this coach (skills, language, region)
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index('coach_id');
        });

        // One ACTIVE assignment per patient (ended_at IS NULL) — parity with uq_assignment_active.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            Schema::getConnection()->statement(
                'CREATE UNIQUE INDEX uq_assignment_active ON coaching_assignments (patient_id) WHERE ended_at IS NULL'
            );
        }

        // ── coaching_touchpoints: every coach ↔ patient interaction ────────────────────────────────
        Schema::create('coaching_touchpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 30);                    // first_contact/check_in/reminder/cross_sell_offer/support/survey/other
            $table->string('channel', 20);                 // phone/sms/whatsapp/email/in_app/in_person/other
            $table->string('direction', 10);               // outbound|inbound
            $table->boolean('successful')->nullable();     // answered/replied?
            $table->text('summary')->nullable();
            $table->string('sentiment', 20)->nullable();   // positive/neutral/negative
            $table->integer('duration_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index(['patient_id', 'occurred_at']);
            $table->index(['coach_id', 'occurred_at']);
            $table->index(['kind', 'occurred_at']);
        });

        // ── coaching_offers: cross-sell offers ─────────────────────────────────────────────────────
        Schema::create('coaching_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->string('service_line');                // free text matching catalog/crm.service_line
            $table->foreignId('catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();
            $table->string('status', 20)->default('open'); // open/accepted/declined/expired/withdrawn
            $table->text('notes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->string('declined_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index(['patient_id', 'status']);
            $table->index(['coach_id', 'created_at']);
            $table->index(['service_line', 'status']);
        });

        // Sanity constraint (parity with 0002): non-negative touchpoint duration. Enforced app-side too.
        if ($driver === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE coaching_touchpoints ADD CONSTRAINT chk_touchpoint_duration_non_negative CHECK (duration_seconds IS NULL OR duration_seconds >= 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coaching_offers');
        Schema::dropIfExists('coaching_touchpoints');
        Schema::dropIfExists('coaching_assignments');
    }
};
