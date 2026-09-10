<?php

namespace Zapmed\SparCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A pharmacy group — the tier above individual pharmacies (spec FR-14).
 * e.g. "SPAR Western Cape". Every SparPharmacy belongs to exactly one group.
 * Group admins are scoped to their group; ZapMed super-admin sees all groups.
 */
class SparPharmacyGroup extends Model
{
    protected $table = 'spar_pharmacy_groups';

    protected $fillable = [
        'name',
        'slug',
        'region',
        'logo_path',
        'contact_name',
        'contact_email',
        'contact_phone',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Auto-derive a unique slug from the name when not supplied.
        static::creating(function (self $group) {
            if (empty($group->slug)) {
                $group->slug = static::uniqueSlug($group->name);
            }
        });
    }

    public function pharmacies(): HasMany
    {
        return $this->hasMany(SparPharmacy::class, 'group_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'group';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
