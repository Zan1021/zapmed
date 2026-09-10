<?php

namespace Zapmed\SparCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparImportBatch extends Model
{
    protected $table = 'spar_import_batches';

    protected $fillable = [
        'filename',
        'source',
        'status',
        'records_total',
        'records_processed',
        'records_created',
        'records_updated',
        'records_skipped',
        'records_failed',
        'errors',
        'summary',
        'imported_by',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // importedBy() (belongsTo App\Models\User) intentionally omitted (AC-3).
    // imported_by remains a plain nullable column; the integrated host subclass
    // may add the relation.

    public function logs(): HasMany
    {
        return $this->hasMany(SparImportLog::class, 'batch_id');
    }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing', 'started_at' => now()]);
    }

    public function markCompleted(array $summary = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'summary' => $summary,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'errors' => array_merge($this->errors ?? [], [$error]),
        ]);
    }

    public function incrementCounter(string $field): void
    {
        $this->increment($field);
        $this->increment('records_processed');
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->records_processed === 0) return 0;
        return round((($this->records_created + $this->records_updated) / $this->records_processed) * 100, 1);
    }
}
