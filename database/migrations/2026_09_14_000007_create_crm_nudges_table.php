<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 8 — AI assist: crm_nudges (parity with Mark's crm nudge workflow). specs/contro-rebuild/08 §2.7.
 *
 * A nudge is an AI-/rule-drafted outreach suggestion for a lead. It is created in DRAFT and MUST be
 * approved by ops before it is sent — the AI never sends anything itself (draft → approved → sent,
 * or dismissed). generated_by records whether the draft came from the LLM ('ai') or the deterministic
 * fallback ('rules'), so ops can see provenance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_nudges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();

            // What kind of outreach + which channel it is drafted for.
            $table->string('kind', 40)->default('reengagement'); // reengagement, cross_sell, winback, checkin
            $table->string('channel', 20)->default('email');     // email, sms, whatsapp, call
            $table->string('title')->nullable();
            $table->text('draft_body');
            $table->string('generated_by', 12)->default('rules'); // ai | rules
            $table->json('context')->nullable();                  // signals the draft was based on

            // draft → approved → sent (or dismissed).
            $table->string('status', 20)->default('draft');

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dismissed_at')->nullable();
            $table->string('dismissed_reason')->nullable();

            $table->string('upstream_id')->nullable();
            $table->string('upstream_source', 32)->default('contro');
            $table->timestamp('upstream_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['upstream_source', 'upstream_id']);
            $table->index(['status', 'created_at']);
            $table->index(['crm_lead_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_nudges');
    }
};
