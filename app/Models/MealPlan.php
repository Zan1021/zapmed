<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A member's current 7-day meal plan. Day 0 is starts_on. */
class MealPlan extends Model
{
    protected $fillable = ['user_id', 'template', 'starts_on', 'generated_at'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'generated_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MealPlanItem::class);
    }
}
