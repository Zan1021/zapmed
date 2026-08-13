<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // consultation, medication, subscription
            $table->string('reference')->nullable(); // appointment/prescription ref
            $table->integer('total_amount'); // what patient paid (cents)

            // Revenue split
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('doctor_amount')->default(0); // cents
            $table->unsignedTinyInteger('doctor_rate')->default(0); // % at time of transaction

            $table->foreignId('pharmacy_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('pharmacy_amount')->default(0);
            $table->unsignedTinyInteger('pharmacy_rate')->default(0);

            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('partner_amount')->default(0);
            $table->unsignedTinyInteger('partner_rate')->default(0);

            $table->integer('platform_amount')->default(0); // Zapmed's cut
            $table->unsignedTinyInteger('platform_rate')->default(0);

            $table->integer('delivery_fee')->default(0);

            // Payout status per recipient
            $table->string('doctor_payout_status', 20)->default('pending'); // pending, paid
            $table->timestamp('doctor_paid_at')->nullable();
            $table->string('pharmacy_payout_status', 20)->default('pending');
            $table->timestamp('pharmacy_paid_at')->nullable();
            $table->string('partner_payout_status', 20)->default('pending');
            $table->timestamp('partner_paid_at')->nullable();

            $table->timestamps();

            $table->index(['doctor_id', 'doctor_payout_status']);
            $table->index(['pharmacy_id', 'pharmacy_payout_status']);
            $table->index(['partner_id', 'partner_payout_status']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
