<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Coupon (blueprint §2.3). value = basis points for percent, minor units for fixed.
 */
class CatalogCoupon extends Model
{
    protected $table = 'catalog_coupons';

    protected $fillable = [
        'code', 'type', 'value', 'expiry_dt', 'number_of_uses', 'max_uses',
        'is_cancelled', 'contro_created_dt', 'metadata',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'type' => 'percent',
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'number_of_uses' => 'integer',
            'max_uses' => 'integer',
            'is_cancelled' => 'boolean',
            'expiry_dt' => 'datetime',
            'contro_created_dt' => 'datetime',
            'metadata' => 'array',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CatalogCouponUsage::class);
    }
}
