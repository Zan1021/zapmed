<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A repeat cycle Operations can set: pack length, first-repeat day, same-day flag, on or off. */
class RepeatCycle extends Model
{
    protected $fillable = ['description', 'days', 'first_repeat_days', 'same_day', 'is_enabled', 'updated_by'];

    protected function casts(): array
    {
        return ['days' => 'integer', 'first_repeat_days' => 'integer', 'same_day' => 'boolean', 'is_enabled' => 'boolean'];
    }

    public function scopeEnabled($q)
    {
        return $q->where('is_enabled', true);
    }
}
