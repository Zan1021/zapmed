<?php

namespace Zapmed\SparCore\Contracts;

use Zapmed\SparCore\Models\SparPatient;

/**
 * Resolves SPAR actor identity (spec FR-2.1). Host apps bind an implementation:
 *   - ZapMed (integrated): UserSparIdentityProvider — reads the linked User.
 *   - Standalone: a provider backed by SPAR-owned identity records only.
 *
 * SPAR domain code depends on this contract, never on App\Models\User directly.
 */
interface SparIdentityProvider
{
    /**
     * Resolve the identity for a SPAR patient (own fields first, host fallback).
     */
    public function forPatient(SparPatient $patient): SparIdentity;

    /**
     * The pharmacy id the current authenticated staff actor is scoped to,
     * or null if not scoped to a single pharmacy (super-admin, group-admin).
     */
    public function currentPharmacyId(): ?int;

    /**
     * The pharmacy GROUP id the current actor is scoped to, or null if not
     * group-scoped (super-admin = null = all; pharmacy actor may still have a
     * group via their pharmacy but their management scope is the pharmacy).
     */
    public function currentGroupId(): ?int;

    /**
     * Whether the current actor is a platform super-admin (sees/does all).
     */
    public function isSuperAdmin(): bool;

    /**
     * The current actor's role key: super_admin|group_admin|pharmacy_admin|
     * pharmacy_staff|null (unauthenticated).
     */
    public function currentRole(): ?string;

    /**
     * Whether the current actor may manage (create/edit) the given group.
     * Super-admin: any. Group-admin: only their own group. Others: no.
     */
    public function canManageGroup(int $groupId): bool;

    /**
     * Whether the current actor may manage the given pharmacy.
     * Super-admin: any. Group-admin: pharmacies in their group. Pharmacy-admin:
     * only their own pharmacy. Pharmacy-staff: no.
     */
    public function canManagePharmacy(int $pharmacyId): bool;
}
