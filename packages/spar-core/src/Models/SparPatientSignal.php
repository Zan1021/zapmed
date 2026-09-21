<?php

namespace Zapmed\SparCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Zapmed\SparCore\Enums\SparPatientSignalType;

/**
 * SPAR Close-the-Loop, Wave B3 (design §1.2). An append-only patient response
 * signal. Three roles at once: the audit of what the patient chose, the input
 * to the reminder schedule, and the feed for the lost-customer / insight panel.
 *
 * NEVER updated — always insert a new row. Read the LATEST per subject to know
 * the current preference. Carries no PHI in `payload` (the subject is by id),
 * so the row is not encrypted.
 *
 * Package-pure: names no host class; the subject is a soft polymorphic
 * reference (subject_type + subject_id) resolved by the caller, not an Eloquent
 * morphTo (so the package never needs to know host subclass names).
 */
class SparPatientSignal extends Model
{
    protected $table = 'spar_patient_signals';

    protected $fillable = [
        'spar_patient_id',
        'subject_type',
        'subject_id',
        'signal',
        'payload',
        'channel',
    ];

    protected function casts(): array
    {
        return [
            'signal' => SparPatientSignalType::class,
            'payload' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(SparPatient::class, 'spar_patient_id');
    }

    public function type(): SparPatientSignalType
    {
        return $this->signal instanceof SparPatientSignalType
            ? $this->signal
            : SparPatientSignalType::from((string) $this->signal);
    }

    /**
     * Canonical subject_type for a subject instance or class-string.
     *
     * Hosts subclass the package models (e.g. App\Models\SparDispenseRecord
     * extends Zapmed\SparCore\Models\SparDispenseRecord). A signal may be
     * recorded against the host subclass but later looked up from code holding
     * the package base class (the reminder service imports the base). Keying on
     * the raw get_class() would then miss. We normalise to the topmost class
     * WITHIN the SparCore package namespace so record + lookup always agree,
     * regardless of which layer holds the instance.
     *
     * @param  object|class-string  $subject
     */
    public static function canonicalType(object|string $subject): string
    {
        $class = is_object($subject) ? get_class($subject) : $subject;
        $packagePrefix = 'Zapmed\\SparCore\\Models\\';

        // Walk up the inheritance chain to the highest ancestor that still lives
        // in the package Models namespace — that's the shared, stable identity.
        for ($ancestor = $class; $ancestor !== false; $ancestor = get_parent_class($ancestor)) {
            if (str_starts_with($ancestor, $packagePrefix)) {
                $class = $ancestor;
            }
        }

        return $class;
    }

    /**
     * Latest signal for a given subject (by soft polymorphic reference).
     *
     * The subject type is canonicalised so a caller may pass EITHER the host
     * subclass or the package base class and still match what was stored
     * (see {@see canonicalType()}). This keeps the soft-polymorphic lookup
     * robust across the two-host (integrated/standalone) subclassing.
     */
    public function scopeForSubject($query, string $subjectType, int|string $subjectId)
    {
        return $query->where('subject_type', self::canonicalType($subjectType))
            ->where('subject_id', $subjectId);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
