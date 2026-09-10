<?php

namespace Zapmed\SparCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A per-group promo banner shown on the patient mobi tracker (after consent).
 * Managed by group admins; auto-converted to WebP on upload.
 */
class SparBanner extends Model
{
    protected $table = 'spar_banners';

    protected $fillable = [
        'group_id',
        'title',
        'image_path',
        'link_url',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
        'impressions',
        'clicks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SparPharmacyGroup::class, 'group_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForGroup($query, int $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * Active AND within the optional scheduling window (starts_at/ends_at).
     */
    public function scopeLiveNow($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** Public URL for the stored WebP image. */
    public function getImageUrlAttribute(): string
    {
        return Storage::disk(config('spar.banners.disk', 'public'))->url($this->image_path);
    }

    public function getClickThroughRateAttribute(): float
    {
        return $this->impressions > 0 ? round($this->clicks / $this->impressions * 100, 1) : 0.0;
    }
}
