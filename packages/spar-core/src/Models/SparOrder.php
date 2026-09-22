<?php

namespace Zapmed\SparCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Zapmed\SparCore\Concerns\ResolvesActionable;
use Zapmed\SparCore\Contracts\SparActionable;

class SparOrder extends Model implements SparActionable
{
    use ResolvesActionable;

    protected $table = 'spar_orders';

    /** Patient-facing fulfilment modes (FR-C1 dropdown). */
    public const MODE_COLLECT_PAY_NOW = 'collect_pay_now';
    public const MODE_DELIVER_PAY_NOW = 'deliver_pay_now';
    public const MODE_COLLECT_PAY_STORE = 'collect_pay_store';

    /** @var array<string, array{type: string, payment_status: string, label: string}> */
    public const MODES = [
        self::MODE_COLLECT_PAY_NOW => ['type' => 'collection', 'payment_status' => 'paid', 'label' => 'Collect & pay now'],
        self::MODE_DELIVER_PAY_NOW => ['type' => 'delivery', 'payment_status' => 'paid', 'label' => 'Deliver & pay now'],
        self::MODE_COLLECT_PAY_STORE => ['type' => 'collection', 'payment_status' => 'pay_at_store', 'label' => 'Collect & pay at store'],
    ];

    protected $fillable = [
        'reference',
        'spar_patient_id',
        'spar_pharmacy_id',
        'dispense_record_id',
        'type',
        'fulfilment_mode',
        'status',
        'payment_status',
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

    /**
     * Line items ("basket" lines) — coach-suggested extras (Health Coach v1).
     */
    public function items(): HasMany
    {
        return $this->hasMany(SparOrderItem::class, 'spar_order_id');
    }

    public function basketTotalCents(): int
    {
        return (int) $this->items->sum(fn (SparOrderItem $item) => $item->lineTotalCents());
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

    /**
     * Resolve a patient-facing fulfilment mode into the concrete attributes an
     * order needs (channel + payment intent). Unknown modes fall back to a
     * plain collection so a bad input can never create an inconsistent order.
     *
     * @return array{type: string, fulfilment_mode: string, payment_status: string}
     */
    public static function attributesForMode(string $mode): array
    {
        $config = self::MODES[$mode] ?? self::MODES[self::MODE_COLLECT_PAY_STORE];

        return [
            'type' => $config['type'],
            'fulfilment_mode' => array_key_exists($mode, self::MODES) ? $mode : self::MODE_COLLECT_PAY_STORE,
            'payment_status' => $config['payment_status'],
        ];
    }

    public function modeLabel(): string
    {
        return self::MODES[$this->fulfilment_mode]['label'] ?? ucfirst((string) $this->type);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['requested', 'preparing']);
    }

    public function scopeForPharmacy($query, int $pharmacyId)
    {
        return $query->where('spar_pharmacy_id', $pharmacyId);
    }

    /**
     * Fail-closed actor scoping (mirrors SparPatient). Orders carry
     * spar_pharmacy_id directly so scoping is a simple column filter:
     *   super-admin  → all orders
     *   pharmacy      → own pharmacy's orders
     *   group-admin   → orders across the group's pharmacies
     *   otherwise     → nothing (unauthenticated / out of scope)
     */
    public function scopeVisibleToCurrentActor($query)
    {
        $identity = app(\Zapmed\SparCore\Contracts\SparIdentityProvider::class);

        if ($identity->isSuperAdmin()) {
            return $query;
        }

        $pharmacyId = $identity->currentPharmacyId();
        if ($pharmacyId !== null) {
            return $query->where('spar_pharmacy_id', $pharmacyId);
        }

        $groupId = $identity->currentGroupId();
        if ($groupId !== null) {
            $pharmacyIds = SparPharmacy::where('group_id', $groupId)->pluck('id')->all();

            return $query->whereIn('spar_pharmacy_id', $pharmacyIds);
        }

        return $query->whereRaw('1 = 0');
    }

    public function scopeActiveQueue($query)
    {
        return $query->whereIn('status', ['requested', 'preparing', 'ready']);
    }
}
