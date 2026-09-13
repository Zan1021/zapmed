<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Alert instance — one row per fired alert (parity with alerts_alert + the reference AlertRepo).
 *
 * Idempotency + re-raise live here (raise()): at most one ACTIVE (open/acknowledged/snoozed) alert per
 * dedupe_key. Raising again while active is a no-op (returns the existing row). If the only prior alert
 * for a dedupe_key is closed (resolved/auto_closed), raising REOPENS a fresh alert and bumps
 * re_raise_count — mirroring "when status returns to open for the same dedupe_key, that's a re-raise".
 */
class Alert extends Model
{
    protected $table = 'alerts_alerts';

    protected $fillable = [
        'definition_code', 'severity', 'status', 'title', 'detail',
        'subject_type', 'subject_id', 'assigned_to', 'dedupe_key', 'metadata',
        'raised_at', 'acknowledged_at', 'acknowledged_by',
        'resolved_at', 'resolved_by', 'snoozed_until',
        'last_re_raised_at', 're_raise_count',
    ];

    protected $attributes = [
        'status' => 'open',
        're_raise_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'status' => AlertStatus::class,
            'metadata' => 'array',
            'raised_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'last_re_raised_at' => 'datetime',
            're_raise_count' => 'integer',
        ];
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AlertComment::class)->latest();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(AlertDefinition::class, 'definition_code', 'code');
    }

    // ---- scopes --------------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->whereIn('status', AlertStatus::activeValues());
    }

    // ---- lifecycle (parity with AlertRepo) -----------------------------------------------------

    /**
     * Idempotent raise. Returns the existing active alert for the dedupe_key if one exists (no-op),
     * otherwise creates one — reopening (re-raise) if the previous alert for this key was closed.
     *
     * @param  array<string,mixed>  $attributes
     */
    public static function raise(array $attributes): self
    {
        $dedupeKey = $attributes['dedupe_key'];

        return DB::transaction(function () use ($attributes, $dedupeKey) {
            $active = static::where('dedupe_key', $dedupeKey)
                ->whereIn('status', AlertStatus::activeValues())
                ->lockForUpdate()
                ->first();

            if ($active) {
                return $active; // already live — idempotent no-op
            }

            // Was there a previously-closed alert for this key? If so this is a re-raise.
            $priorClosed = static::where('dedupe_key', $dedupeKey)
                ->whereIn('status', [AlertStatus::Resolved->value, AlertStatus::AutoClosed->value])
                ->orderByDesc('id')
                ->first();

            return static::create([
                'definition_code' => $attributes['definition_code'],
                'severity' => $attributes['severity'] instanceof AlertSeverity
                    ? $attributes['severity']->value : $attributes['severity'],
                'status' => AlertStatus::Open->value,
                'title' => $attributes['title'],
                'detail' => $attributes['detail'] ?? null,
                'subject_type' => $attributes['subject_type'],
                'subject_id' => (string) $attributes['subject_id'],
                'dedupe_key' => $dedupeKey,
                'metadata' => $attributes['metadata'] ?? null,
                'raised_at' => now(),
                'last_re_raised_at' => $priorClosed ? now() : null,
                're_raise_count' => $priorClosed ? ($priorClosed->re_raise_count + 1) : 0,
            ]);
        });
    }

    public function acknowledge(?int $userId = null): void
    {
        if ($this->status === AlertStatus::Open) {
            $this->update([
                'status' => AlertStatus::Acknowledged->value,
                'acknowledged_at' => now(),
                'acknowledged_by' => $userId,
            ]);
        }
    }

    public function resolve(?int $userId = null): void
    {
        $this->update([
            'status' => AlertStatus::Resolved->value,
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ]);
    }

    public function snooze(\DateTimeInterface $until): void
    {
        $this->update([
            'status' => AlertStatus::Snoozed->value,
            'snoozed_until' => $until,
        ]);
    }

    /** Scanner-driven auto-close when the underlying condition clears. */
    public function autoClose(): void
    {
        $this->update([
            'status' => AlertStatus::AutoClosed->value,
            'resolved_at' => now(),
        ]);
    }
}
