<?php

namespace App\Models;

use App\Enums\ReconStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A PayFast ↔ pharmacy reconciliation row for one order/invoice. delta_cents (pharmacy − payment)
 * is computed here rather than in the DB — Mark's Postgres used a STORED generated column, which
 * SQLite (our local driver) cannot express.
 *
 * @property-read int $delta_cents
 */
class FinanceReconEntry extends Model
{
    protected $table = 'finance_recon_entries';

    protected $fillable = [
        'order_id', 'payment_id', 'pharmacy_invoice_ref',
        'pharmacy_amount_cents', 'payment_amount_cents', 'status', 'notes',
        'matched_at', 'matched_by', 'written_off_at', 'written_off_by', 'written_off_reason',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'status' => 'unmatched',
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReconStatus::class,
            'pharmacy_amount_cents' => 'integer',
            'payment_amount_cents' => 'integer',
            'matched_at' => 'datetime',
            'written_off_at' => 'datetime',
            'upstream_synced_at' => 'datetime',
        ];
    }

    /** delta = pharmacy − payment (positive = pharmacy billed more than we collected). */
    protected function deltaCents(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) ($this->pharmacy_amount_cents ?? 0) - (int) ($this->payment_amount_cents ?? 0),
        );
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeStatus(Builder $query, ReconStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
