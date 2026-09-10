<?php

namespace App\Services\Spar\Identity;

use Zapmed\SparCore\Contracts\SparIdentity;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use App\Enums\UserRole;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;

/**
 * Integrated (ZapMed) identity provider (spec FR-2.2).
 *
 * Prefers SPAR-owned identity fields, falling back to the linked ZapMed `User`
 * for name/phone/email. This is the ONLY place SPAR reaches into `User`; the
 * standalone host binds a different provider that never does.
 */
class UserSparIdentityProvider implements SparIdentityProvider
{
    public function forPatient(SparPatient $patient): SparIdentity
    {
        $first = $patient->first_name;
        $last = $patient->last_name;
        $phone = $patient->cellphone;
        $email = $patient->email;
        $userId = $patient->user_id;

        // Integrated fallback to the linked User where SPAR-owned fields are
        // blank. Resolve via user_id so we don't depend on a `user()` relation
        // existing on the (package base) model instance.
        if ($userId) {
            $user = \App\Models\User::find($userId);
            if ($user) {
                $first = $first ?: ($user->first_name ?? '');
                $last = $last ?: ($user->last_name ?? '');
                $phone = $phone ?: ($user->phone ?? null);
                $email = $email ?: ($user->email ?? null);
            }
        }

        return new SparIdentity(
            userId: $userId,
            firstName: (string) $first,
            lastName: (string) $last,
            phone: $phone,
            email: $email,
        );
    }

    public function currentPharmacyId(): ?int
    {
        $user = auth()->user();

        if (!$user) {
            return null;
        }

        if ($user->role === UserRole::PharmacyStaff) {
            return $user->spar_pharmacy_id;
        }

        // Admin / other: not scoped to a single pharmacy.
        return null;
    }

    public function currentGroupId(): ?int
    {
        // Integrated ZapMed does not (yet) model group-admins on User; the group
        // hierarchy is exercised in the standalone host. A pharmacy-staff actor's
        // group is derived from their pharmacy. Super-admin/admin sees all → null.
        $user = auth()->user();
        if (!$user || $this->isSuperAdmin()) {
            return null;
        }

        if ($user->role === UserRole::PharmacyStaff && !empty($user->spar_pharmacy_id)) {
            return SparPharmacy::whereKey($user->spar_pharmacy_id)->value('group_id');
        }

        return null;
    }

    public function isSuperAdmin(): bool
    {
        $user = auth()->user();

        return $user && $user->role === UserRole::Admin;
    }

    public function currentRole(): ?string
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return match ($user->role) {
            UserRole::Admin => 'super_admin',
            UserRole::PharmacyStaff => 'pharmacy_staff',
            default => $user->role?->value,
        };
    }

    public function canManageGroup(int $groupId): bool
    {
        // Integrated: only the ZapMed admin (super-admin) manages groups.
        return $this->isSuperAdmin();
    }

    public function canManagePharmacy(int $pharmacyId): bool
    {
        return $this->isSuperAdmin();
    }
}
