<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;
use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPrescriptionJourney;

/**
 * DEV helper: seed ONE consented test patient with a full 6-month (6-repeat)
 * chronic prescription so the phone web app shows a realistic tracker.
 * Prints a signed tracker link to open it. Local/debug only.
 *
 *   php artisan spar:seed-demo-prescription
 *   php artisan spar:seed-demo-prescription --renewal   (final repeat reached → renewal-due card)
 */
class SparSeedDemoPrescription extends Command
{
    protected $signature = 'spar:seed-demo-prescription
        {--renewal : Set the journey to its final repeat so the renewal card shows}
        {--host=http://127.0.0.1:8090 : Base URL for the printed link}';

    protected $description = '[DEV] Seed a consented patient with a 6-month prescription + print a tracker link';

    public function handle(): int
    {
        if (!app()->environment('local') && !config('app.debug')) {
            $this->error('Dev helper only — refusing to run outside local/debug.');

            return self::FAILURE;
        }

        $pharmacy = SparPharmacy::firstOrCreate(
            ['spar_store_id' => 'DEMO-6MONTH'],
            ['name' => 'SPAR Pharmacy — Demo', 'is_active' => true, 'supports_delivery' => true]
        );

        // Consented primary member with full identity.
        $patient = SparPatient::updateOrCreate(
            ['spar_pharmacy_id' => $pharmacy->id, 'profile_code' => 'DEMO-6M', 'dependent_code' => '0'],
            [
                'first_name' => 'Nomsa',
                'last_name' => 'Khumalo',
                'cellphone' => '0821112222',
                'email' => 'nomsa@example.test',
                'is_primary_member' => true,
                'is_active' => true,
                'onboarding_status' => 'active',
                'consent_status' => 'opted_in',
                'consent_given_at' => now(),
                'consent_channel' => 'web',
            ]
        );

        $renewal = (bool) $this->option('renewal');
        $completed = $renewal ? 6 : 2; // 6/6 = renewal due; 2/6 = mid-course

        // Fresh journey each run for a clean demo.
        SparPrescriptionJourney::where('spar_patient_id', $patient->id)->delete();

        $journey = SparPrescriptionJourney::create([
            'spar_patient_id' => $patient->id,
            'spar_pharmacy_id' => $pharmacy->id,
            'script_number' => 'DEMO-SCRIPT-6M',
            'status' => $renewal ? 'renewal_due' : 'active',
            'total_dispenses' => 6,
            'dispenses_completed' => $completed,
            'start_date' => now()->subMonths($completed),
            'next_dispense_date' => $renewal ? null : now()->addDays(5),
            'renewal_due_date' => now()->addMonths(6 - $completed),
            'doctor_name' => 'Dr A. Naidoo',
            'medications' => [
                ['name' => 'AMLODIPINE 5MG TAB 30', 'quantity' => 30],
                ['name' => 'METFORMIN 500MG TAB 60', 'quantity' => 60],
                ['name' => 'ATORVASTATIN 20MG TAB 30', 'quantity' => 30],
            ],
        ]);

        // Build 6 monthly dispense records: collected ones + upcoming ones.
        for ($i = 1; $i <= 6; $i++) {
            $isDone = $i <= $completed;
            SparDispenseRecord::create([
                'journey_id' => $journey->id,
                'spar_patient_id' => $patient->id,
                'dispense_number' => $i,
                'status' => $isDone ? 'collected' : 'upcoming',
                'due_date' => now()->subMonths($completed)->addMonths($i - 1),
                'completed_at' => $isDone ? now()->subMonths($completed)->addMonths($i - 1) : null,
                'fulfillment_type' => 'collection',
            ]);
        }

        $ttl = (int) config('spar.link.ttl_minutes', 60 * 24 * 7);
        $link = URL::temporarySignedRoute('spar.track', now()->addMinutes($ttl), ['patient' => $patient->id]);

        if ($host = $this->option('host')) {
            $parts = parse_url($link);
            $link = rtrim($host, '/') . ($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        $this->newLine();
        $this->info('Seeded 6-month prescription for Nomsa Khumalo (profile DEMO-6M).');
        $this->line("  Journey: {$completed}/6 dispenses" . ($renewal ? ' — RENEWAL DUE' : ' — active'));
        $this->line('  Meds: AMLODIPINE 5MG, METFORMIN 500MG, ATORVASTATIN 20MG');
        $this->newLine();
        $this->info('Open the phone app at:');
        $this->line($link);
        $this->newLine();

        return self::SUCCESS;
    }
}
