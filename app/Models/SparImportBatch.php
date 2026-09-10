<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Zapmed\SparCore\Models\SparImportBatch as BaseSparImportBatch;

/**
 * Integrated (ZapMed) SparImportBatch — adds the ZapMed-only `importedBy`
 * relation.
 */
class SparImportBatch extends BaseSparImportBatch
{
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
