<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;

/**
 * Charge-at-booking: release slots whose payment reservation has expired.
 *
 * When a patient confirms a booking we create the appointment as `pending_payment` with a
 * `reserved_until` hold and send them to PayFast. If payment isn't confirmed before the hold expires,
 * this sweep marks the appointment `expired` so the slot frees up for others. Never touches paid or
 * already-confirmed appointments.
 *
 * Schedule: run every few minutes (see routes/console.php / Kernel schedule).
 */
class ReleaseExpiredReservations extends Command
{
    protected $signature = 'onboarding:release-expired-reservations';

    protected $description = 'Release slot reservations for unpaid bookings whose hold has expired';

    public function handle(): int
    {
        $expired = Appointment::query()
            ->where('status', 'pending_payment')
            ->where('is_paid', false)
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<', now())
            ->get();

        foreach ($expired as $appointment) {
            $appointment->update(['status' => 'expired', 'reserved_until' => null]);
        }

        $this->info("Released {$expired->count()} expired reservation(s).");

        return self::SUCCESS;
    }
}
