<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doctor Requests (Craig's guide 4.1 and 8.1): shared work items between doctors, Operations and coaches.
 * Description and response are encrypted (they can name a patient's situation). Files sit on a private disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();          // encrypted
            $table->string('status', 16)->default('pending');  // pending | in_review | in_progress | submitted | completed
            $table->text('response')->nullable();              // encrypted
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });

        // role = assignee (does the work) | participant (kept in the loop)
        Schema::create('doctor_request_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12)->default('assignee');
            $table->timestamps();
            $table->unique(['doctor_request_id', 'user_id']);
        });

        Schema::create('doctor_request_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
            $table->index('patient_id');
        });

        Schema::create('doctor_request_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamps();
        });

        // One row per change: who did what and when (the audit trail the guide asks for).
        Schema::create('doctor_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 32);                      // created | status | file | participant
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['doctor_request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_request_events');
        Schema::dropIfExists('doctor_request_files');
        Schema::dropIfExists('doctor_request_refs');
        Schema::dropIfExists('doctor_request_users');
        Schema::dropIfExists('doctor_requests');
    }
};
