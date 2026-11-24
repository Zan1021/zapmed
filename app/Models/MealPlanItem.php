<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPlanItem extends Model
{
    protected $fillable = [
        'meal_plan_id', 'day_index', 'slot', 'recipe_id', 'servings', 'locked', 'is_new', 'protein_boost', 'eaten_at',
    ];

    protected function casts(): array
    {
        return [
            'day_index' => 'integer',
            'servings' => 'integer',
            'locked' => 'boolean',
            'is_new' => 'boolean',
            'protein_boost' => 'boolean',
            'eaten_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class, 'meal_plan_id');
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
