<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    protected $table = 'partner_referrals';

    protected $fillable = [
        'partner_id',
        'patient_id',
        'landing_url',
        'source_url',
        'ip_address',
        'status',
        'registered_at',
        'converted_at',
        'cookie_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'converted_at' => 'datetime',
            'cookie_expires_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
