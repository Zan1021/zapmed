<?php

namespace App\Models;

use App\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coaching cross-sell offer — a coach pitches another service line (parity with coaching_offer).
 */
class CoachingOffer extends Model
{
    protected $fillable = [
        'patient_id', 'coach_id', 'service_line', 'catalog_item_id', 'status', 'notes',
        'expires_at', 'accepted_at', 'declined_at', 'declined_reason', 'created_by',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
    ];

    protected $attributes = [
        'status' => 'open',
        'upstream_source' => 'contro',
    ];

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'upstream_synced_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', OfferStatus::Open->value);
    }
}
