<?php

namespace Zapmed\SparCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Health Coach v1 (spec spar-health-coach §2.4). A line item ("basket" line) on
 * a SPAR order. v1 source is coach_suggestion; manual/other sources come later.
 */
class SparOrderItem extends Model
{
    protected $table = 'spar_order_items';

    protected $fillable = [
        'spar_order_id',
        'source',
        'product_name',
        'price_cents',
        'qty',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'qty' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SparOrder::class, 'spar_order_id');
    }

    public function lineTotalCents(): int
    {
        return (int) ($this->price_cents ?? 0) * (int) ($this->qty ?? 1);
    }
}
