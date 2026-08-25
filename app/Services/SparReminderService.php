<?php

namespace App\Services;

use App\Models\SparDispenseRecord;
use App\Models\SparPatient;
use App\Models\SparPrescriptionJourney;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SparReminderService
{
    /**
     * Process all due reminders.
     * Called by the scheduled command daily.
     */
    public function processReminders(): array
    {
        $stats = [
            'reminders_sent' => 0,
            'renewal_reminders_sent' => 0,
            'errors' => 0,
        ];

        // Monthly medication reminders (7 days before due)
        $dueDispenses = SparDispenseRecord::dueForReminder()
            ->with(['patient.user', 'journey.pharmacy'])
            ->get();

        foreach ($dueDispenses as $dispense) {
            try {
                if ($this->sendMonthlyReminder($dispense)) {
                    $stats['reminders_sent']++;
                }
            } catch (\Exception $e) {
                Log::error('SPAR reminder failed', [
                    'dispense_id' => $dispense->id,
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        // Renewal reminders (final dispense approaching)
        $renewalDue = SparPrescriptionJourney::renewalDue()
            ->with(['patient.user', 'pharmacy'])
            ->get();

        foreach ($renewalDue as $journey) {
            try {
                if ($this->sendRenewalReminder($journey)) {
                    $stats['renewal_reminders_sent']++;
                }
            } catch (\Exception $e) {
                Log::error('SPAR renewal reminder failed', [
                    'journey_id' => $journey->id,
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * Send monthly medication reminder.
     */
    private function sendMonthlyReminder(SparDispenseRecord $dispense): bool
    {
        $patient = $dispense->patient;

        if (!$patient->hasConsented()) {
            return false;
        }

        // Check if patient has a linked user with contact info
        if (!$patient->user || !$patient->user->phone) {
            Log::info('SPAR reminder skipped - no contact info', [
                'spar_patient_id' => $patient->id,
                'profile_code' => $patient->profile_code,
            ]);
            return false;
        }

        $pharmacyName = $dispense->journey->pharmacy->name ?? 'your SPAR Pharmacy';
        $medications = collect($dispense->items ?? $dispense->journey->medications ?? [])
            ->pluck('name')
            ->filter()
            ->implode(', ');

        $message = $this->buildMonthlyMessage(
            $patient->user->first_name,
            $pharmacyName,
            $medications,
            $dispense->due_date->format('d F Y')
        );

        // TODO: Send via WhatsApp/Crisp when integrated
        // For now, log it and mark as reminded
        Log::info('SPAR medication reminder', [
            'patient' => $patient->display_name,
            'phone' => $patient->user->phone,
            'message' => $message,
        ]);

        $dispense->markReminded();

        return true;
    }

    /**
     * Send renewal reminder when prescription is nearing end.
     */
    private function sendRenewalReminder(SparPrescriptionJourney $journey): bool
    {
        $patient = $journey->patient;

        if (!$patient->hasConsented()) {
            return false;
        }

        if (!$patient->user || !$patient->user->phone) {
            return false;
        }

        $message = $this->buildRenewalMessage(
            $patient->user->first_name,
            $journey->pharmacy->name ?? 'your SPAR Pharmacy'
        );

        // TODO: Send via WhatsApp/Crisp when integrated
        Log::info('SPAR renewal reminder', [
            'patient' => $patient->display_name,
            'journey_id' => $journey->id,
            'phone' => $patient->user->phone,
            'message' => $message,
        ]);

        return true;
    }

    /**
     * Build monthly reminder message.
     */
    private function buildMonthlyMessage(string $firstName, string $pharmacy, string $medications, string $dueDate): string
    {
        $medLine = $medications ? "\nMedication: {$medications}" : '';

        return "Hi {$firstName}, your medication is due for collection at {$pharmacy} by {$dueDate}.{$medLine}\n\nWould you like to:\n1. Pack my order for collection\n2. Deliver to me\n\nReply with 1 or 2, or tap the button below.";
    }

    /**
     * Build renewal reminder message.
     */
    private function buildRenewalMessage(string $firstName, string $pharmacy): string
    {
        return "Hi {$firstName}, your prescription at {$pharmacy} is due for renewal.\n\nWould you like to:\n1. Renew with your primary doctor\n2. Consult a ZapMed doctor online\n\nReply with 1 or 2, or tap the button below.";
    }

    /**
     * Get patients who haven't responded to reminders (for exception list).
     */
    public function getUnresponsivePatients(int $daysOverdue = 10): Collection
    {
        return SparDispenseRecord::overdue()
            ->whereNotNull('reminded_at')
            ->where('due_date', '<', now()->subDays($daysOverdue))
            ->with(['patient.user', 'journey.pharmacy'])
            ->get();
    }

    /**
     * Generate missing prescription exceptions.
     * Patients whose renewal_due_date has passed without a new journey.
     */
    public function getMissingRenewals(int $daysOverdue = 7): Collection
    {
        return SparPrescriptionJourney::where('status', 'renewal_due')
            ->where('renewal_due_date', '<', now()->subDays($daysOverdue))
            ->with(['patient.user', 'pharmacy'])
            ->get();
    }
}
