<?php

namespace Zapmed\SparCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SparOrder extends Model
{
    protected $table = 'spar_orders';

    protected $fillable = [
        'reference',
        'spar_patient_id',
        'spar_pharmacy_id',
        'dispense_record_id',
        'type',
        'status',
        'delivery_address',
        'delivery_city',
        'delivery_postal_code',
        'delivery_phone',
        'delivery_date',
        'notes',
        'prepared_at',
        'ready_at',
        'completed_at',
        'cancelled_at',
        'cancelled_reason',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'prepared_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SparOrder $order) {
            if (empty($order->reference)) {
                $order->reference = static::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'SP-' . strtoupper(Str::random(8));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(SparPatient::class, 'spar_patient_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(SparPharmacy::class, 'spar_pharmacy_id');
    }

    public function dispenseRecord(): BelongsTo
    {
        return $this->belongsTo(SparDispenseRecord::class, 'dispense_record_id');
    }

    public function markPreparing(): void
    {
        $this->update(['status' => 'preparing', 'prepared_at' => now()]);
    }

    public function markReady(): void
    {
        $this->update(['status' => 'ready', 'ready_at' => now()]);
    }

    public function markCompleted(): void
    {
        $this->update(['status' => 'completed', 'completed_at' => now()]);

        if ($this->dispenseRecord) {
            if ($this->type === 'collection') {
                $this->dispenseRecord->markCollected();
            } else {
                $this->dispenseRecord->markDelivered();
            }
        }
    }

    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => $reason,
        ]);
    }

    public function isCollection(): bool
    {
        return $this->type === 'collection';
    }

    public function isDelivery(): bool
    {
        return $this->type === 'delivery';
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['requested', 'preparing']);
    }

    public function scopeForPharmacy($query, int $pharmacyId)
    {
        return $query->where('spar_pharmacy_id', $pharmacyId);
    }
}
