<?php

namespace Zapmed\SparCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * POPIA consent evidence for a SPAR patient (spec FR-7). SPAR-owned.
 */
class SparConsent extends Model
{
    protected $table = 'spar_consents';

    protected $fillable = [
        'spar_patient_id',
        'consent_type',
        'version',
        'granted',
        'channel',
        'source',
        'ip_address',
        'user_agent',
        'granted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(SparPatient::class, 'spar_patient_id');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('consent_type', $type);
    }

    public function scopeGranted($query)
    {
        return $query->where('granted', true)->whereNull('revoked_at');
    }
}
