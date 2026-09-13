<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 9 — append-only audit trail for the CRM domains (parity with Mark's audit module intent).
 * specs/contro-rebuild/08 §2.8.
 *
 * Complements (does not replace) the existing ClinicalAuditLogger (Monolog `clinical_audit` channel,
 * clinical READ access). This is a QUERYABLE DB trail for CRM-domain WRITES (consent, DSAR, funnel
 * stage moves, flag/nudge/coaching actions, etc.) so ops/auditors can answer "who changed what, when".
 *
 * Append-only is enforced at the application layer (AuditTrail service only ever inserts; models have
 * no update/delete path) AND made tamper-evident with a per-row hash chain: hash = sha256(prev_hash +
 * canonical payload). A broken chain reveals tampering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 40);          // consent, dsar, funnel, flag, nudge, coaching, ...
            $table->string('action', 60);          // consent.granted, dsar.completed, lead.stage_changed
            $table->string('subject_type')->nullable(); // model class / logical type
            $table->string('subject_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label')->nullable(); // 'admin:12' / 'system' / 'import'
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // Tamper-evident hash chain.
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64);

            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['domain', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_audit_events');
    }
};
