<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Partner;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;

class ReferralService
{
    /**
     * Link a newly registered user to their referral partner.
     */
    public function attributeRegistration(User $user): ?Referral
    {
        $cookie = request()->cookie('zapmed_ref');

        if (!$cookie) {
            return null;
        }

        [$slug, $referralId] = explode('|', $cookie, 2) + [null, null];

        if (!$slug || !$referralId) {
            return null;
        }

        $referral = Referral::where('id', $referralId)
            ->whereHas('partner', fn ($q) => $q->where('slug', $slug)->active())
            ->whereNull('patient_id')
            ->first();

        if (!$referral) {
            return null;
        }

        $referral->update([
            'patient_id' => $user->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        return $referral;
    }

    /**
     * Record a commission when a referred patient pays for a consultation.
     */
    public function recordConsultationCommission(User $patient, int $amountCents, string $reference): ?Commission
    {
        $referral = $this->getActiveReferral($patient);

        if (!$referral) {
            return null;
        }

        $partner = $referral->partner;
        $rate = $partner->commission_consultation;
        $commission = (int) round($amountCents * ($rate / 100));

        if ($commission <= 0) {
            return null;
        }

        // Mark as converted on first purchase
        if ($referral->status === 'registered') {
            $referral->update(['status' => 'converted', 'converted_at' => now()]);
        }

        return Commission::create([
            'partner_id' => $partner->id,
            'referral_id' => $referral->id,
            'patient_id' => $patient->id,
            'type' => 'consultation',
            'reference' => $reference,
            'sale_amount' => $amountCents,
            'commission_rate' => $rate,
            'commission_amount' => $commission,
            'status' => 'pending',
        ]);
    }

    /**
     * Record a commission when a referred patient pays for medication.
     */
    public function recordMedicationCommission(User $patient, int $amountCents, string $reference): ?Commission
    {
        $referral = $this->getActiveReferral($patient);

        if (!$referral) {
            return null;
        }

        $partner = $referral->partner;
        $rate = $partner->commission_medication;
        $commission = (int) round($amountCents * ($rate / 100));

        if ($commission <= 0) {
            return null;
        }

        return Commission::create([
            'partner_id' => $partner->id,
            'referral_id' => $referral->id,
            'patient_id' => $patient->id,
            'type' => 'medication',
            'reference' => $reference,
            'sale_amount' => $amountCents,
            'commission_rate' => $rate,
            'commission_amount' => $commission,
            'status' => 'pending',
        ]);
    }

    /**
     * Get the active referral for a patient (if any).
     */
    private function getActiveReferral(User $patient): ?Referral
    {
        return Referral::where('patient_id', $patient->id)
            ->whereIn('status', ['registered', 'converted'])
            ->whereHas('partner', fn ($q) => $q->active())
            ->first();
    }
}
