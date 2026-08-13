<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('articles_count')->default(0);
            $table->timestamps();
        });

        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_category_id')->constrained('help_categories')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('body');
            $table->text('excerpt')->nullable();
            $table->string('status')->default('draft');
            $table->string('visibility')->default('public');
            $table->integer('sort_order')->default(0);
            $table->integer('views')->default(0);
            $table->integer('helpful_yes')->default(0);
            $table->integer('helpful_no')->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('help_search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query');
            $table->integer('results_count')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('help_article_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->boolean('is_helpful');
            $table->text('comment')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_article_feedback');
        Schema::dropIfExists('help_search_logs');
        Schema::dropIfExists('help_articles');
        Schema::dropIfExists('help_categories');
    }
};
