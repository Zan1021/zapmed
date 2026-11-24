<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doctor authorisations (Craig's guide 4.1): the period (six months) a doctor has authorised an order's repeats.
 * Reviewed around month five: renew for six more months, or ask for a follow-up first.
 * status: active | renewed (replaced by a newer one) | expired (ran out without being renewed)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_authorisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();      // who authorised
            $table->foreignId('previous_id')->nullable()->constrained('order_authorisations')->nullOnDelete();
            $table->foreignId('follow_up_request_id')->nullable()->constrained('follow_up_requests')->nullOnDelete();
            $table->date('starts_on');
            $table->date('expires_on');
            $table->string('status', 10)->default('active');
            $table->text('note')->nullable();                      // encrypted
            $table->timestamp('renewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_on']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_authorisations');
    }
};
