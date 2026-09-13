<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 3 — CRM core (leads, funnel, flags, notes, risk score).
 *
 * Re-implements Mark's `crm` module (crm/migrations/0001_init.up.sql) in our Laravel app, per
 * specs/contro-rebuild/08-crm-build-spec §2.1 and the parity checklist (07). This is staff-internal
 * lifecycle + intelligence, DISTINCT from patient_profiles (the patient's own clinical/contact data).
 *
 * Fidelity notes vs the reference:
 *  - Their `principal_id uuid` (patient) → our `patient_id` FK to users. One lead per patient (unique).
 *  - Postgres ENUMs → string columns (driver-agnostic: local SQLite, staging/prod Postgres). The allowed
 *    values live in App\Enums\* and are enforced at the app layer + a coverage test — same discipline as
 *    the order state machine (config-as-data).
 *  - Their optimistic `version` + touch trigger → Laravel timestamps + explicit versioning on the model.
 *  - Contro crosswalk columns (upstream_id/source/synced_at + unique) are BORN with every table — the
 *    ControCrosswalkTest coverage guard requires it the moment the tables exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── crm_leads: one lifecycle record per patient principal ────────────────────────────────
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();

            // Attribution (free text — parity with reference; may become enums later).
            $table->string('source')->nullable();
            $table->string('channel')->nullable();
            $table->string('campaign')->nullable();
            $table->string('referrer')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('service_line')->nullable();

            // Funnel position (14-stage enum, verbatim from crm_funnel_stage_enum).
            $table->string('current_stage', 40)->default('lead');
            $table->timestamp('stage_entered_at')->useCurrent();
            $table->timestamp('last_activity_at')->useCurrent();

            // Assigned staff (ops/coach). Nullable.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->json('metadata')->nullable();
            $table->unsignedInteger('version')->default(0); // optimistic-concurrency parity

            // Contro crosswalk.
            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique('patient_id');
            $table->unique(['upstream_source', 'upstream_id']);
            $table->index('current_stage');
            $table->index('assigned_to');
            $table->index('last_activity_at');
            $table->index('service_line');
        });

        // ── crm_funnel_events: immutable stage-transition history ─────────────────────────────────
        Schema::create('crm_funnel_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->string('from_stage', 40)->nullable();      // null = initial capture
            $table->string('to_stage', 40);
            $table->string('aggregate_type')->nullable();       // order/consult/etc.
            $table->unsignedBigInteger('aggregate_id')->nullable();
            $table->text('notes')->nullable();
            $table->string('actor')->nullable();                // principal / actor ref
            $table->timestamp('occurred_at')->useCurrent();

            // Contro crosswalk.
            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            // Append-only — no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index(['crm_lead_id', 'occurred_at']);
            $table->index(['to_stage', 'occurred_at']);
        });

        // ── crm_notes: pinned staff notes ──────────────────────────────────────────────────────────
        Schema::create('crm_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Contro crosswalk.
            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index(['crm_lead_id', 'pinned']);
        });

        // ── crm_flags: lightweight tags with optional reason ───────────────────────────────────────
        Schema::create('crm_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->string('kind', 40);                         // at_risk/vip/... (crm_flag_kind_enum)
            $table->text('reason')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Contro crosswalk.
            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
            // Parity with reference: at most one ACTIVE flag of a kind per lead.
            $table->unique(['crm_lead_id', 'kind', 'cleared_at']);
            $table->index('kind');
        });

        // ── crm_risk_scores: computed patient risk (0–100 + band + factors) ─────────────────────────
        Schema::create('crm_risk_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);   // 0–100
            $table->string('band', 20)->default('low');         // low/medium/high/critical
            $table->json('factors')->nullable();                // [{label,impact,weight}]
            $table->text('reasoning')->nullable();
            $table->string('computed_by', 40)->default('rules'); // rules | ai
            $table->timestamp('computed_at')->useCurrent();

            $table->timestamps();

            // One current score per lead (recompute overwrites).
            $table->unique('crm_lead_id');
            $table->index(['band', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_risk_scores');
        Schema::dropIfExists('crm_flags');
        Schema::dropIfExists('crm_notes');
        Schema::dropIfExists('crm_funnel_events');
        Schema::dropIfExists('crm_leads');
    }
};
