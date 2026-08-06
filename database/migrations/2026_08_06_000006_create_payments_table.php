<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique(); // PAY-XXXXXXXXXX
            $table->foreignId('patient_id')->constrained('users');
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20)->default('payfast'); // payfast
            $table->string('provider_reference')->nullable(); // PayFast payment ID
            $table->integer('amount'); // in cents
            $table->string('currency', 3)->default('ZAR');
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed, refunded, cancelled
            $table->string('payment_method', 30)->nullable(); // credit_card, eft, instant_eft
            $table->text('description')->nullable();
            $table->json('provider_data')->nullable(); // full PayFast response
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->integer('refund_amount')->nullable();
            $table->string('refund_reason')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'status']);
            $table->index('provider_reference');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
