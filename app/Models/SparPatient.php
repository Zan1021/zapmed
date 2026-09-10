<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Zapmed\SparCore\Models\SparPatient as BaseSparPatient;

/**
 * Integrated (ZapMed) SparPatient — the shared domain model lives in
 * `zapmed/spar-core`; this subclass adds the ZapMed-only `user` relation so
 * existing `App\Models\SparPatient` references keep working unchanged.
 * Resolves to the same `spar_patients` table.
 */
class SparPatient extends BaseSparPatient
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
