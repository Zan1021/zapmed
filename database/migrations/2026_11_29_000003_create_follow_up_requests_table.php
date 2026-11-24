<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doctor follow-up requests (Craig's guide 4.2, 7, 8.2). A doctor asks for a follow-up in 7/14/20/30/60/90 days.
 * While one is open (requested or booked) the order's next repeat is blocked. Done when the doctor has reviewed
 * and submitted again. The first follow-up on an order is free, later ones are paid (recorded here; the payment
 * itself belongs to the patient Follow-Up tab).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();   // the doctor
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->unsignedSmallInteger('days');
            $table->timestamp('due_at');
            $table->string('status', 12)->default('requested');   // requested | booked | completed | cancelled
            $table->boolean('is_free')->default(false);            // first follow-up on the order
            $table->text('note')->nullable();                      // doctor's internal note (encrypted)
            $table->string('cancel_reason', 255)->nullable();
            $table->string('order_status_before', 40)->nullable(); // set only when the order status was moved
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'due_at']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_requests');
    }
};
