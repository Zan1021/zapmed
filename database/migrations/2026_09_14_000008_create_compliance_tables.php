<?php

use App\Enums\RetentionAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 9 — Compliance (POPIA) parity with Mark's compliance module (compliance/migrations/0001_init).
 * specs/contro-rebuild/08 §2.8.
 *
 * Tables:
 *   compliance_consents          — one row per (principal × purpose): current state + policy version.
 *   compliance_consent_records   — immutable audit of every grant/withdraw (append-only).
 *   compliance_dsars             — data-subject access requests (access/export/erasure/…); 30-day SLA.
 *   compliance_dsar_artifacts    — each produced output (export json/csv, erasure certificate) + sha256.
 *   compliance_retention_policies— per data-class retention rule (retain_days + action + legal basis).
 *   compliance_retention_schedule— per-record scheduled erasure/redaction, with legal-hold support.
 *
 * NOTE: this is a NEW compliance_* namespace mirroring the reference. The legacy `consent_records`
 * table (thin boolean consent used by public signup) is left UNTOUCHED — this is the richer POPIA
 * layer, not a replacement of that capture point. Driver-agnostic (json not jsonb; inet → string).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── consent: current state per principal × purpose ────────────────────────────────────────
        Schema::create('compliance_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('principal_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('state', 24)->default('pending_reconsent');
            $table->string('policy_version')->default('v1.0');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('evidence_ref')->nullable(); // URL / content hash of what was agreed to
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['principal_id', 'purpose']);
            $table->index('state');
        });

        // ── consent_records: immutable audit of every state change ────────────────────────────────
        Schema::create('compliance_consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consent_id')->constrained('compliance_consents')->cascadeOnDelete();
            $table->foreignId('principal_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('new_state', 24);
            $table->string('policy_version');
            $table->string('reason')->nullable();
            $table->string('ip_address', 45)->nullable();  // inet → string (IPv6-safe length)
            $table->string('user_agent')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['principal_id', 'occurred_at']);
            $table->index(['consent_id', 'occurred_at']);
        });

        // ── DSAR ────────────────────────────────────────────────────────────────────────────────
        Schema::create('compliance_dsars', function (Blueprint $table) {
            $table->id();
            $table->string('dsar_number')->unique();
            $table->string('tenant_id')->default('zapmed');
            $table->foreignId('principal_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('status', 24)->default('received');
            $table->text('request_detail')->nullable();
            $table->text('handler_notes')->nullable();      // ops-side, not patient-visible
            $table->timestamp('due_at');                    // 30 days from receipt (POPIA outer bound)
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('retention_schedule_id')->nullable();
            $table->string('verification_evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['principal_id', 'received_at']);
            $table->index('kind');
            $table->index(['status', 'due_at']);
        });

        // ── DSAR artifacts ────────────────────────────────────────────────────────────────────────
        Schema::create('compliance_dsar_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dsar_id')->constrained('compliance_dsars')->cascadeOnDelete();
            $table->string('kind'); // export.json, export.csv, erasure_certificate, ...
            $table->string('location');
            $table->string('checksum_sha256', 64);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('dsar_id');
        });

        // ── retention policies (per data class) ─────────────────────────────────────────────────
        Schema::create('compliance_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('data_class')->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('retain_days');
            $table->string('action', 24)->default('erase');
            $table->string('legal_basis')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── retention schedule (one per record × policy) ──────────────────────────────────────────
        Schema::create('compliance_retention_schedule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained('compliance_retention_policies')->restrictOnDelete();
            $table->string('data_class');
            $table->unsignedBigInteger('aggregate_id'); // soft FK into the source table
            $table->foreignId('principal_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at');
            $table->string('status', 24)->default('scheduled');
            $table->boolean('legal_hold')->default(false);
            $table->string('legal_hold_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['data_class', 'aggregate_id']);
            $table->index(['status', 'due_at']);
            $table->index('principal_id');
        });

        $this->seedRetentionPolicies();
    }

    /**
     * Seed the POPIA/HPCSA-aligned default retention policies (verbatim from the reference seed).
     * Idempotent via updateOrInsert on data_class.
     */
    private function seedRetentionPolicies(): void
    {
        $sevenYears = 7 * 365;
        $policies = [
            ['clinical_review', 'Doctor consultation reviews', $sevenYears, RetentionAction::ArchiveAndErase->value, 'HPCSA records 6yr; POPIA s14'],
            ['clinical_prescription', 'Issued prescriptions', $sevenYears, RetentionAction::ArchiveAndErase->value, 'HPCSA records 6yr; POPIA s14'],
            ['orders_order', 'Sales orders', $sevenYears, RetentionAction::Redact->value, 'POPIA s14; tax record retention'],
            ['payments_payment', 'Payment records', $sevenYears, RetentionAction::Redact->value, 'POPIA s14; tax record retention'],
            ['consultations_consultation', 'Consultations metadata', $sevenYears, RetentionAction::Redact->value, 'HPCSA records 6yr'],
            ['pharmacy_dispatch', 'Pharmacy dispatch records', $sevenYears, RetentionAction::Redact->value, 'HPCSA records 6yr'],
            ['identity_session', 'Auth sessions', 180, RetentionAction::Erase->value, 'Operational only'],
            ['audit_trail', 'Audit trail', $sevenYears, RetentionAction::ArchiveAndErase->value, 'POPIA s14; SOX-style retention'],
            ['notifications_notification', 'Notification log', 365, RetentionAction::Erase->value, 'Operational only'],
            ['patient_profile_profile', 'Patient profiles (after archive)', $sevenYears, RetentionAction::Redact->value, 'POPIA s14'],
        ];

        foreach ($policies as [$dataClass, $description, $retainDays, $action, $legalBasis]) {
            DB::table('compliance_retention_policies')->updateOrInsert(
                ['data_class' => $dataClass],
                [
                    'description' => $description,
                    'retain_days' => $retainDays,
                    'action' => $action,
                    'legal_basis' => $legalBasis,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_retention_schedule');
        Schema::dropIfExists('compliance_retention_policies');
        Schema::dropIfExists('compliance_dsar_artifacts');
        Schema::dropIfExists('compliance_dsars');
        Schema::dropIfExists('compliance_consent_records');
        Schema::dropIfExists('compliance_consents');
    }
};
