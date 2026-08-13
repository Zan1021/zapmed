<?php

namespace App\Console\Commands;

use App\Models\Prescription;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendPrescriptionReminders extends Command
{
    protected $signature = 'prescriptions:remind';

    protected $description = 'Remind patients with chronic prescriptions to refill before they run out';

    public function handle(): int
    {
        $sent = 0;

        // Find chronic prescriptions that:
        // 1. Have refills remaining
        // 2. Were last dispensed ~25-28 days ago (assuming monthly meds)
        // 3. Haven't been reminded in the last 7 days
        $prescriptions = Prescription::where('is_chronic', true)
            ->whereColumn('repeats_used', '<', 'repeats')
            ->where('status', 'dispensed')
            ->where(function ($q) {
                // Last dispensed 25-30 days ago (time for refill)
                $q->whereBetween('dispatched_at', [now()->subDays(30), now()->subDays(25)]);
            })
            ->whereNull('valid_until')
            ->orWhere('valid_until', '>', now())
            ->with(['patient', 'items'])
            ->get();

        $sms = app(SmsService::class);

        foreach ($prescriptions as $prescription) {
            $patient = $prescription->patient;
            $remaining = $prescription->refills_remaining;
            $medNames = $prescription->items->pluck('medication_name')->implode(', ');

            // Send SMS
            if ($patient->phone) {
                $sms->send(
                    $patient->phone,
                    "Hi {$patient->first_name}, your medication ({$medNames}) may be running low. You have {$remaining} refill(s) remaining. Log in to Zapmed to request a refill. — Zapmed"
                );
                $sent++;
            }

            // Send email
            Mail::raw(
                "Hi {$patient->first_name},\n\nYour medication may be running low:\n\n{$medNames}\n\nYou have {$remaining} refill(s) remaining on prescription {$prescription->reference}.\n\nLog in to request your refill: " . url('/prescriptions') . "\n\n— Zapmed",
                function ($message) use ($patient) {
                    $message->to($patient->email)
                        ->subject('Time to refill your medication — Zapmed');
                }
            );
            $sent++;
        }

        $this->info("Sent {$sent} refill reminder(s).");
        return self::SUCCESS;
    }
}
