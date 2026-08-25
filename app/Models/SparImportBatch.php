<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparImportBatch extends Model
{
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

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SparImportLog::class, 'batch_id');
    }

    /**
     * Mark batch as processing.
     */
    public function markProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark batch as completed.
     */
    public function markCompleted(array $summary = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'summary' => $summary,
        ]);
    }

    /**
     * Mark batch as failed.
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'errors' => array_merge($this->errors ?? [], [$error]),
        ]);
    }

    /**
     * Increment a counter.
     */
    public function incrementCounter(string $field): void
    {
        $this->increment($field);
        $this->increment('records_processed');
    }

    /**
     * Get success rate as percentage.
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->records_processed === 0) return 0;
        return round((($this->records_created + $this->records_updated) / $this->records_processed) * 100, 1);
    }
}
