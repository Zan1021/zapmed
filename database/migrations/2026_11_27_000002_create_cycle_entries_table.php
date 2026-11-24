<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coach App cycle tracker (build guide 5.9): one mood and symptom entry per day (South African date).
 * Cycle length, period length and the start of the last period already live on coach_profiles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('entry_date');
            $table->unsignedTinyInteger('mood')->nullable();       // 1 to 5
            $table->json('symptoms')->nullable();                  // keys from config coach.cycle.symptoms
            $table->timestamps();

            $table->unique(['user_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_entries');
    }
};
