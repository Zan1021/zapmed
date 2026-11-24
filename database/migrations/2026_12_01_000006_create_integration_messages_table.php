<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integration log (Craig's guide 6.2, 6.4): one row per attempt to send something to another system (today: a prescription to
 * the pharmacy). status: success | failed (they refused or we could not reach them: safe to try again) | unknown (no answer: do
 * not resend until someone has checked). Attempts of the same prescription are linked by parent_id. Request/response text is
 * kept encrypted and shown only as a short summary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_messages', function (Blueprint $table) {
            $table->id();
            $table->string('system', 20);                          // pharmacy | rxhub | scriptwise | dps
            $table->string('type', 30);                            // prescription_send ...
            $table->foreignId('prescription_id')->nullable()->constrained('prescriptions')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('unknown');
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedBigInteger('parent_id')->nullable();   // the first attempt in this chain
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('request_summary', 255);
            $table->text('response_excerpt')->nullable();          // encrypted
            $table->string('error', 255)->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->foreignId('resent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['system', 'status']);
            $table->index(['prescription_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_messages');
    }
};
