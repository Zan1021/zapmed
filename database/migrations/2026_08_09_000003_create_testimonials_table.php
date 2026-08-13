<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->string('treatment_category', 50); // weight-loss, skincare, mens-health, etc.
            $table->string('treatment_slug', 80)->nullable(); // specific treatment (internal use only)
            $table->unsignedTinyInteger('rating'); // 1-5 stars
            $table->text('comment'); // the testimonial text
            $table->boolean('would_recommend')->default(true);
            $table->boolean('show_name')->default(false); // patient opts in to show first name
            $table->boolean('is_approved')->default(false); // admin moderation
            $table->boolean('is_featured')->default(false); // admin can feature
            $table->timestamps();

            $table->index('treatment_category');
            $table->index(['is_approved', 'rating']);
            $table->unique('consultation_id'); // one testimonial per consultation
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
