<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 4 — Payments extensions for the Contro import (blueprint §2.6).
 *
 * The `payments` table already exists (provider default 'payfast', amount in cents, provider_data json)
 * and already carries the Task 1 crosswalk columns. This migration adds:
 *   - idempotency_key (unique)         — dedup guard for provider callbacks / repeat charges
 *   - payment_type                     — Contro type: card/medical_aid/eft/...
 *   - failure_reason                   — Contro failureReason
 *   - retry_count                      — Contro retryCount
 *   - is_repeat_charge                 — Contro isRepeatCharge (subscription repeat)
 *   - medical_aid_claim_status         — Contro medicalAidClaimStatus
 *   - order_id                         — link to the new Order aggregate (Task 2)
 * plus three child tables: payment_attempts, refunds, payment_webhook_events.
 *
 * ZERO-AMOUNT: Contro sends zero-amount pending payment rows. `payments.amount` is a signed integer,
 * so >= 0 is already permitted at the column level; we add NO positive-only DB check. Documented here
 * so nobody "helpfully" adds an amount > 0 constraint later and breaks the import.
 *
 * GATEWAY: the owned system uses PayFast (config('contro.payment_gateway') = 'payfast'). Contro's
 * provider value maps to 'payfast' during reconcile; provider column default is already 'payfast'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('appointment_id')->constrained()->nullOnDelete();
            $table->string('idempotency_key')->nullable()->after('provider_reference')->unique();
            $table->string('payment_type')->nullable()->after('payment_method'); // Contro type
            $table->string('failure_reason')->nullable()->after('payment_type');
            $table->unsignedInteger('retry_count')->default(0)->after('failure_reason');
            $table->boolean('is_repeat_charge')->default(false)->after('retry_count');
            $table->string('medical_aid_claim_status')->nullable()->after('is_repeat_charge');
        });

        // Per-attempt log (retries against a single payment).
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('status', 20);
            $table->string('provider_reference')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('provider_data')->nullable();
            $table->timestamp('attempted_at')->useCurrent();

            // Contro crosswalk (attempts may derive from Contro payment rows).
            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index(['payment_id', 'attempt_number']);
        });

        // Refunds (a payment can have multiple partial refunds).
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->integer('amount_minor'); // cents
            $table->string('currency', 3)->default('ZAR');
            $table->string('status', 20)->default('pending'); // pending|completed|failed
            $table->string('reason')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index('payment_id');
        });

        // Provider webhook events, deduped on provider_event_id.
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20)->default('payfast');
            $table->string('provider_event_id')->nullable();
            $table->string('event_type')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            // Dedup: one row per (provider, provider_event_id).
            $table->unique(['provider', 'provider_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_attempts');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_idempotency_key_unique');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn([
                'idempotency_key', 'payment_type', 'failure_reason',
                'retry_count', 'is_repeat_charge', 'medical_aid_claim_status',
            ]);
        });
    }
};
