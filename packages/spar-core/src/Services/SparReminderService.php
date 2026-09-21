<?php

namespace Zapmed\SparCore\Services;

use Zapmed\SparCore\Contracts\SparActionable;
use Zapmed\SparCore\Contracts\TelehealthBridge;
use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPatientSignal;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class SparReminderService
{
    private MessagingDispatcher $dispatcher;
    private TelehealthBridge $bridge;

    public function __construct(?MessagingDispatcher $dispatcher = null, ?TelehealthBridge $bridge = null)
    {
        $this->dispatcher = $dispatcher ?? new MessagingDispatcher();
        $this->bridge = $bridge ?? app(TelehealthBridge::class);
    }

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
            ->with(['patient', 'journey.pharmacy'])
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
            ->with(['patient', 'pharmacy'])
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
     * Whether an actionable subject is currently snoozed (a staff deferral or a
     * patient "remind me later"). The ResolvesActionable trait owns the rule:
     * status snoozed AND snoozed_until in the future. A woken snooze (past
     * snoozed_until) returns false so the reminder fires again.
     */
    private function isSnoozed(SparActionable $subject): bool
    {
        return $subject instanceof Model
            && method_exists($subject, 'isSnoozed')
            && $subject->isSnoozed();
    }

    /**
     * Whether the patient has muted reminders for THIS subject via their latest
     * response signal (FR-B4). Only a hard opt-out (stop_reminders / ignore_future)
     * suppresses here; timed mutes (ignore_month / remind_*) are already enforced
     * as a snooze by SparActionService::applyScheduleAdjustment, so they surface
     * through isSnoozed() and must not be double-counted as a permanent opt-out.
     *
     * Read against the subject's true class (host subclass or package model) so
     * the soft-polymorphic subject_type recorded at response time matches.
     */
    private function isSuppressedByLatestSignal(SparActionable $subject): bool
    {
        if (! $subject instanceof Model) {
            return false;
        }

        $latest = SparPatientSignal::forSubject(
            SparPatientSignal::canonicalType($subject),
            $subject->getKey()
        )
            ->latestFirst()
            ->first();

        return $latest !== null && $latest->type()->isOptOut();
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

        // Close-the-loop honouring (FR-B4): don't re-fire while the item is
        // snoozed (staff deferral or a patient "remind me later"), and honour a
        // patient's hard opt-out for this subject even if they remain consented
        // at the account level (a "STOP"/"never" on one item mutes only that item).
        if ($this->isSnoozed($dispense) || $this->isSuppressedByLatestSignal($dispense)) {
            return false;
        }

        // Must be reachable on some channel (SPAR-owned identity or, integrated,
        // a linked User). No contact => skip (recorded for the exception list).
        if (!$patient->isContactable()) {
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

        $firstName = $patient->first_name ?: $patient->display_name;

        $message = $this->buildMonthlyMessage(
            $firstName,
            $pharmacyName,
            $medications,
            $dispense->due_date->format('d F Y')
        );

        $sent = $this->dispatcher->send($patient, [
            'subject' => "{$pharmacyName} — medication due",
            'body' => $message,
            'link' => $this->trackerLink($patient),
        ]);

        if ($sent) {
            $dispense->markReminded();
        }

        return $sent;
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

        // Same close-the-loop honouring as the monthly reminder (FR-B4):
        // skip snoozed journeys and journeys the patient has opted out of.
        if ($this->isSnoozed($journey) || $this->isSuppressedByLatestSignal($journey)) {
            return false;
        }

        if (!$patient->isContactable()) {
            return false;
        }

        $firstName = $patient->first_name ?: $patient->display_name;

        $message = $this->buildRenewalMessage(
            $firstName,
            $journey->pharmacy->name ?? 'your SPAR Pharmacy'
        );

        return $this->dispatcher->send($patient, [
            'subject' => 'Your prescription is due for renewal',
            'body' => $message,
            'link' => $this->trackerLink($patient),
        ]);
    }

    /**
     * Generate the patient's signed, expiring tracker link (spec FR-9.1).
     * No login — the token in the link grants scoped access to their profile.
     */
    private function trackerLink(SparPatient $patient): string
    {
        $ttl = (int) config('spar.link.ttl_minutes', 60 * 24 * 7);

        return URL::temporarySignedRoute(
            'spar.track',
            now()->addMinutes($ttl),
            ['patient' => $patient->id]
        );
    }

    /**
     * Build monthly reminder message.
     */
    private function buildMonthlyMessage(string $firstName, string $pharmacy, string $medications, string $dueDate): string
    {
        $medLine = $medications ? "\nMedication: {$medications}" : '';

        return "Hi {$firstName}, your medication is due for collection at {$pharmacy} by {$dueDate}.{$medLine}\n\nWould you like to:\n1. Pack my order for collection\n2. Deliver to me\n\nReply with 1 or 2, or tap the button below." . $this->optInFooter();
    }

    /**
     * Consent / reminder-preference footer appended to every reminder
     * (Craig — patients must be able to dial comms down, and understand why
     * we send them). Offers NEVER / REMIND IN A MONTH / OPT IN plus a one-line
     * benefit explanation. The choices are wired to the response engine in
     * Wave B; this establishes the copy now.
     */
    private function optInFooter(): string
    {
        return "\n\nPharmacy reminders help you never miss a repeat and keep your treatment on track."
            . "\nManage reminders — reply: MONTH (remind me next month), STOP (never remind me), or OPT IN to keep them on.";
    }

    /**
     * Build renewal reminder message.
     *
     * The online-consult option is driven by the TelehealthBridge: the ZapMed
     * bridge offers it, the NullTelehealthBridge (standalone) does not
     * (spec FR-11.2, FR-13).
     */
    private function buildRenewalMessage(string $firstName, string $pharmacy): string
    {
        if ($this->bridge->offersOnlineConsult()) {
            return "Hi {$firstName}, your prescription at {$pharmacy} is due for renewal.\n\nWould you like to:\n1. Renew with your primary doctor\n2. Consult a ZapMed doctor online\n\nReply with 1 or 2, or tap the button below." . $this->optInFooter();
        }

        return "Hi {$firstName}, your prescription at {$pharmacy} is due for renewal.\n\nPlease arrange a new prescription with your doctor, then visit {$pharmacy} to continue your medication.\n\nTap the button below for details." . $this->optInFooter();
    }

    /**
     * Get patients who haven't responded to reminders (for exception list).
     */
    public function getUnresponsivePatients(int $daysOverdue = 10): Collection
    {
        return SparDispenseRecord::overdue()
            ->whereNotNull('reminded_at')
            ->where('due_date', '<', now()->subDays($daysOverdue))
            ->with(['patient', 'journey.pharmacy'])
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
            ->with(['patient', 'pharmacy'])
            ->get();
    }
}
