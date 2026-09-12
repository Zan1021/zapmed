<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time-versioned price for a catalog item (blueprint §2.2). effective_to = null means current.
 */
class CatalogPrice extends Model
{
    protected $fillable = [
        'catalog_item_id', 'price_minor', 'currency', 'effective_from', 'effective_to',
    ];

    protected $attributes = [
        'currency' => 'ZAR',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }
}
