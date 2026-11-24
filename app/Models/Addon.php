<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An extra Operations can offer on an order, for example health coach support. */
class Addon extends Model
{
    protected $fillable = ['name', 'price_cents', 'categories', 'is_active', 'updated_by'];

    protected function casts(): array
    {
        return ['price_cents' => 'integer', 'categories' => 'array', 'is_active' => 'boolean'];
    }
}
