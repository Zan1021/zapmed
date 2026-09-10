<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Standalone staff account (spec design §5.2). Replaces the ZapMed `User` +
 * `UserRole::PharmacyStaff` for staff auth where there is NO telehealth User
 * table. Each staff member is scoped to a single SPAR pharmacy (or null for a
 * global admin).
 *
 * NOTE: patients have NO account here (spec FR-9) — they use the tokenised,
 * no-login tracker shipped by spar-core. This model is staff-only.
 */
class PharmacyUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'pharmacy_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'spar_pharmacy_id',
        'group_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Roles (spec FR-15). Legacy 'admin' is treated as super_admin.
     */
    public function isSuperAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin'], true);
    }

    public function isGroupAdmin(): bool
    {
        return $this->role === 'group_admin';
    }

    public function isPharmacyAdmin(): bool
    {
        return $this->role === 'pharmacy_admin';
    }

    public function isPharmacyStaff(): bool
    {
        return $this->role === 'pharmacy_staff';
    }

    /** Can this actor manage other users (any tier below their own)? */
    public function canManageUsers(): bool
    {
        return $this->isSuperAdmin() || $this->isGroupAdmin() || $this->isPharmacyAdmin();
    }

    /** @deprecated retained for back-compat with earlier code paths. */
    public function isAdmin(): bool
    {
        return $this->isSuperAdmin();
    }
}
