<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'hpcsa_number',
        'speciality',
        'qualification',
        'university',
        'year_qualified',
        'bio',
        'consultation_fee',
        'followup_fee',
        'consultation_duration',
        'available_days',
        'available_from',
        'available_to',
        'signature_path',
        'accepts_new_patients',
        'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'consultation_fee' => 'integer',
            'followup_fee' => 'integer',
            'consultation_duration' => 'integer',
            'available_days' => 'array',
            'accepts_new_patients' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get consultation fee formatted in Rands.
     */
    public function getFormattedFeeAttribute(): string
    {
        return 'R' . number_format($this->consultation_fee / 100, 2);
    }

    /**
     * Get follow-up fee formatted in Rands.
     */
    public function getFormattedFollowupFeeAttribute(): string
    {
        return 'R' . number_format($this->followup_fee / 100, 2);
    }

    /**
     * Check if doctor is available on a given day of the week.
     */
    public function isAvailableOn(string $day): bool
    {
        return in_array(strtolower($day), $this->available_days ?? []);
    }

    /**
     * Get available time slots for a given date.
     */
    public function getTimeSlotsForDate(string $date): array
    {
        $dayOfWeek = strtolower(date('D', strtotime($date)));

        if (!$this->isAvailableOn($dayOfWeek)) {
            return [];
        }

        $slots = [];
        $start = strtotime($this->available_from);
        $end = strtotime($this->available_to);
        $duration = $this->consultation_duration * 60; // convert to seconds

        while ($start + $duration <= $end) {
            $slots[] = date('H:i', $start);
            $start += $duration;
        }

        return $slots;
    }

    /**
     * Scope to only verified doctors.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope to doctors accepting new patients.
     */
    public function scopeAcceptingPatients($query)
    {
        return $query->where('accepts_new_patients', true);
    }
}
