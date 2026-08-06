<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique(); // ZAP-XXXXXX
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30)->default('general'); // general, follow_up, chronic_renewal, new_patient
            $table->string('status', 20)->default('pending'); // pending, confirmed, in_progress, completed, cancelled, no_show
            $table->date('appointment_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes')->default(30);
            $table->text('reason')->nullable(); // patient's reason for booking
            $table->text('notes')->nullable(); // internal notes
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->integer('fee_amount'); // in cents at time of booking
            $table->boolean('is_paid')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['doctor_id', 'appointment_date']);
            $table->index(['patient_id', 'status']);
            $table->index('appointment_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
