<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Health Coach v1 (spec spar-health-coach FR-1). A conversation binds the
 * PRIMARY member of a profile to a single pharmacy (the coach speaks for that
 * store). At most one OPEN conversation per (patient, pharmacy) — enforced in
 * SparCoachService. Unread counts drive the patient + staff badges.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spar_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spar_patient_id')->constrained('spar_patients')->cascadeOnDelete();
            $table->foreignId('spar_pharmacy_id')->constrained('spar_pharmacies')->cascadeOnDelete();
            $table->string('status')->default('open'); // open | closed
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('patient_unread_count')->default(0);
            $table->unsignedInteger('staff_unread_count')->default(0);
            $table->timestamps();

            $table->index(['spar_patient_id', 'spar_pharmacy_id', 'status']);
            $table->index(['spar_pharmacy_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_conversations');
    }
};
