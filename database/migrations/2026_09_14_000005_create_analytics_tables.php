<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 7 (analytics half) — parity with Mark's analytics module
 * (analytics/migrations/0001_init.up.sql). specs/contro-rebuild/08 §2.6.
 *
 * Captures the raw analytical signal; the read side (AnalyticsService) computes aggregates on
 * demand or from the nightly KPI snapshot. Four tables:
 *   analytics_funnel_events  — append-only event log (anonymous allowed pre-signup via visitor_id)
 *   analytics_attribution    — one row per patient: first/last-touch UTM
 *   analytics_ad_spend       — channel/campaign spend for CAC
 *   analytics_kpi_snapshots  — nightly, sliceable, flexible metrics bag
 *
 * Driver-agnostic: json() not jsonb(); no DB-specific generated columns. Local SQLite / prod Postgres.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── raw funnel events (append-only) ─────────────────────────────────────────────────────
        Schema::create('analytics_funnel_events', function (Blueprint $table) {
            $table->id();
            // Optional principal — anonymous events allowed before sign-up.
            $table->foreignId('principal_id')->nullable()->constrained('users')->nullOnDelete();
            // Anonymous visitor id (set by frontend before sign-up).
            $table->string('visitor_id')->nullable();
            $table->string('kind', 32);
            // Custom kinds use this label.
            $table->string('custom_label')->nullable();
            // Service line at moment of event (weight_loss, ed, contraception, sti, etc.).
            $table->string('service_line')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index(['principal_id', 'occurred_at']);
            $table->index(['visitor_id', 'occurred_at']);
            $table->index(['kind', 'occurred_at']);
        });

        // ── attribution (one row per principal — first/last touch) ───────────────────────────────
        Schema::create('analytics_attribution', function (Blueprint $table) {
            $table->foreignId('principal_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('visitor_id')->nullable();
            $table->string('first_source')->nullable();
            $table->string('first_medium')->nullable();
            $table->string('first_campaign')->nullable();
            $table->string('first_referrer')->nullable();
            $table->string('last_source')->nullable();
            $table->string('last_medium')->nullable();
            $table->string('last_campaign')->nullable();
            $table->string('landing_page')->nullable();
            $table->timestamp('captured_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index('first_source');
            $table->index('last_source');
        });

        // ── ad spend / CAC (manually entered by ops, or pulled by a future job) ───────────────────
        Schema::create('analytics_ad_spend', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('channel'); // google_ads, meta, tiktok, organic, referral, other
            $table->string('campaign')->nullable();
            $table->integer('amount_cents');
            $table->string('currency', 3)->default('ZAR');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['period_start', 'period_end']);
            $table->index(['channel', 'period_start']);
        });

        // ── daily KPI snapshots (computed nightly, queried fast) ──────────────────────────────────
        Schema::create('analytics_kpi_snapshots', function (Blueprint $table) {
            $table->date('snapshot_date');
            // Slice the metric: 'all', a service_line, a channel, etc.
            $table->string('dimension')->default('all');
            $table->string('dimension_value')->default('all');
            // A flexible bag of named metrics — easier than a column-per-KPI.
            $table->json('metrics')->nullable();
            $table->timestamp('computed_at')->useCurrent();

            $table->primary(['snapshot_date', 'dimension', 'dimension_value']);
            $table->index('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_kpi_snapshots');
        Schema::dropIfExists('analytics_ad_spend');
        Schema::dropIfExists('analytics_attribution');
        Schema::dropIfExists('analytics_funnel_events');
    }
};
