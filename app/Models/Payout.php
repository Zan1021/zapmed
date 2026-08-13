<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    protected $fillable = [
        'payment_id', 'type', 'reference', 'total_amount',
        'doctor_id', 'doctor_amount', 'doctor_rate',
        'pharmacy_id', 'pharmacy_amount', 'pharmacy_rate',
        'partner_id', 'partner_amount', 'partner_rate',
        'platform_amount', 'platform_rate', 'delivery_fee',
        'doctor_payout_status', 'doctor_paid_at',
        'pharmacy_payout_status', 'pharmacy_paid_at',
        'partner_payout_status', 'partner_paid_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'doctor_amount' => 'integer',
            'pharmacy_amount' => 'integer',
            'partner_amount' => 'integer',
            'platform_amount' => 'integer',
            'delivery_fee' => 'integer',
            'doctor_paid_at' => 'datetime',
            'pharmacy_paid_at' => 'datetime',
            'partner_paid_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function doctor(): BelongsTo { return $this->belongsTo(User::class, 'doctor_id'); }
    public function pharmacy(): BelongsTo { return $this->belongsTo(Pharmacy::class); }
    public function partner(): BelongsTo { return $this->belongsTo(Partner::class); }
}
