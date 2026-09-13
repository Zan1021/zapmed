<?php

namespace App\Models;

use App\Services\Orders\OrderStatusMachine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Order aggregate root — the lifecycle spine (specs/contro-rebuild §4).
 * Money in minor units (cents), ZAR. Status transitions are guarded by OrderStatusMachine and
 * recorded immutably in order_status_history.
 */
class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference', 'contro_order_number', 'patient_id', 'doctor_id', 'status',
        'service_fee_minor', 'medication_fee_minor', 'total_minor', 'currency',
        'is_subscription', 'service_category', 'payment_type',
        'next_repeat_date', 'pharmacy_script_ref',
        'cancellation_reason', 'cancelled_at', 'paused_at',
        'delivery_address', 'delivery_city', 'delivery_province', 'delivery_postal_code',
        'delivery_phone', 'delivery_instructions', 'ordered_at', 'metadata',
        'upstream_id', 'upstream_source', 'upstream_synced_at',
        'source_type', 'source_ref',
    ];

    protected $attributes = [
        'status' => 'PendingPayment',
        'upstream_source' => 'contro',
        'currency' => 'ZAR',
    ];

    protected function casts(): array
    {
        return [
            'is_subscription' => 'boolean',
            'service_fee_minor' => 'integer',
            'medication_fee_minor' => 'integer',
            'total_minor' => 'integer',
            'next_repeat_date' => 'date',
            'cancelled_at' => 'datetime',
            'paused_at' => 'datetime',
            'ordered_at' => 'datetime',
            'upstream_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->reference)) {
                $order->reference = static::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        $year = now()->year;
        do {
            $reference = sprintf('ZM-%d-%06d', $year, random_int(1, 999999));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('occurred_at');
    }

    /**
     * Payments raised against this order (payments.order_id). Read surface for the ops board.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Prescriptions linked by the shared pharmacy script reference. Contro does not FK a prescription
     * to an order; the common thread is orders.pharmacy_script_ref == prescriptions.pharmacy_script_ref.
     * Returns an empty relation (never all rows) when this order has no script ref.
     */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class, 'pharmacy_script_ref', 'pharmacy_script_ref')
            ->when(blank($this->pharmacy_script_ref), fn ($q) => $q->whereRaw('1 = 0'));
    }

    /**
     * Guarded status transition. Validates against the seeded state machine, applies it, and records
     * an immutable history row. Throws on a disallowed transition — callers that import Contro data
     * should check isTransitionAllowed() first and quarantine anomalies instead of calling this.
     *
     * @param  array<string,mixed>  $context  notes/payload/triggered_by for the history row
     */
    public function transitionTo(string $to, string $trigger, array $context = []): OrderStatusHistory
    {
        $from = $this->status;

        if (!OrderStatusMachine::isValidStatus($to)) {
            throw new RuntimeException("Unknown order status: {$to}");
        }
        if (!OrderStatusMachine::isTransitionAllowed($from, $to, $trigger)) {
            throw new RuntimeException("Illegal transition {$from} -> {$to} via {$trigger}");
        }

        $this->update(['status' => $to]);

        return $this->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $to,
            'trigger_type' => $trigger,
            'triggered_by' => $context['triggered_by'] ?? null,
            'notes' => $context['notes'] ?? null,
            'payload' => $context['payload'] ?? null,
            'occurred_at' => $context['occurred_at'] ?? now(),
        ]);
    }

    /**
     * Record a status step WITHOUT validation — for importing Contro history verbatim, where the
     * step is already known (possibly against current rules). Writes an immutable history row and
     * sets current status. Anomaly flagging is the reconciler's job (quarantine), not this method's.
     *
     * @param  array<string,mixed>  $context
     */
    public function recordImportedStatus(?string $from, string $to, string $trigger, array $context = []): OrderStatusHistory
    {
        $this->forceFill(['status' => $to])->save();

        return $this->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $to,
            'trigger_type' => $trigger,
            'triggered_by' => $context['triggered_by'] ?? null,
            'notes' => $context['notes'] ?? null,
            'payload' => $context['payload'] ?? null,
            'occurred_at' => $context['occurred_at'] ?? now(),
            'upstream_id' => $context['upstream_id'] ?? null,
        ]);
    }
}
