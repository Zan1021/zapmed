<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single Contro CouponDto.usage[] entry (blueprint §2.3).
 */
class CatalogCouponUsage extends Model
{
    protected $table = 'catalog_coupon_usage';

    protected $fillable = [
        'catalog_coupon_id', 'pre_value_minor', 'post_value_minor', 'is_redeemed',
        'payment_reference_id', 'expiry_dt', 'contro_create_dt',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'pre_value_minor' => 'integer',
            'post_value_minor' => 'integer',
            'is_redeemed' => 'boolean',
            'expiry_dt' => 'datetime',
            'contro_create_dt' => 'datetime',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(CatalogCoupon::class, 'catalog_coupon_id');
    }
}
