<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SparDispenseRecord extends Model
{
    protected $fillable = [
        'journey_id',
        'spar_patient_id',
        'dispense_number',
        'status',
        'due_date',
        'reminded_at',
        'collection_requested_at',
        'ready_at',
        'completed_at',
        'fulfillment_type',
        'document_number',
        'sales_value',
        'items',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'reminded_at' => 'datetime',
            'collection_requested_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'sales_value' => 'integer',
            'items' => 'array',
            'metadata' => 'array',
        ];
    }

    public function journey(): BelongsTo
    {
        return $this->belongsTo(SparPrescriptionJourney::class, 'journey_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(SparPatient::class, 'spar_patient_id');
    }

    public function order(): HasOne
    {
        return $this->hasOne(SparOrder::class, 'dispense_record_id');
    }

    /**
     * Check if reminder is due (7 days before due_date).
     */
    public function isReminderDue(): bool
    {
        return $this->status === 'upcoming' &&
            $this->due_date->subDays(7)->isPast() &&
            is_null($this->reminded_at);
    }

    /**
     * Check if this dispense is overdue.
     */
    public function isOverdue(): bool
    {
        return in_array($this->status, ['upcoming', 'reminded']) &&
            $this->due_date->isPast();
    }

    /**
     * Mark as reminded.
     */
    public function markReminded(): void
    {
        $this->update([
            'status' => 'reminded',
            'reminded_at' => now(),
        ]);
    }

    /**
     * Mark as collected.
     */
    public function markCollected(): void
    {
        $this->update([
            'status' => 'collected',
            'fulfillment_type' => 'collection',
            'completed_at' => now(),
        ]);

        $this->journey->recordDispense();
    }

    /**
     * Mark as delivered.
     */
    public function markDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'fulfillment_type' => 'delivery',
            'completed_at' => now(),
        ]);

        $this->journey->recordDispense();
    }

    /**
     * Get formatted sales value.
     */
    public function getFormattedSalesValueAttribute(): string
    {
        if (!$this->sales_value) return 'N/A';
        return 'R' . number_format($this->sales_value / 100, 2);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming');
    }

    public function scopeOverdue($query)
    {
        return $query->whereIn('status', ['upcoming', 'reminded'])
            ->where('due_date', '<', now());
    }

    public function scopeDueForReminder($query)
    {
        return $query->where('status', 'upcoming')
            ->whereNull('reminded_at')
            ->where('due_date', '<=', now()->addDays(7));
    }
}
