<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coach App insights (build guide 5.13): the daily "How are you feeling today?" answer and the thumbs up or down on
 * the day's insight. One row per member per day (South African date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insight_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('answer', 10)->nullable();          // better | same | worse | gone
            $table->boolean('helpful')->nullable();            // thumbs up (true) or down (false)
            $table->timestamp('coach_told_at')->nullable();    // when the coach was told about a "worse" answer
            $table->timestamps();

            $table->unique(['user_id', 'entry_date']);
            $table->index(['answer', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insight_entries');
    }
};
