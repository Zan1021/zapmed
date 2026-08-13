<?php

namespace App\Livewire\Partner;

use App\Models\Partner;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public Partner $partner;

    public function mount(): void
    {
        $this->partner = Partner::where('user_id', Auth::id())->firstOrFail();
    }

    public function render()
    {
        $partner = $this->partner;

        // Stats
        $totalReferrals = $partner->referrals()->count();
        $registrations = $partner->referrals()->where('status', '!=', 'clicked')->count();
        $conversions = $partner->referrals()->where('status', 'converted')->count();
        $conversionRate = $totalReferrals > 0 ? round(($conversions / $totalReferrals) * 100, 1) : 0;

        $pendingEarnings = $partner->commissions()->where('status', 'pending')->sum('commission_amount');
        $approvedEarnings = $partner->commissions()->where('status', 'approved')->sum('commission_amount');
        $paidEarnings = $partner->commissions()->where('status', 'paid')->sum('commission_amount');
        $totalEarnings = $approvedEarnings + $paidEarnings;

        // This month
        $monthReferrals = $partner->referrals()->whereMonth('created_at', now()->month)->count();
        $monthEarnings = $partner->commissions()
            ->whereMonth('created_at', now()->month)
            ->whereIn('status', ['pending', 'approved', 'paid'])
            ->sum('commission_amount');

        // Recent commissions
        $recentCommissions = $partner->commissions()
            ->with('patient')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('livewire.partner.dashboard', [
            'totalReferrals' => $totalReferrals,
            'registrations' => $registrations,
            'conversions' => $conversions,
            'conversionRate' => $conversionRate,
            'pendingEarnings' => $pendingEarnings,
            'approvedEarnings' => $approvedEarnings,
            'paidEarnings' => $paidEarnings,
            'totalEarnings' => $totalEarnings,
            'monthReferrals' => $monthReferrals,
            'monthEarnings' => $monthEarnings,
            'recentCommissions' => $recentCommissions,
        ])->layout('layouts.app');
    }
}
