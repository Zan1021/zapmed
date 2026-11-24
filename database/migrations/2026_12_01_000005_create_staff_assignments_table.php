<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who looks after a patient (Craig's guide 2 and 8.1): Operations can give one patient several doctors and several health
 * coaches, each with a start and an end, and the trail of who assigned them. An assignment gives role-appropriate access to the
 * patient (a doctor to the doctor tools, a coach to the coaching data). It never gives a coach the doctor's confidential notes
 * or the other way round: those rules are by role and stay as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 12);                                   // doctor | health_coach
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'ended_at']);
            $table->index(['staff_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_assignments');
    }
};
