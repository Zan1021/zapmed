<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UAT Task 8 — single delivery log for every outbound message (email/SMS/WhatsApp).
 * specs/notifications-parity/01-scope-and-gap-analysis.md (Option B).
 *
 * Before this, sends were scattered across controllers/commands/Livewire with only ad-hoc
 * `*_sent_at` columns and no record of WHAT went out, on which channel, or whether it landed.
 * NotificationDispatcher writes one row here per attempt so ops/auditors can answer
 * "did the patient get their prescription-ready message, and how?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);              // email, sms, whatsapp
            $table->string('template_key', 80);         // e.g. payment.received, appointment.reminder_24h
            $table->string('category', 20)->default('transactional'); // transactional | marketing
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient');                // email address / E.164 number (as sent)
            $table->string('status', 20);               // sent, failed, suppressed, skipped
            $table->string('provider')->nullable();     // brevo, bulksms, clickatell, whatsapp_meta, log
            $table->string('provider_ref')->nullable(); // provider message id when available
            $table->text('error')->nullable();          // failure reason when status=failed
            $table->json('meta')->nullable();           // arbitrary context (appointment_id, etc.)
            $table->timestamps();

            $table->index(['channel', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['template_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
