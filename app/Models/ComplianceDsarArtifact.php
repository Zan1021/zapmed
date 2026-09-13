<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An output produced for a DSAR (export.json / export.csv / erasure_certificate). checksum_sha256 is
 * the SHA-256 of the artifact bytes, for integrity verification when handed to the patient.
 */
class ComplianceDsarArtifact extends Model
{
    protected $table = 'compliance_dsar_artifacts';

    protected $fillable = [
        'dsar_id', 'kind', 'location', 'checksum_sha256', 'expires_at', 'size_bytes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    public function dsar(): BelongsTo
    {
        return $this->belongsTo(ComplianceDsar::class, 'dsar_id');
    }
}
