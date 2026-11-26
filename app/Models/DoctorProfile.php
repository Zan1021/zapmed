<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Carbon\Carbon;

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

    public function availabilities(): HasMany
    {
        return $this->hasMany(DoctorAvailability::class);
    }

    public function blockedDates(): HasMany
    {
        return $this->hasMany(DoctorBlockedDate::class);
    }

    /**
     * Get available 15-minute slot times for a given date.
     * Checks day_of_week match, excludes blocked dates, excludes already-booked appointments.
     */
    public function getAvailableSlotsForDate(string $date): array
    {
        $dayOfWeek = (int) Carbon::parse($date)->dayOfWeek; // 0=Sunday

        // Check if date is blocked. Uses whereDate() because blocked_date is cast
        // to 'date' and stored with a 00:00:00 time component, so an exact string
        // match on 'Y-m-d' would never match.
        if ($this->blockedDates()->whereDate('blocked_date', $date)->exists()) {
            return [];
        }

        // Get active availability slots for this day of week
        $slots = $this->availabilities()
            ->active()
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get();

        if ($slots->isEmpty()) {
            return [];
        }

        // Get already-booked times for this doctor on this date. whereDate() for the
        // same date-cast reason above — otherwise booked slots leak back into
        // availability and the doctor can be double-booked.
        $booked = Appointment::where('doctor_id', $this->user_id)
            ->whereDate('appointment_date', $date)
            ->whereNotIn('status', Appointment::SLOT_FREE_STATUSES)
            ->get(['start_time', 'end_time']);

        // A booking blocks every 15-minute slot it overlaps (a 45-minute consultation holds three
        // slots), not only the slot it starts in.
        $isBooked = function (string $time) use ($booked): bool {
            foreach ($booked as $b) {
                $start = substr((string) $b->start_time, 0, 5);
                $end = $b->end_time ? substr((string) $b->end_time, 0, 5) : null;
                if ($time === $start || ($end && $end > $start && $time > $start && $time < $end)) {
                    return true;
                }
            }
            return false;
        };

        // Each slot row IS a 15-min slot (start_time to end_time)
        $available = [];
        foreach ($slots as $slot) {
            $time = substr($slot->start_time, 0, 5);
            if (! $isBooked($time)) {
                $available[] = $time;
            }
        }

        sort($available);

        return $available;
    }

    /**
     * Start times that can hold a consultation of $minutes: every 15-minute slot it needs must be free.
     * (Weight loss is a 45-minute first consultation, so 09:00 is only offered when 09:00, 09:15 and
     * 09:30 are all free.)
     *
     * @return array<int,string>
     */
    public function availableStartTimes(string $date, int $minutes = 15): array
    {
        $free = $this->getAvailableSlotsForDate($date);
        $set = array_flip($free);
        $steps = max(1, (int) ceil($minutes / 15));
        $out = [];
        foreach ($free as $time) {
            $ok = true;
            for ($k = 1; $k < $steps; $k++) {
                if (! isset($set[date('H:i', strtotime($time) + $k * 900)])) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $out[] = $time;
            }
        }
        return $out;
    }

    /**
     * Check if doctor is available on a given date (has slots and not blocked).
     */
    public function isAvailableOnDate(string $date): bool
    {
        $dayOfWeek = (int) Carbon::parse($date)->dayOfWeek;

        if ($this->blockedDates()->whereDate('blocked_date', $date)->exists()) {
            return false;
        }

        return $this->availabilities()
            ->active()
            ->where('day_of_week', $dayOfWeek)
            ->exists();
    }

    /**
     * Get all doctors available for a specific date/time slot.
     */
    public static function getAvailableDoctorsForSlot(string $date, string $time): Collection
    {
        $dayOfWeek = (int) Carbon::parse($date)->dayOfWeek;

        return static::verified()
            ->acceptingPatients()
            ->whereHas('availabilities', function ($query) use ($dayOfWeek, $time) {
                $query->active()
                    ->where('day_of_week', $dayOfWeek)
                    ->where('start_time', $time);
            })
            ->whereDoesntHave('blockedDates', function ($query) use ($date) {
                $query->whereDate('blocked_date', $date);
            })
            ->get()
            ->filter(function ($profile) use ($date, $time) {
                // Exclude doctors already booked for this slot, including a longer booking that
                // started earlier and still covers it (same rule the slot list uses).
                return in_array(substr($time, 0, 5), $profile->getAvailableSlotsForDate($date), true);
            });
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

    /**
     * Get the doctor's reviews (via testimonials).
     */
    public function reviews()
    {
        return Testimonial::where('doctor_id', $this->user_id);
    }

    /**
     * Recalculate and cache the doctor's average rating.
     */
    public function recalculateRating(): void
    {
        $stats = Testimonial::where('doctor_id', $this->user_id)
            ->where('is_approved', true)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total')
            ->first();

        $this->update([
            'average_rating' => round($stats->avg_rating ?? 0, 2),
            'total_reviews' => $stats->total ?? 0,
        ]);
    }

    /**
     * Get formatted rating display (e.g. "4.8").
     */
    public function getFormattedRatingAttribute(): string
    {
        return number_format($this->average_rating, 1);
    }

    /**
     * Get star display as array (for rendering full/half/empty stars).
     */
    public function getStarsAttribute(): array
    {
        $rating = $this->average_rating;
        $stars = [];

        for ($i = 1; $i <= 5; $i++) {
            if ($rating >= $i) {
                $stars[] = 'full';
            } elseif ($rating >= $i - 0.5) {
                $stars[] = 'half';
            } else {
                $stars[] = 'empty';
            }
        }

        return $stars;
    }
}
