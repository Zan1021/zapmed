<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coach App nutrition log (build guide 5.10): what a member ate, with its nutrition, per meal slot.
 *
 * An entry can come from a recipe, from a meal in the plan (ticking the circle), or from a quick add. Nutrition is
 * copied onto the entry so a later recipe edit never rewrites what the member actually logged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('logged_at');
            $table->string('slot', 12);                                  // breakfast | lunch | dinner | snack
            $table->string('name', 120);
            $table->unsignedSmallInteger('servings')->default(1);
            $table->unsignedSmallInteger('kcal');
            $table->decimal('protein_g', 6, 1)->default(0);
            $table->decimal('fibre_g', 6, 1)->default(0);
            $table->decimal('carbs_g', 6, 1)->default(0);
            $table->decimal('fat_g', 6, 1)->default(0);
            $table->string('source', 10)->default('quick');              // quick | recipe | plan
            $table->foreignId('recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->foreignId('meal_plan_item_id')->nullable()->unique()->constrained('meal_plan_items')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_entries');
    }
};
