<?php

namespace App\Console\Commands;

use App\Models\Prescription;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;
use Illuminate\Console\Command;

class SendPrescriptionReminders extends Command
{
    protected $signature = 'prescriptions:remind';

    protected $description = 'Remind patients with chronic prescriptions to refill before they run out';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $sent = 0;

        // Find chronic prescriptions that:
        // 1. Have refills remaining
        // 2. Were last dispensed ~25-28 days ago (assuming monthly meds)
        // 3. Haven't been reminded in the last 7 days
        $prescriptions = Prescription::where('is_chronic', true)
            ->whereColumn('repeats_used', '<', 'repeats')
            ->where('status', 'dispensed')
            ->whereNull('refill_of_id')
            // Last dispensed 25-30 days ago (time for refill)
            ->whereBetween('dispatched_at', [now()->subDays(30), now()->subDays(25)])
            // Grouped on purpose: without the brackets the "or" matched every prescription that has an expiry date in the future.
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->with(['patient', 'items'])
            ->get();

        $repeats = app(\App\Services\Subscriptions\PrescriptionRepeats::class);
        foreach ($prescriptions as $prescription) {
            // A repeat order is charged and sent automatically: nothing to ask the patient to do. And one reminder a week at most.
            if ($repeats->subscriptionFor($prescription) || ! $prescription->patient) {
                continue;
            }
            $last = data_get($prescription->metadata, 'refill_reminded_at');
            if ($last && \Illuminate\Support\Carbon::parse($last)->gt(now()->subDays(7))) {
                continue;
            }

            $patient = $prescription->patient;
            $remaining = $prescription->refills_remaining;
            $medNames = $prescription->items->pluck('medication_name')->implode(', ');

            $smsBody = "Hi {$patient->first_name}, your medication ({$medNames}) may be running low. "
                . "You have {$remaining} refill(s) remaining. Log in to Zapmed to request a refill. — Zapmed";

            $emailBody = "Hi {$patient->first_name},\n\nYour medication may be running low:\n\n{$medNames}\n\n"
                . "You have {$remaining} refill(s) remaining on prescription {$prescription->reference}.\n\n"
                . "Log in to request your refill: " . url('/prescriptions') . "\n\n— Zapmed";

            // SMS (only if we have a number).
            if ($patient->phone) {
                $dispatcher->sendVia(new OutboundMessage(
                    templateKey: 'prescription.refill_reminder',
                    category: 'transactional',
                    user: $patient,
                    phone: $patient->phone,
                    body: $smsBody,
                    meta: ['prescription_id' => $prescription->id],
                ), 'sms');
                $sent++;
            }

            // Email.
            $dispatcher->sendVia(new OutboundMessage(
                templateKey: 'prescription.refill_reminder',
                category: 'transactional',
                user: $patient,
                email: $patient->email,
                subject: 'Time to refill your medication — Zapmed',
                body: $emailBody,
                meta: ['prescription_id' => $prescription->id],
            ), 'email');
            $sent++;
            $prescription->update(['metadata' => array_merge((array) $prescription->metadata, ['refill_reminded_at' => now()->toIso8601String()])]);
        }

        $this->info("Sent {$sent} refill reminder(s).");

        return self::SUCCESS;
    }
}
