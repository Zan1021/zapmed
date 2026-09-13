<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 6 — Subscription repeat lifecycle (parity with Mark's subscriptions module,
 * subscriptions/migrations/0001_init.up.sql). specs/contro-rebuild/08 §2.5.
 *
 * Extends the EXISTING `subscriptions` table (do not replace it — live billing already uses it) with
 * the repeat-cycle backbone, and adds three tables: cycles (one per renewal attempt), followups
 * (Completed → +180d review), and pause events (audit trail).
 *
 * ⚠️ LIVE-FLOW ONLY. The cycle runner and 3-strike logic apply to post-cutover live subscriptions.
 * IMPORTED subscriptions must NEVER trigger a real charge: the SubscriptionLifecycle service records
 * cycle OUTCOMES fed to it (it does not call a payment gateway), and imported cycles are seeded in a
 * terminal state, so reconstructing history can't fire money. This is the standing non-negotiable.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Lifecycle columns on the existing subscriptions table (guarded — only add if missing).
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'consecutive_failures')) {
                $table->unsignedInteger('consecutive_failures')->default(0)->after('payment_count');
            }
            if (! Schema::hasColumn('subscriptions', 'completed_cycles')) {
                $table->unsignedInteger('completed_cycles')->default(0)->after('consecutive_failures');
            }
            if (! Schema::hasColumn('subscriptions', 'next_run_at')) {
                $table->timestamp('next_run_at')->nullable()->after('next_billing_date');
            }
            if (! Schema::hasColumn('subscriptions', 'last_failure_at')) {
                $table->timestamp('last_failure_at')->nullable()->after('last_payment_at');
            }
        });

        // ── subscription_cycles: one row per renewal attempt ───────────────────────────────────────
        Schema::create('subscription_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->unsignedInteger('sequence_no');
            // scheduled → attempted → placed → payment_failed / fulfilled (or cancelled)
            $table->string('status', 20)->default('scheduled');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('scheduled_for');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('metadata')->nullable();

            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['subscription_id', 'sequence_no']);
            $table->unique(['upstream_source', 'upstream_id']);
            $table->index(['status', 'scheduled_for']);
        });

        // ── subscription_followups: Completed order → 6-month review ────────────────────────────────
        Schema::create('subscription_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('due_at');
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('consultation_id')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_reason')->nullable();

            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index('due_at');
        });

        // One active follow-up per origin order (parity with uq_followup_origin).
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            Schema::getConnection()->statement(
                'CREATE UNIQUE INDEX uq_followup_origin ON subscription_followups (origin_order_id) WHERE cancelled_at IS NULL'
            );
        }

        // ── subscription_pause_events: pause/resume audit trail ────────────────────────────────────
        Schema::create('subscription_pause_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->timestamp('paused_at')->useCurrent();
            $table->timestamp('paused_until')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->string('paused_reason')->nullable();
            $table->foreignId('paused_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resumed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['subscription_id', 'paused_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_pause_events');
        Schema::dropIfExists('subscription_followups');
        Schema::dropIfExists('subscription_cycles');

        Schema::table('subscriptions', function (Blueprint $table) {
            foreach (['consecutive_failures', 'completed_cycles', 'next_run_at', 'last_failure_at'] as $col) {
                if (Schema::hasColumn('subscriptions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
