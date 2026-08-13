<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Payout;
use App\Models\Payment;
use App\Models\Referral;

class PayoutService
{
    /**
     * Calculate and record revenue split for a consultation payment.
     */
    public function recordConsultationPayout(Payment $payment): Payout
    {
        $amount = $payment->amount; // cents
        $appointment = $payment->appointment;
        $doctorId = $appointment?->doctor_id;

        $doctorRate = config('payouts.consultation.doctor_percentage');
        $platformRate = config('payouts.consultation.platform_percentage');

        $doctorAmount = (int) round($amount * ($doctorRate / 100));
        $platformAmount = $amount - $doctorAmount;

        // Check for partner referral
        $partnerData = $this->getPartnerSplit($payment->patient_id, $amount, 'consultation');

        // Partner commission comes from platform's cut
        if ($partnerData['amount'] > 0) {
            $platformAmount -= $partnerData['amount'];
        }

        return Payout::create([
            'payment_id' => $payment->id,
            'type' => 'consultation',
            'reference' => $appointment?->reference,
            'total_amount' => $amount,
            'doctor_id' => $doctorId,
            'doctor_amount' => $doctorAmount,
            'doctor_rate' => $doctorRate,
            'platform_amount' => $platformAmount,
            'platform_rate' => $platformRate,
            'partner_id' => $partnerData['partner_id'],
            'partner_amount' => $partnerData['amount'],
            'partner_rate' => $partnerData['rate'],
        ]);
    }

    /**
     * Calculate and record revenue split for a medication payment.
     */
    public function recordMedicationPayout(Payment $payment, ?int $pharmacyId = null): Payout
    {
        $amount = $payment->amount;
        $deliveryFee = config('payouts.medication.delivery_fee');

        $netAmount = $amount - $deliveryFee; // remove delivery fee first
        $pharmacyRate = config('payouts.medication.pharmacy_percentage');
        $platformRate = config('payouts.medication.platform_percentage');

        $pharmacyAmount = (int) round($netAmount * ($pharmacyRate / 100));
        $platformAmount = $netAmount - $pharmacyAmount;

        // Partner commission from platform cut
        $partnerData = $this->getPartnerSplit($payment->patient_id, $amount, 'medication');
        if ($partnerData['amount'] > 0) {
            $platformAmount -= $partnerData['amount'];
        }

        return Payout::create([
            'payment_id' => $payment->id,
            'type' => 'medication',
            'reference' => $payment->description,
            'total_amount' => $amount,
            'pharmacy_id' => $pharmacyId,
            'pharmacy_amount' => $pharmacyAmount,
            'pharmacy_rate' => $pharmacyRate,
            'platform_amount' => $platformAmount,
            'platform_rate' => $platformRate,
            'partner_id' => $partnerData['partner_id'],
            'partner_amount' => $partnerData['amount'],
            'partner_rate' => $partnerData['rate'],
            'delivery_fee' => $deliveryFee,
        ]);
    }

    /**
     * Get partner split if patient was referred.
     */
    private function getPartnerSplit(int $patientId, int $amount, string $type): array
    {
        $referral = Referral::where('patient_id', $patientId)
            ->whereIn('status', ['registered', 'converted'])
            ->whereHas('partner', fn ($q) => $q->active())
            ->first();

        if (!$referral) {
            return ['partner_id' => null, 'amount' => 0, 'rate' => 0];
        }

        $partner = $referral->partner;
        $rate = $type === 'consultation'
            ? $partner->commission_consultation
            : $partner->commission_medication;

        $commission = (int) round($amount * ($rate / 100));

        return ['partner_id' => $partner->id, 'amount' => $commission, 'rate' => $rate];
    }

    /**
     * Get summary for admin dashboard.
     */
    public static function getFinancialSummary(string $period = 'month'): array
    {
        $query = Payout::query();

        if ($period === 'month') {
            $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        } elseif ($period === 'week') {
            $query->where('created_at', '>=', now()->startOfWeek());
        }

        return [
            'total_revenue' => $query->sum('total_amount'),
            'doctor_payouts' => $query->sum('doctor_amount'),
            'pharmacy_payouts' => $query->sum('pharmacy_amount'),
            'partner_payouts' => $query->sum('partner_amount'),
            'delivery_costs' => $query->sum('delivery_fee'),
            'platform_profit' => $query->sum('platform_amount'),
            'transaction_count' => $query->count(),
        ];
    }
}
