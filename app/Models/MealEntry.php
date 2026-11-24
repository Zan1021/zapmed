<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealEntry extends Model
{
    protected $fillable = [
        'user_id', 'logged_at', 'slot', 'name', 'servings', 'kcal', 'protein_g', 'fibre_g', 'carbs_g', 'fat_g',
        'source', 'recipe_id', 'meal_plan_item_id',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
            'servings' => 'integer',
            'kcal' => 'integer',
            'protein_g' => 'float',
            'fibre_g' => 'float',
            'carbs_g' => 'float',
            'fat_g' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
