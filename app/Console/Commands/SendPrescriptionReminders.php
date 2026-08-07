<?php

namespace App\Console\Commands;

use App\Models\Prescription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPrescriptionReminders extends Command
{
    protected $signature = 'prescriptions:remind';
    protected $description = 'Send reminders to patients with chronic prescriptions due for refill';

    public function handle(): int
    {
        $remindDaysBefore = 5; // Remind 5 days before medication runs out

        // Find chronic prescriptions that are paid and due for refill
        $prescriptions = Prescription::where('is_chronic', true)
            ->where('payment_status', 'paid')
            ->where('pharmacy_status', 'dispatched')
            ->whereNotNull('paid_at')
            ->where(function ($q) {
                $q->where('repeats', 0) // unlimited
                  ->orWhereColumn('repeats_used', '<', 'repeats');
            })
            ->with(['patient', 'items'])
            ->get();

        $sent = 0;

        foreach ($prescriptions as $prescription) {
            // Calculate when meds run out based on longest duration item
            $maxDuration = $prescription->items->max('duration_days') ?? 30;
            $refillDate = $prescription->paid_at->addDays($maxDuration);

            // Check if we're within the reminder window
            $daysUntilRefill = now()->diffInDays($refillDate, false);

            if ($daysUntilRefill > 0 && $daysUntilRefill <= $remindDaysBefore) {
                // Don't send if we already reminded (check metadata)
                $lastReminder = $prescription->metadata['last_reminder_sent'] ?? null;
                if ($lastReminder && now()->diffInDays($lastReminder) < 3) {
                    continue; // Don't spam — max once every 3 days
                }

                // Send reminder email
                Mail::to($prescription->patient)->queue(
                    new \App\Mail\PrescriptionRefillReminder($prescription, $daysUntilRefill)
                );

                // Track that we sent a reminder
                $prescription->update([
                    'metadata' => array_merge($prescription->metadata ?? [], [
                        'last_reminder_sent' => now()->toISOString(),
                    ]),
                ]);

                $sent++;
            }
        }

        $this->info("Sent {$sent} prescription refill reminders.");
        Log::info("Prescription reminders sent: {$sent}");

        return Command::SUCCESS;
    }
}
