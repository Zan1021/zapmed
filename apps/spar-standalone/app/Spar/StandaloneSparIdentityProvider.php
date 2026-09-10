<?php

namespace App\Spar;

use Zapmed\SparCore\Contracts\SparIdentity;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;

/**
 * Standalone identity provider (spec FR-2.2, design §5.1/§5.3).
 *
 * Patient identity comes ENTIRELY from SPAR-owned fields on spar_patients —
 * there is no telehealth `User` table to fall back to. The current staff
 * pharmacy scope is read from the authenticated PharmacyUser (guarded, not
 * the ZapMed role enum).
 */
class StandaloneSparIdentityProvider implements SparIdentityProvider
{
    public function forPatient(SparPatient $patient): SparIdentity
    {
        // Standalone: identity is SPAR-owned only. No User fallback.
        return new SparIdentity(
            userId: null,
            firstName: (string) ($patient->first_name ?? ''),
            lastName: (string) ($patient->last_name ?? ''),
            phone: $patient->cellphone,
            email: $patient->email,
        );
    }

    public function currentPharmacyId(): ?int
    {
        $user = auth()->user();

        if (!$user) {
            return null;
        }

        // Super-admin / group-admin: not scoped to a single pharmacy.
        if ($this->isSuperAdmin() || (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin())) {
            return null;
        }

        return $user->spar_pharmacy_id ? (int) $user->spar_pharmacy_id : null;
    }

    public function currentGroupId(): ?int
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        // Super-admin sees all groups → null (no restriction).
        if ($this->isSuperAdmin()) {
            return null;
        }

        // Group-admin carries their own group_id directly.
        if (property_exists($user, 'group_id') || isset($user->group_id)) {
            if (!empty($user->group_id)) {
                return (int) $user->group_id;
            }
        }

        // Pharmacy actor: derive the group from their pharmacy, if any.
        if (!empty($user->spar_pharmacy_id)) {
            return SparPharmacy::whereKey($user->spar_pharmacy_id)->value('group_id');
        }

        return null;
    }

    public function isSuperAdmin(): bool
    {
        $user = auth()->user();

        return $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }

    public function currentRole(): ?string
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }
        // Normalise legacy 'admin' → super_admin.
        return $user->role === 'admin' ? 'super_admin' : ($user->role ?? null);
    }

    public function canManageGroup(int $groupId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $user = auth()->user();
        // Group-admin may manage only their own group.
        if ($user && method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
            return (int) $user->group_id === $groupId;
        }

        return false;
    }

    public function canManagePharmacy(int $pharmacyId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // Group-admin: any pharmacy in their group.
        if (method_exists($user, 'isGroupAdmin') && $user->isGroupAdmin()) {
            $groupId = SparPharmacy::whereKey($pharmacyId)->value('group_id');

            return $groupId !== null && (int) $groupId === (int) $user->group_id;
        }

        // Pharmacy-admin: only their own pharmacy.
        if (method_exists($user, 'isPharmacyAdmin') && $user->isPharmacyAdmin()) {
            return (int) $user->spar_pharmacy_id === $pharmacyId;
        }

        // Pharmacy-staff: no management.
        return false;
    }
}
