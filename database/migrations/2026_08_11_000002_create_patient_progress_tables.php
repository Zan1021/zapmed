<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Main progress log — daily entries
        Schema::create('progress_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->decimal('waist_cm', 5, 1)->nullable();
            $table->unsignedTinyInteger('energy_level')->nullable(); // 1-10
            $table->unsignedTinyInteger('mood')->nullable(); // 1-10
            $table->unsignedTinyInteger('sleep_hours')->nullable(); // 0-24
            $table->unsignedTinyInteger('sleep_quality')->nullable(); // 1-10
            $table->unsignedTinyInteger('water_glasses')->nullable(); // 0-20
            $table->boolean('medication_taken')->default(false);
            $table->boolean('exercised')->default(false);
            $table->string('exercise_type', 100)->nullable();
            $table->unsignedSmallInteger('exercise_minutes')->nullable();
            $table->text('meals_summary')->nullable(); // brief description
            $table->text('symptoms')->nullable(); // any side effects or issues
            $table->text('notes')->nullable(); // general notes
            $table->timestamps();

            $table->unique(['user_id', 'log_date']);
            $table->index(['user_id', 'created_at']);
        });

        // Goals table — patients set targets
        Schema::create('patient_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // weight, waist, exercise, water, sleep
            $table->decimal('target_value', 8, 2);
            $table->string('unit', 20); // kg, cm, minutes/day, glasses, hours
            $table->date('target_date')->nullable();
            $table->decimal('start_value', 8, 2)->nullable();
            $table->boolean('achieved')->default(false);
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_goals');
        Schema::dropIfExists('progress_logs');
    }
};
