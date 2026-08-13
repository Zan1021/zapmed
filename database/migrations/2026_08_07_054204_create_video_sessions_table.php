<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the basic version created in consultations migration, replace with full version
        Schema::dropIfExists('video_sessions');

        Schema::create('video_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();

            // Daily.co room data
            $table->string('room_name')->unique();
            $table->string('room_url');
            $table->text('doctor_token')->nullable();
            $table->text('patient_token')->nullable();

            // Session timing
            $table->string('status')->default('waiting'); // waiting, in_progress, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            // Metadata
            $table->string('ended_by')->nullable(); // doctor, patient, system
            $table->json('metadata')->nullable(); // any extra Daily.co event data

            $table->timestamps();

            $table->index(['appointment_id', 'status']);
            $table->index('doctor_id');
            $table->index('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_sessions');
    }
};
