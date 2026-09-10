<?php

namespace Zapmed\SparCore\Services;

use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the household "patient view" (spec FR-3).
 *
 * Assembles exactly what a patient sees on their mobi tracker — resolved to the
 * PRIMARY member, rolled up across all dependants under the same Profile Code
 * (self + dependants), with journeys, dispense history, and renewal state.
 *
 * Both the patient-facing tracker (MyMedsTracker / MyMedsHistory) AND the staff
 * read-only mirror (PatientDetail) use this, so the two can never drift.
 *
 * profile_code is encrypted at rest, so members are matched on the DECRYPTED
 * value in PHP (scoped to the pharmacy set the profile appears at).
 */
class SparPatientView
{
    /**
     * Resolve to the PRIMARY member of the given patient's profile.
     */
    public function primary(SparPatient $patient): SparPatient
    {
        if ($patient->is_primary_member) {
            return $patient->load('pharmacy');
        }

        $primary = SparPatient::where('spar_pharmacy_id', $patient->spar_pharmacy_id)
            ->where('is_primary_member', true)
            ->get()
            ->first(fn (SparPatient $p) => $p->profile_code === $patient->profile_code);

        return ($primary ?? $patient)->load('pharmacy');
    }

    /**
     * All members under the profile (primary first, then dependants).
     */
    public function members(SparPatient $patient): Collection
    {
        $primary = $this->primary($patient);

        return SparPatient::where('spar_pharmacy_id', $primary->spar_pharmacy_id)
            ->get()
            ->filter(fn (SparPatient $p) => $p->profile_code === $primary->profile_code)
            ->sortByDesc('is_primary_member')
            ->values();
    }

    /**
     * Dependants only (everyone under the profile except the primary).
     */
    public function dependants(SparPatient $patient): Collection
    {
        return $this->members($patient)->filter(fn (SparPatient $p) => !$p->is_primary_member)->values();
    }

    /**
     * Active + renewal-due journeys across the whole profile, pharmacy loaded
     * (for the "Collected at: <pharmacy>" label).
     */
    public function journeys(SparPatient $patient): Collection
    {
        $memberIds = $this->members($patient)->pluck('id');

        return SparPrescriptionJourney::whereIn('spar_patient_id', $memberIds)
            ->whereIn('status', ['active', 'renewal_due'])
            ->with(['patient', 'pharmacy'])
            ->latest()
            ->get();
    }

    public function renewalDue(SparPatient $patient): ?SparPrescriptionJourney
    {
        return $this->journeys($patient)->firstWhere('status', 'renewal_due');
    }

    /**
     * PAST prescriptions across the profile — completed / renewed / expired
     * journeys (i.e. not currently active or renewal-due). Pharmacy loaded.
     */
    public function pastJourneys(SparPatient $patient): Collection
    {
        $memberIds = $this->members($patient)->pluck('id');

        return SparPrescriptionJourney::whereIn('spar_patient_id', $memberIds)
            ->whereNotIn('status', ['active', 'renewal_due'])
            ->with(['patient', 'pharmacy'])
            ->latest('updated_at')
            ->get();
    }

    /**
     * Collection history across the whole profile, pharmacy loaded.
     */
    public function history(SparPatient $patient, int $limit = 50): Collection
    {
        $memberIds = $this->members($patient)->pluck('id');

        return SparDispenseRecord::whereIn('spar_patient_id', $memberIds)
            ->whereIn('status', ['collected', 'delivered'])
            ->with('journey.pharmacy')
            ->orderByDesc('completed_at')
            ->limit($limit)
            ->get();
    }
}
