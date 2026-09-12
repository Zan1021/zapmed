<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Commerce catalog item — a sellable SKU / bundle / fee. Receives Contro products (blueprint §2.2).
 * Distinct from `medications` (clinical reference); may optionally link to one. Money in minor units.
 */
class CatalogItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'item_type', 'medication_id', 'nappi_code', 'schedule_class',
        'price_minor', 'currency', 'tax_class', 'pack_qty',
        'is_master_bundle', 'requires_subscription', 'status', 'metadata',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'item_type' => 'product',
        'status' => 'active',
        'currency' => 'ZAR',
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'pack_qty' => 'decimal:3',
            'is_master_bundle' => 'boolean',
            'requires_subscription' => 'boolean',
            'metadata' => 'array',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(CatalogPrice::class)->orderByDesc('effective_from');
    }
}
