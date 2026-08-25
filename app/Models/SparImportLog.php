<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SparImportLog extends Model
{
    protected $fillable = [
        'batch_id',
        'row_number',
        'profile_code',
        'store_name',
        'status',
        'action',
        'error_message',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SparImportBatch::class, 'batch_id');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeCreated($query)
    {
        return $query->where('status', 'created');
    }
}
