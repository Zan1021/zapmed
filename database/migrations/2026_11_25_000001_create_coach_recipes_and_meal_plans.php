<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coach App v2: recipes, favourites and the 7-day meal plan (build guide 5.11 and 5.12, design screens 16 to 18).
 *
 * Nutrition on a recipe is per ONE serving; the app scales it when a member picks 1 to 4 servings. A recipe is only
 * offered to members once a dietitian has approved it (approved_at). Seeded demo recipes (source = demo) are sample
 * content until Amy supplies and checks the real set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('slot', 12);                          // breakfast | lunch | dinner | snack
            $table->string('name');
            $table->string('summary', 280)->nullable();
            $table->unsignedSmallInteger('minutes')->default(15);
            $table->unsignedSmallInteger('kcal');
            $table->decimal('protein_g', 6, 1);
            $table->decimal('fibre_g', 6, 1)->default(0);
            $table->decimal('carbs_g', 6, 1)->default(0);
            $table->decimal('fat_g', 6, 1)->default(0);
            $table->decimal('sugars_g', 6, 1)->default(0);
            $table->json('extras')->nullable();                  // vitamins and minerals, estimates
            $table->json('ingredients');                         // [{name, grams}]
            $table->json('steps');                               // [text]
            $table->json('diet_tags')->nullable();               // diets this meal suits: vegetarian, vegan, pescatarian, halal
            $table->json('allergens')->nullable();               // allergy keys it contains (config coach.profile.allergies)
            $table->json('avoid_tags')->nullable();              // avoid keys it contains (config coach.profile.avoids)
            $table->string('colour', 9)->nullable();            // tile colour when there is no photo
            $table->string('emoji', 16)->nullable();
            $table->string('image_path')->nullable();
            $table->string('source', 10)->default('amy');       // amy | demo
            $table->boolean('is_active')->default(true);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['slot', 'is_active']);
        });

        Schema::create('favourite_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'recipe_id']);
        });

        Schema::create('meal_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('template', 24)->default('treatment_friendly');
            $table->date('starts_on');                           // day 0 of the 7 days (South African date)
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('meal_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_id')->constrained('meal_plans')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_index');            // 0 to 6
            $table->string('slot', 12);
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->unsignedTinyInteger('servings')->default(1);
            $table->boolean('locked')->default(false);
            $table->boolean('is_new')->default(false);           // "new for you"
            $table->boolean('protein_boost')->default(false);
            $table->timestamp('eaten_at')->nullable();
            $table->timestamps();

            $table->unique(['meal_plan_id', 'day_index', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plan_items');
        Schema::dropIfExists('meal_plans');
        Schema::dropIfExists('favourite_recipes');
        Schema::dropIfExists('recipes');
    }
};
