<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->text('response');
            $table->string('matched_treatment_slug')->nullable();
            $table->string('matched_treatment_name')->nullable();
            $table->boolean('had_match')->default(false); // did AI find a relevant treatment?
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('source_page')->nullable(); // which page the question came from
            $table->timestamps();

            $table->index('matched_treatment_slug');
            $table->index('had_match');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
