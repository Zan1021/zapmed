<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A doctor's request for blood tests, the results that come back and the doctor's review. */
class BloodTestRequest extends Model
{
    public const REQUESTED = 'requested';
    public const RESULTS_RECEIVED = 'results_received';
    public const REVIEWED = 'reviewed';
    public const CANCELLED = 'cancelled';

    public const OPEN = [self::REQUESTED, self::RESULTS_RECEIVED];

    protected $fillable = [
        'patient_id', 'order_id', 'requested_by', 'tests', 'free_text', 'lab_form', 'lab_form_path', 'lab_form_name',
        'status', 'review_note', 'cancel_reason', 'order_status_before', 'results_at', 'reviewed_at', 'cancelled_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'tests' => 'array',
            'free_text' => 'encrypted',
            'review_note' => 'encrypted',
            'results_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(BloodTestResult::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function scopeOpen($q)
    {
        return $q->whereIn('status', self::OPEN);
    }

    /** Readable names of the ticked tests. */
    public function testNames(): array
    {
        $catalogue = config('doctor.blood_tests.catalogue');

        return collect($this->tests ?? [])->map(fn ($k) => $catalogue[$k] ?? $k)->all();
    }
}
