<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Zapmed\SparCore\Models\SparPharmacy as BaseSparPharmacy;

/**
 * Integrated (ZapMed) SparPharmacy — adds the ZapMed-only `staff` relation.
 */
class SparPharmacy extends BaseSparPharmacy
{
    public function staff(): HasMany
    {
        return $this->hasMany(User::class, 'spar_pharmacy_id');
    }
}
