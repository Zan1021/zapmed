<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 4 — Alerts / SLA engine (parity with Mark's alerts module, alerts/migrations/0001_init.up.sql
 * + 0002 pending_booking + 0003 abandoned_carts). specs/contro-rebuild/08 §2.3.
 *
 * Staff-facing alert engine — DISTINCT from patient notifications. Detectors (a scheduled scanner)
 * write alert instances; ops ack/resolve/snooze/comment. Idempotency + re-raise are enforced by a
 * dedupe_key with a partial-unique index on the OPEN-ish states, exactly as the reference does.
 *
 * subject_id is a string (not FK): subjects span multiple tables (order/payment/appointment/lead) plus
 * a cohort sentinel ('order_cohort') for the rolled-up abandoned-carts alert — same shape as reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── alerts_definitions: configurable rule templates ────────────────────────────────────────
        Schema::create('alerts_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();               // 'orders.stale', 'payments.failed', ...
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('default_severity', 20)->default('warning'); // critical|warning|informational
            $table->string('source_module');                // orders/payments/consultations/subscriptions/crm
            $table->json('threshold_config')->nullable();   // { "stale_hours": 24 }
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_resolve')->default(true);
            $table->timestamps();

            $table->index('source_module');
        });

        // ── alerts_alerts: fired instances ─────────────────────────────────────────────────────────
        Schema::create('alerts_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('definition_code');
            $table->string('severity', 20);                 // critical|warning|informational
            $table->string('status', 20)->default('open');  // open|acknowledged|resolved|snoozed|auto_closed
            $table->string('title');
            $table->text('detail')->nullable();
            $table->string('subject_type');                 // order|payment|appointment|lead|order_cohort
            $table->string('subject_id');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dedupe_key');                   // code:subjectId — idempotency
            $table->json('metadata')->nullable();
            $table->timestamp('raised_at')->useCurrent();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('last_re_raised_at')->nullable();
            $table->unsignedInteger('re_raise_count')->default(0);
            $table->timestamps();

            $table->index(['severity', 'raised_at']);
            $table->index(['assigned_to', 'status']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('status');
        });

        // At most ONE active (open/acknowledged/snoozed) alert per dedupe_key — the idempotency spine.
        // Partial unique index on Postgres; SQLite (local/testing) also supports partial indexes.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            Schema::getConnection()->statement(
                "CREATE UNIQUE INDEX uq_alert_open_dedupe ON alerts_alerts (dedupe_key) " .
                "WHERE status IN ('open','acknowledged','snoozed')"
            );
        } else {
            // Fallback for engines without partial indexes: enforce in the app layer only.
            Schema::table('alerts_alerts', fn (Blueprint $t) => $t->index('dedupe_key'));
        }

        // ── alerts_comments: activity log ────────────────────────────────────────────────────────
        Schema::create('alerts_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('alerts_alerts')->cascadeOnDelete();
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['alert_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts_comments');
        Schema::dropIfExists('alerts_alerts');
        Schema::dropIfExists('alerts_definitions');
    }
};
