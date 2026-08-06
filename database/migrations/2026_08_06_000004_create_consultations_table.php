<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users');
            $table->foreignId('doctor_id')->constrained('users');
            $table->string('status', 20)->default('in_progress'); // in_progress, completed, requires_follow_up
            $table->text('presenting_complaint')->nullable();
            $table->text('history_of_presenting_illness')->nullable();
            $table->text('examination_findings')->nullable();
            $table->text('diagnosis')->nullable();
            $table->string('icd10_code', 10)->nullable(); // ICD-10 diagnosis code
            $table->text('treatment_plan')->nullable();
            $table->text('doctor_notes')->nullable(); // private notes (not shared with patient)
            $table->boolean('follow_up_required')->default(false);
            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['doctor_id', 'created_at']);
            $table->index(['patient_id', 'created_at']);
        });

        // Video session metadata (we don't record, just log)
        Schema::create('video_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('daily'); // daily.co
            $table->string('room_name')->nullable();
            $table->string('room_url')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('quality', 20)->nullable(); // good, fair, poor
            $table->json('metadata')->nullable(); // provider-specific data
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_sessions');
        Schema::dropIfExists('consultations');
    }
};
