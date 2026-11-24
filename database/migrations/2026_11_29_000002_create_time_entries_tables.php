<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doctor time tracking (Craig's guide 4.2 and 8.1): minutes spent on a patient record or a doctor request.
 * An entry is a running timer (ended_at null) or a finished/manual one. Every edit to the minutes leaves an
 * adjustment row with the old value, the new value and the reason, so the monthly invoice summary can be audited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();                 // the doctor
            $table->foreignId('patient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('doctor_request_id')->nullable()->constrained('doctor_requests')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();            // null = timer still running
            $table->unsignedSmallInteger('minutes')->default(0);
            $table->string('source', 8)->default('timer');        // timer | manual
            $table->boolean('capped')->default(false);            // a forgotten timer was cut at the maximum
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'started_at']);
        });

        Schema::create('time_entry_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('old_minutes');
            $table->unsignedSmallInteger('new_minutes');
            $table->string('reason', 255);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entry_adjustments');
        Schema::dropIfExists('time_entries');
    }
};
