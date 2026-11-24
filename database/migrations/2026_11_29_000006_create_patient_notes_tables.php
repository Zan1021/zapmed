<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Note separation (Craig's guide 4.3 and 8.1). Every note says who wrote it, who it is for, when, which patient/order
 * and where its action stands. Bodies are encrypted. Notes are append-only: a correction is a new note.
 *
 * type: clinical (authorised doctors only) | patient_summary (the patient may read it) | operations (addressed to chosen staff or roles)
 * Coach notes stay in coach_notes (private / shared), which already follows the same rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 16);
            $table->text('body');                                  // encrypted
            $table->string('action_status', 8)->default('none');   // none | open | done (operations notes that ask for something)
            $table->foreignId('action_done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('action_done_at')->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'type', 'id']);
        });

        // Who an operations note is addressed to: one specific person (user_id) or everybody in a role (role).
        Schema::create('patient_note_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('role', 16)->nullable();                // admin | doctor | health_coach
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_note_audiences');
        Schema::dropIfExists('patient_notes');
    }
};
