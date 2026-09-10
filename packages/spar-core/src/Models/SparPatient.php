<?php

namespace Zapmed\SparCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Zapmed\PlatformSupport\Concerns\EncryptsSensitiveFields;

class SparPatient extends Model
{
    use EncryptsSensitiveFields;

    protected $table = 'spar_patients';

    protected array $encryptedFields = [
        'profile_code',
        'cellphone',
        'email',
        // NOTE: 'medical_aid_number' removed — no such column exists on
        // spar_patients (the table has medical_aid_name/medical_aid_option).
        // Dead entry cleaned up in Phase 10.2. Add back with a real column if
        // medical aid numbers are ever stored (they would be PHI → encrypt).
    ];

    protected $fillable = [
        'user_id',
        'spar_pharmacy_id',
        'onboarding_pharmacy_id',
        'captured_by_id',
        'captured_at',
        'profile_code',
        'profile_code_hash',
        'dependent_code',
        'dependent_relation',
        'first_name',
        'last_name',
        'cellphone',
        'cellphone_hash',
        'email',
        'onboarding_status',
        'needs_identity_review',
        'identity_review_reason',
        'medical_aid_name',
        'medical_aid_option',
        'consent_status',
        'consent_given_at',
        'consent_revoked_at',
        'consent_channel',
        'is_primary_member',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'consent_given_at' => 'datetime',
            'consent_revoked_at' => 'datetime',
            'captured_at' => 'datetime',
            'is_primary_member' => 'boolean',
            'is_active' => 'boolean',
            'needs_identity_review' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Keep the blind indexes in sync whenever the encrypted identity fields
     * change (national identity, spec FR-1). Runs for app-created patients too,
     * not only imports. Plaintext stays encrypted via EncryptsSensitiveFields.
     */
    protected static function booted(): void
    {
        static::saving(function (self $patient) {
            if ($patient->isDirty('profile_code') || ($patient->profile_code && empty($patient->profile_code_hash))) {
                $patient->profile_code_hash = static::blindIndex($patient->profile_code, 'profile');
            }
            if ($patient->isDirty('cellphone') || ($patient->cellphone && empty($patient->cellphone_hash))) {
                $patient->cellphone_hash = static::blindIndex($patient->cellphone, 'phone');
            }
        });
    }

    /**
     * Deterministic, non-reversible keyed hash of an identity value (spec FR-1,
     * design §2). Same normalised input → same hash → matchable in SQL without
     * decrypting. Returns null for empty input so an absent phone isn't hashed
     * to a constant (which would collide unrelated patients).
     */
    public static function blindIndex(?string $value, string $type = 'profile'): ?string
    {
        $normalised = static::normaliseForIndex($value, $type);
        if ($normalised === '') {
            return null;
        }

        return hash_hmac('sha256', $type . ':' . $normalised, (string) config('spar.blind_index_key'));
    }

    private static function normaliseForIndex(?string $value, string $type): string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return '';
        }

        if ($type === 'phone') {
            // Digits only (drops spaces, +, dashes) so 072 123 4567 == 0721234567.
            $digits = preg_replace('/\D+/', '', $v);

            return $digits ?? '';
        }

        // Profile code: case/space-insensitive.
        return strtoupper(preg_replace('/\s+/', '', $v));
    }

    public function flagIdentityReview(string $reason): void
    {
        $this->update([
            'needs_identity_review' => true,
            'identity_review_reason' => $reason,
        ]);
    }

    public function scopeNeedsIdentityReview($query)
    {
        return $query->where('needs_identity_review', true);
    }

    public function pharmacy()
    {
        return $this->belongsTo(SparPharmacy::class, 'spar_pharmacy_id');
    }

    public function onboardingPharmacy()
    {
        return $this->belongsTo(SparPharmacy::class, 'onboarding_pharmacy_id');
    }

    public function journeys(): HasMany
    {
        return $this->hasMany(SparPrescriptionJourney::class, 'spar_patient_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(SparConsent::class, 'spar_patient_id');
    }

    public function dispenseRecords(): HasMany
    {
        return $this->hasMany(SparDispenseRecord::class, 'spar_patient_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SparOrder::class, 'spar_patient_id');
    }

    // NOTE: user() (belongsTo App\Models\User) is intentionally NOT in the
    // package (AC-3). The integrated ZapMed subclass adds it. Identity/contact
    // resolution goes through the host's SparIdentityProvider; the fallbacks
    // below defer to a `user` relation ONLY if a host subclass defines one.

    public function hasConsented(): bool
    {
        return $this->consent_status === 'opted_in';
    }

    public function optIn(string $channel = 'whatsapp', array $context = []): void
    {
        $this->update([
            'consent_status' => 'opted_in',
            'consent_given_at' => now(),
            'consent_revoked_at' => null,
            'consent_channel' => $channel,
        ]);

        $this->consents()->create([
            'consent_type' => $context['consent_type'] ?? 'chronic_medication',
            'version' => $context['version'] ?? config('spar.consent_version', '1.0'),
            'granted' => true,
            'channel' => $channel,
            'source' => $context['source'] ?? 'patient',
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'granted_at' => now(),
        ]);

        $this->refreshOnboardingStatus();
    }

    public function optOut(array $context = []): void
    {
        $this->update([
            'consent_status' => 'opted_out',
            'consent_revoked_at' => now(),
        ]);

        $this->consents()->create([
            'consent_type' => $context['consent_type'] ?? 'chronic_medication',
            'version' => $context['version'] ?? config('spar.consent_version', '1.0'),
            'granted' => false,
            'channel' => $context['channel'] ?? $this->consent_channel ?? 'web',
            'source' => $context['source'] ?? 'patient',
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'revoked_at' => now(),
        ]);

        $this->refreshOnboardingStatus();
    }

    public function activeJourney(): ?SparPrescriptionJourney
    {
        return $this->journeys()->where('status', 'active')->latest()->first();
    }

    /**
     * Display name: SPAR-owned identity first, then a linked User (integrated,
     * resolved by user_id — no relation dependency), then the profile code.
     */
    public function getDisplayNameAttribute(): string
    {
        $own = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        if ($own !== '') {
            return $own;
        }

        if ($user = $this->linkedUser()) {
            return trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? "Patient #{$this->profile_code}");
        }

        return "Patient #{$this->profile_code}";
    }

    public function primaryPhone(): ?string
    {
        if (!empty($this->cellphone)) {
            return $this->cellphone;
        }

        return $this->linkedUser()?->phone;
    }

    public function primaryEmail(): ?string
    {
        if (!empty($this->email)) {
            return $this->email;
        }

        return $this->linkedUser()?->email;
    }

    /**
     * Resolve the linked host User in integrated mode WITHOUT depending on a
     * `user()` relation existing on the package base model (AC-3) and WITHOUT
     * naming any host class. The host supplies its user model via
     * config('spar.user_model'); standalone leaves it null so this returns null.
     */
    protected function linkedUser()
    {
        if (config('spar.host_mode', 'integrated') !== 'integrated') {
            return null;
        }
        if (empty($this->user_id)) {
            return null;
        }
        $userClass = config('spar.user_model');
        if (!$userClass || !class_exists($userClass)) {
            return null;
        }

        return $userClass::find($this->user_id);
    }

    public function isContactable(): bool
    {
        return !empty($this->primaryPhone()) || !empty($this->primaryEmail());
    }

    public function hasCompleteIdentity(): bool
    {
        return !empty(trim($this->first_name ?? ''))
            && !empty(trim($this->last_name ?? ''))
            && $this->isContactable();
    }

    public function refreshOnboardingStatus(): string
    {
        if ($this->consent_status === 'opted_out') {
            $status = 'opted_out';
        } elseif (!$this->hasCompleteIdentity()) {
            $status = 'awaiting_contact';
        } elseif ($this->hasConsented()) {
            $status = 'active';
        } else {
            $status = 'pending_consent';
        }

        if ($this->onboarding_status !== $status) {
            $this->update(['onboarding_status' => $status]);
        }

        return $status;
    }

    public function isOnboarded(): bool
    {
        return $this->onboarding_status === 'active';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeConsented($query)
    {
        return $query->where('consent_status', 'opted_in');
    }

    public function scopeForPharmacy($query, int $pharmacyId)
    {
        // National identity: a patient "belongs to" a pharmacy if they have a
        // journey OR dispense there — not via the (now informational) home
        // pharmacy column.
        return $query->where(function ($q) use ($pharmacyId) {
            $q->whereHas('journeys', fn ($j) => $j->where('spar_pharmacy_id', $pharmacyId))
              ->orWhereHas('dispenseRecords', fn ($d) => $d->whereHas('journey', fn ($j) => $j->where('spar_pharmacy_id', $pharmacyId)));
        });
    }

    /**
     * Restrict to patients the CURRENT actor may see (spec FR-16), scoped via
     * the bound SparIdentityProvider. Super-admin: all. Group-admin: patients
     * with activity at any pharmacy in their group. Pharmacy actor: patients
     * with activity at their store. National-identity aware — a patient is
     * visible to every store they have a journey/dispense at (not a flat
     * home-pharmacy column). Host-agnostic — no host user class (AC-3/AC-15).
     */
    public function scopeVisibleToCurrentActor($query)
    {
        $identity = app(\Zapmed\SparCore\Contracts\SparIdentityProvider::class);

        if ($identity->isSuperAdmin()) {
            return $query;
        }

        $pharmacyId = $identity->currentPharmacyId();
        if ($pharmacyId !== null) {
            return $this->scopeForPharmacy($query, $pharmacyId);
        }

        $groupId = $identity->currentGroupId();
        if ($groupId !== null) {
            $pharmacyIds = SparPharmacy::where('group_id', $groupId)->pluck('id')->all();

            return $query->where(function ($q) use ($pharmacyIds) {
                $q->whereHas('journeys', fn ($j) => $j->whereIn('spar_pharmacy_id', $pharmacyIds))
                  ->orWhereHas('dispenseRecords', fn ($d) => $d->whereHas('journey', fn ($j) => $j->whereIn('spar_pharmacy_id', $pharmacyIds)));
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function scopeAwaitingContact($query)
    {
        return $query->where('onboarding_status', 'awaiting_contact');
    }

    public function scopePendingConsent($query)
    {
        return $query->where('onboarding_status', 'pending_consent');
    }

    public function scopeOnboarded($query)
    {
        return $query->where('onboarding_status', 'active');
    }
}
