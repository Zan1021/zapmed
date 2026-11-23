<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coach notes about a member. visibility = private: only the author sees it. shared: the member's assigned coach
 * and a doctor who has an appointment with the member. Operations/admin never see either. Body is stored encrypted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 8)->default('private');   // private | shared
            $table->text('body');
            $table->timestamps();
            $table->index(['patient_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_notes');
    }
};
