<?php

namespace App\Livewire\Admin;

use App\Services\AnalyticsService;
use App\Services\GoogleAnalyticsService;
use Livewire\Component;

class Analytics extends Component
{
    public string $period = 'month';

    public function refreshPageSpeed(): void
    {
        $ga = app(GoogleAnalyticsService::class);
        $ga->clearPageSpeedCache();
    }

    public function render()
    {
        $analytics = app(AnalyticsService::class);
        $ga = app(GoogleAnalyticsService::class);

        return view('livewire.admin.analytics', [
            'revenue' => $analytics->getRevenueSummary($this->period),
            'revenueByType' => $analytics->getRevenueByType($this->period),
            'revenueChart' => $analytics->getRevenueChart(12),
            'profit' => $analytics->getProfitSummary($this->period),
            'patients' => $analytics->getPatientStats(),
            'signupChart' => $analytics->getSignupChart(12),
            'funnel' => $analytics->getConversionFunnel(),
            'demographics' => $analytics->getPatientDemographics(),
            'consultations' => $analytics->getConsultationStats($this->period),
            'doctorPerformance' => $analytics->getDoctorPerformance(),
            'treatmentPopularity' => $analytics->getTreatmentPopularity(),
            'prescriptions' => $analytics->getPrescriptionStats($this->period),
            'topMeds' => $analytics->getTopMedications(),
            'partners' => $analytics->getPartnerStats(),
            'predictions' => $analytics->getPredictions(),
            'gaConfigured' => $ga->isConfigured(),
            'pageSpeedScores' => $ga->getAllPageSpeedScores(),
        ])->layout('layouts.app');
    }
}
