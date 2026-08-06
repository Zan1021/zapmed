<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('treatment_slug');
            $table->string('treatment_name');
            $table->json('answers');
            $table->string('status', 20)->default('completed');
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'treatment_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
