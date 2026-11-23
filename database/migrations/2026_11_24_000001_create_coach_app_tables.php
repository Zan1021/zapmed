<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coach App (MVP) tracking tables. Every row belongs to ONE patient (users.id).
 * Access is enforced in App\Services\Coach\CoachAccess (patient themself, assigned coach, treating doctor).
 * Numeric tracking values stay queryable (charts, flags); free text is kept to a minimum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('start_weight_kg', 5, 1)->nullable();
            $table->decimal('goal_weight_kg', 5, 1)->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->unsignedTinyInteger('target_months')->nullable();   // 3, 6, 9, 12
            $table->string('units', 2)->default('kg');                  // kg | lb
            $table->string('diet', 30)->nullable();
            $table->json('allergies')->nullable();
            $table->json('avoids')->nullable();
            $table->string('activity_level', 20)->nullable();
            $table->json('health_concerns')->nullable();
            $table->string('worry', 30)->nullable();                    // onboarding answer
            $table->boolean('track_cycle')->default(false);
            $table->unsignedTinyInteger('cycle_length')->default(28);
            $table->unsignedTinyInteger('period_length')->default(5);
            $table->date('last_period_start')->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('weight_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('logged_on');
            $table->decimal('weight_kg', 5, 1);
            $table->string('cycle_phase', 12)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'logged_on']); // saving again today replaces today's value
        });

        Schema::create('dose_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('taken_at');
            $table->string('site', 30);
            $table->unsignedTinyInteger('pain')->default(0);            // 0-10
            $table->timestamps();
            $table->index(['user_id', 'taken_at']);
        });

        Schema::create('side_effect_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('logged_on');
            $table->string('symptom', 60);
            $table->unsignedTinyInteger('score');                       // 0-10
            $table->boolean('flagged')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'logged_on']);
        });

        // A patient's own symptoms, and default symptoms they chose to hide.
        Schema::create('custom_symptoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('kind', 16);                                 // custom | hidden_default
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('water_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('logged_at');
            $table->unsignedSmallInteger('ml');
            $table->timestamps();
            $table->index(['user_id', 'logged_at']);
        });

        Schema::create('reminder_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                                 // hydration | weigh_in | dose_day | meals | cycle
            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'type']);
        });

        Schema::create('coach_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->unsignedTinyInteger('length_minutes');              // 15 | 30
            $table->string('status', 12)->default('booked');            // booked | cancelled | completed
            $table->timestamps();
            $table->index(['coach_id', 'starts_at']);
        });

        // Safety escalations: serious symptom or "something feels wrong". Reaches a doctor with no queue.
        Schema::create('coach_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->string('source', 20);                               // side_effect | self_report
            $table->string('symptom', 60)->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('status', 12)->default('open');              // open | handled
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['coach_flags', 'coach_calls', 'reminder_settings', 'water_entries', 'custom_symptoms',
            'side_effect_entries', 'dose_entries', 'weight_entries', 'coach_profiles'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
