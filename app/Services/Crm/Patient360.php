<?php

namespace App\Services\Crm;

use App\Enums\UserRole;
use App\Models\CrmLead;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Patient-360 read aggregator + cross-field search (parity with Mark's crm patient-view).
 *
 * assemble() builds a single read model for one patient — profile + lead + risk + flags + notes +
 * recent orders/payments/consults + LTV — for the admin patient view. Pure reads; no side effects.
 *
 * search() is the cross-field patient lookup: member number / email / msisdn / name / order number.
 * NOTE: id_number (SA-ID) is stored ENCRYPTED (User::$encryptedFields), so it cannot be matched with a
 * SQL LIKE. We therefore do NOT offer SA-ID substring search here (an exact-match decrypt-scan is a
 * separate, deliberate feature); this is called out so "cross-field search" isn't silently overstated.
 */
class Patient360
{
    /**
     * Cross-field patient search. Matches member_number, email, phone (msisdn), first/last name, and
     * order reference / Contro order number. Returns the matching patient users (deduped).
     *
     * @return Collection<int,User>
     */
    public function search(string $term, int $limit = 25): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }

        $like = "%{$term}%";

        return User::query()
            ->where('role', UserRole::Patient->value)
            ->where(function ($q) use ($like, $term) {
                $q->where('member_number', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhereRaw("(first_name || ' ' || last_name) like ?", [$like])
                    ->orWhereHas('orders', function ($o) use ($like) {
                        $o->where('reference', 'like', $like)
                            ->orWhere('contro_order_number', 'like', $like);
                    });
            })
            ->orderBy('first_name')
            ->limit($limit)
            ->get();
    }

    /**
     * Assemble the full 360 read model for a patient. Returns null if the user isn't a patient.
     *
     * @return array<string,mixed>|null
     */
    public function assemble(User $patient): ?array
    {
        if (! $patient->isPatient()) {
            return null;
        }

        $patient->loadMissing([
            'patientProfile',
            'crmLead.activeFlags',
            'crmLead.notes',
            'crmLead.riskScore',
            'crmLead.funnelEvents',
        ]);

        $lead = $patient->crmLead;

        $recentOrders = $patient->orders()->latest('ordered_at')->limit(10)->get();
        $recentPayments = $patient->payments()->latest()->limit(10)->get();
        $recentConsults = $patient->appointments()->latest('appointment_date')->limit(10)->get();

        // Lifetime value = sum of completed payments (minor units → rands).
        $ltvMinor = (int) $patient->payments()->where('status', 'completed')->sum('amount');

        return [
            'patient' => $patient,
            'profile' => $patient->patientProfile,
            'lead' => $lead,
            'stage' => $lead?->current_stage,
            'risk' => $lead?->riskScore,
            'flags' => $lead?->activeFlags ?? collect(),
            'notes' => $lead?->notes ?? collect(),
            'funnel_events' => $lead?->funnelEvents ?? collect(),
            'recent_orders' => $recentOrders,
            'recent_payments' => $recentPayments,
            'recent_consults' => $recentConsults,
            'ltv_minor' => $ltvMinor,
            'counts' => [
                'orders' => $patient->orders()->count(),
                'payments' => $patient->payments()->count(),
                'consults' => $patient->appointments()->count(),
                'active_flags' => $lead?->activeFlags()->count() ?? 0,
            ],
        ];
    }

    /** Convenience: assemble by patient id, or null. */
    public function assembleById(int $patientId): ?array
    {
        $patient = User::find($patientId);

        return $patient ? $this->assemble($patient) : null;
    }
}
