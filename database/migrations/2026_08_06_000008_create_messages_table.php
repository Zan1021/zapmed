<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conversation threads between patient and doctor
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users');
            $table->foreignId('doctor_id')->constrained('users');
            $table->string('subject')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'doctor_id']);
            $table->index('last_message_at');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users');
            $table->text('body');
            $table->string('type', 20)->default('text'); // text, image, document
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'created_at']);
        });

        // Notifications (in-app)
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50); // appointment_reminder, prescription_ready, message_received, etc
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('action_url')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
