<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'role',
        'password',
        'id_number',
        'date_of_birth',
        'gender',
        'address',
        'city',
        'province',
        'postal_code',
        'two_factor_enabled',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code',
        'id_number',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'date_of_birth' => 'date',
            'two_factor_enabled' => 'boolean',
            'two_factor_expires_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get the patient profile.
     */
    public function patientProfile(): HasOne
    {
        return $this->hasOne(PatientProfile::class);
    }

    /**
     * Get the doctor profile.
     */
    public function doctorProfile(): HasOne
    {
        return $this->hasOne(DoctorProfile::class);
    }

    /**
     * Get the user's consent records.
     */
    public function consentRecords(): HasMany
    {
        return $this->hasMany(ConsentRecord::class);
    }

    /**
     * Check if patient has completed onboarding.
     */
    public function hasCompletedOnboarding(): bool
    {
        if (!$this->isPatient()) {
            return true;
        }

        return $this->patientProfile?->onboarding_complete ?? false;
    }

    /**
     * Get the user's full name.
     */
    public function getNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Check if user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Check if user is a doctor.
     */
    public function isDoctor(): bool
    {
        return $this->role === UserRole::Doctor;
    }

    /**
     * Check if user is a patient.
     */
    public function isPatient(): bool
    {
        return $this->role === UserRole::Patient;
    }

    /**
     * Scope to filter by role.
     */
    public function scopeRole($query, UserRole $role)
    {
        return $query->where('role', $role->value);
    }

    /**
     * Scope to filter active users only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Generate and set a 2FA code.
     */
    public function generateTwoFactorCode(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->update([
            'two_factor_code' => $code,
            'two_factor_expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }

    /**
     * Clear the 2FA code.
     */
    public function clearTwoFactorCode(): void
    {
        $this->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);
    }

    /**
     * Record a login event.
     */
    public function recordLogin(string $ip): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }
}
