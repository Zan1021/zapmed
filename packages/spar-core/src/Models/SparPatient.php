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
        'profile_code',
        'dependent_code',
        'dependent_relation',
        'first_name',
        'last_name',
        'cellphone',
        'email',
        'onboarding_status',
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
            'is_primary_member' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function pharmacy()
    {
        return $this->belongsTo(SparPharmacy::class, 'spar_pharmacy_id');
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
        return $query->where('spar_pharmacy_id', $pharmacyId);
    }

    /**
     * Restrict to patients the CURRENT actor may see (spec FR-16), scoped via
     * the bound SparIdentityProvider. Super-admin: all. Group-admin: patients at
     * any pharmacy in their group. Pharmacy actor: their store only.
     * Host-agnostic — no host user class referenced (AC-3/AC-15 preserved).
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
            $pharmacyIds = SparPharmacy::where('group_id', $groupId)->pluck('id');

            return $query->whereIn('spar_pharmacy_id', $pharmacyIds);
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
