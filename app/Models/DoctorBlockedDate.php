<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorBlockedDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_profile_id',
        'blocked_date',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'blocked_date' => 'date',
        ];
    }

    public function doctorProfile(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class);
    }
}
