<?php

namespace App\Models;

use App\Enums\DsarKind;
use App\Enums\DsarStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A data-subject access request (POPIA s23-25). dsar_number is a human-readable reference; due_at is
 * the 30-day POPIA outer bound set at receipt.
 */
class ComplianceDsar extends Model
{
    protected $table = 'compliance_dsars';

    protected $fillable = [
        'dsar_number', 'tenant_id', 'principal_id', 'kind', 'status',
        'request_detail', 'handler_notes', 'due_at', 'received_at',
        'acknowledged_at', 'completed_at', 'rejected_at', 'rejected_reason', 'cancelled_at',
        'retention_schedule_id', 'verification_evidence', 'metadata',
        'created_by', 'updated_by',
    ];

    protected $attributes = [
        'tenant_id' => 'zapmed',
        'status' => 'received',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DsarKind::class,
            'status' => DsarStatus::class,
            'due_at' => 'datetime',
            'received_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ComplianceDsar $dsar) {
            if (empty($dsar->dsar_number)) {
                $dsar->dsar_number = static::generateNumber();
            }
        });
    }

    public static function generateNumber(): string
    {
        do {
            $number = 'DSAR-' . now()->year . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('dsar_number', $number)->exists());

        return $number;
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(ComplianceDsarArtifact::class, 'dsar_id');
    }

    /** Is this request past its 30-day SLA and still open? */
    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_at !== null && $this->due_at->isPast();
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [DsarStatus::Received->value, DsarStatus::Acknowledged->value, DsarStatus::InProgress->value]);
    }
}
