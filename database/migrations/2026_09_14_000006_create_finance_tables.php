<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 7 (finance half) — parity with Mark's finance_reports module
 * (finance_reports/migrations/0001_init.up.sql). specs/contro-rebuild/08 §2.6.
 *
 *   finance_revenue_entries — immutable accounting ledger (cash/recognised/pipeline/refund/
 *                             chargeback/discount). Reports SUM over it. Amounts in cents; NEGATIVE
 *                             for refunds/chargebacks/discounts.
 *   finance_recon_entries   — PayFast ↔ pharmacy reconciliation, one row per (order, invoice).
 *
 * NOTE on delta_cents: Mark's Postgres uses a STORED generated column. That does not port to SQLite,
 * so we DO NOT create a generated column — FinanceReconEntry computes `delta_cents` as an accessor.
 * Keeps the migration driver-agnostic (local SQLite / prod Postgres).
 *
 * ⚠️ Import-safety: recording revenue/recon entries is bookkeeping only — it never moves money.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── revenue ledger (immutable accounting entries) ─────────────────────────────────────────
        Schema::create('finance_revenue_entries', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->default('zapmed');
            $table->string('kind', 32);
            // When this revenue is attributed (the day it counts toward MRR/cash).
            $table->date('effective_date');
            // Amount in cents. NEGATIVE for refunds/chargebacks/discounts.
            $table->integer('amount_cents');
            $table->string('currency', 3)->default('ZAR');
            // What this entry is for: 'medication','consult','subscription','copay','other'.
            $table->string('revenue_category')->default('other');
            $table->string('service_line')->nullable();
            // Optional links to source aggregates.
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('principal_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index(['kind', 'effective_date']);
            $table->index(['service_line', 'effective_date']);
            $table->index('payment_id');
            $table->index('subscription_id');
        });

        // ── PayFast ↔ pharmacy reconciliation ─────────────────────────────────────────────────────
        Schema::create('finance_recon_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            // Pharmacy / Scriptwise invoice reference (external).
            $table->string('pharmacy_invoice_ref')->nullable();
            $table->integer('pharmacy_amount_cents')->nullable();
            $table->integer('payment_amount_cents')->nullable();
            // delta_cents is computed in the model (Postgres had a STORED generated column; SQLite can't).
            $table->string('status', 20)->default('unmatched');
            $table->text('notes')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('written_off_at')->nullable();
            $table->foreignId('written_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('written_off_reason')->nullable();

            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index('status');
            $table->index('order_id');
            $table->index('pharmacy_invoice_ref');
        });

        // One recon row per (order, invoice) — parity with uq_recon_order_invoice.
        // Postgres/SQLite honour the COALESCE expression index; other drivers get a plain unique.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            Schema::getConnection()->statement(
                "CREATE UNIQUE INDEX uq_recon_order_invoice ON finance_recon_entries (order_id, COALESCE(pharmacy_invoice_ref, ''))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_recon_entries');
        Schema::dropIfExists('finance_revenue_entries');
    }
};
