<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Group promo banners (mobi slider). A banner belongs to a pharmacy GROUP and
 * shows on the patient tracker (after consent) for patients in that group.
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spar_banners')) {
            return;
        }

        Schema::create('spar_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('spar_pharmacy_groups')->cascadeOnDelete();
            $table->string('title')->nullable()->comment('Internal label for admins');
            $table->string('image_path')->comment('Stored WebP (public disk)');
            $table->string('link_url')->nullable()->comment('Optional click-through target');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['group_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spar_banners');
    }
};
