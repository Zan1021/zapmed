<?php

namespace App\Models;

use App\Enums\RevenueKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable revenue ledger entry. Amounts in cents; NEGATIVE for refunds/chargebacks/discounts.
 * Reports SUM over these rows sliced by kind / service_line / category.
 */
class FinanceRevenueEntry extends Model
{
    protected $table = 'finance_revenue_entries';

    protected $fillable = [
        'tenant_id', 'kind', 'effective_date', 'amount_cents', 'currency',
        'revenue_category', 'service_line',
        'payment_id', 'order_id', 'subscription_id', 'principal_id',
        'notes', 'metadata',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
        'created_by',
    ];

    protected $attributes = [
        'tenant_id' => 'zapmed',
        'currency' => 'ZAR',
        'revenue_category' => 'other',
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'kind' => RevenueKind::class,
            'effective_date' => 'date',
            'amount_cents' => 'integer',
            'metadata' => 'array',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeOfKind(Builder $query, RevenueKind $kind): Builder
    {
        return $query->where('kind', $kind->value);
    }

    /** @param  Builder<self>  $query */
    public function scopeBetween(Builder $query, \DateTimeInterface $since, \DateTimeInterface $until): Builder
    {
        return $query->whereBetween('effective_date', [$since, $until]);
    }
}
