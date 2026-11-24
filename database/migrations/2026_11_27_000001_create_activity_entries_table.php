<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coach App activity log (build guide 5.8): workouts (minutes + calories from MET and weight) and a daily steps entry.
 * Calories are worked out when logged and stored, so a later weight change never rewrites old entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('logged_at');
            $table->string('kind', 10);                                 // workout | steps
            $table->string('workout_key', 30)->nullable();              // key in config coach.activity.workouts
            $table->string('name', 80);
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->unsignedInteger('steps')->nullable();
            $table->unsignedSmallInteger('kcal');
            $table->decimal('met', 4, 1)->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();             // weight used for the estimate
            $table->timestamps();

            $table->index(['user_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_entries');
    }
};
