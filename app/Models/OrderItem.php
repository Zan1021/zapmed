<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Order line item — catalog + tax snapshot at order time. Money in minor units (cents).
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'medication_id', 'description', 'nappi_code',
        'quantity', 'unit_price_minor', 'line_total_minor', 'tax_bps', 'tax_minor', 'metadata',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'line_total_minor' => 'integer',
            'tax_bps' => 'integer',
            'tax_minor' => 'integer',
            'metadata' => 'array',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }
}
