<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A recipe members can favourite and the meal plan can use. Nutrition columns are per ONE serving. */
class Recipe extends Model
{
    public const SLOTS = ['breakfast', 'lunch', 'dinner', 'snack'];

    protected $fillable = [
        'slot', 'name', 'summary', 'minutes', 'kcal', 'protein_g', 'fibre_g', 'carbs_g', 'fat_g', 'sugars_g',
        'extras', 'ingredients', 'steps', 'diet_tags', 'allergens', 'avoid_tags', 'colour', 'emoji', 'image_path',
        'source', 'is_active', 'approved_by', 'approved_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'kcal' => 'integer',
            'protein_g' => 'float',
            'fibre_g' => 'float',
            'carbs_g' => 'float',
            'fat_g' => 'float',
            'sugars_g' => 'float',
            'extras' => 'array',
            'ingredients' => 'array',
            'steps' => 'array',
            'diet_tags' => 'array',
            'allergens' => 'array',
            'avoid_tags' => 'array',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** What members may see and the meal plan may use: switched on and approved by a dietitian. */
    public function scopeUsable($query)
    {
        return $query->where('is_active', true)->whereNotNull('approved_at');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function isDemo(): bool
    {
        return $this->source === 'demo';
    }

    /**
     * Nutrition for a number of servings (estimates, one decimal).
     *
     * @return array{kcal:int,protein_g:float,fibre_g:float,carbs_g:float,fat_g:float,sugars_g:float}
     */
    public function nutrition(int $servings = 1): array
    {
        $n = max(1, $servings);

        return [
            'kcal' => (int) round($this->kcal * $n),
            'protein_g' => round($this->protein_g * $n, 1),
            'fibre_g' => round($this->fibre_g * $n, 1),
            'carbs_g' => round($this->carbs_g * $n, 1),
            'fat_g' => round($this->fat_g * $n, 1),
            'sugars_g' => round($this->sugars_g * $n, 1),
        ];
    }

    /** Ingredient amounts for a number of servings. @return array<int,array{name:string,grams:float}> */
    public function scaledIngredients(int $servings = 1): array
    {
        $n = max(1, $servings);

        return array_map(fn ($i) => ['name' => $i['name'], 'grams' => round(((float) $i['grams']) * $n, 1)], (array) $this->ingredients);
    }
}
