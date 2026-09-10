<?php

namespace Database\Seeders;

use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparImportService;
use Illuminate\Database\Seeder;

/**
 * Full-system demo data. Runs the REAL dual-file import (so the demo mirrors a
 * live SPAR upload), then layers realistic lifecycle states on top so EVERY
 * screen has content: consent variety, orders in each status, an overdue
 * dispense, a renewal-due journey, and reminders.
 *
 * Idempotent-ish: intended to run on a freshly migrated demo DB
 * (`migrate:fresh --seed`). Safe to re-run; it will just add more orders.
 */
class DemoSeeder extends Seeder
{
    private const DEMO_DIR = 'C:\\Users\\zande\\Documents\\Zapmed\\Spar\\demo';

    public function run(): void
    {
        $sales = self::DEMO_DIR . '\\Demo SalesExtract072026.csv';
        $drug = self::DEMO_DIR . '\\Demo Drug Usage 01 Sept 2026.xlsx';

        if (!is_file($sales) || !is_file($drug)) {
            $this->command?->warn('Demo import files not found — skipping import layer. Expected in ' . self::DEMO_DIR);
            return;
        }

        // The import service deletes the files it processes (PHI). Work on COPIES
        // so the originals (handed to the client) survive.
        $tmpSales = tempnam(sys_get_temp_dir(), 'demo_sales') . '.csv';
        $tmpDrug = tempnam(sys_get_temp_dir(), 'demo_drug') . '.xlsx';
        copy($sales, $tmpSales);
        copy($drug, $tmpDrug);

        config(['spar.onboarding_mode' => 'pharmacist_capture']);
        $batch = (new SparImportService())->importPair($tmpSales, $tmpDrug, null, 'manual');
        $this->command?->info("Imported demo files: {$batch->records_created} patients created.");

        // Attach every imported pharmacy to the demo group so group-admin sees them.
        $group = \Zapmed\SparCore\Models\SparPharmacyGroup::firstOrCreate(
            ['slug' => 'spar-western-cape'],
            ['name' => 'SPAR Western Cape', 'region' => 'Western Cape', 'is_active' => true]
        );
        SparPharmacy::whereNull('group_id')->update(['group_id' => $group->id]);

        // --- Consent variety --------------------------------------------------
        // Principals opt in (so reminders + tracker work); leave one pending.
        $principals = SparPatient::where('is_primary_member', true)->get();
        foreach ($principals as $i => $p) {
            if ($i === count($principals) - 1) {
                continue; // leave the last principal pending_consent for the demo
            }
            $p->optIn('whatsapp', ['source' => 'pharmacist', 'ip_address' => '127.0.0.1']);
        }

        // --- Orders in every status (for the pharmacy dashboard queue) --------
        $withJourney = SparPatient::whereHas('journeys')->get();
        $statuses = ['requested', 'preparing', 'ready', 'completed'];
        foreach ($withJourney->take(4)->values() as $idx => $patient) {
            $journey = $patient->journeys()->first();
            $dispense = SparDispenseRecord::where('journey_id', $journey->id)->first();
            $status = $statuses[$idx % count($statuses)];

            SparOrder::create([
                'spar_patient_id' => $patient->id,
                'spar_pharmacy_id' => $patient->spar_pharmacy_id,
                'dispense_record_id' => $dispense?->id,
                'type' => $idx % 2 === 0 ? 'collection' : 'delivery',
                'status' => $status,
                'delivery_address' => $idx % 2 === 0 ? null : ($patient->metadata['address'] ?? '1 Demo St'),
                'delivery_phone' => $idx % 2 === 0 ? null : $patient->cellphone,
                'notes' => 'Demo order (' . $status . ')',
                'prepared_at' => in_array($status, ['preparing', 'ready', 'completed']) ? now()->subHours(2) : null,
                'ready_at' => in_array($status, ['ready', 'completed']) ? now()->subHour() : null,
                'completed_at' => $status === 'completed' ? now() : null,
            ]);
        }

        // --- An OVERDUE upcoming dispense (exceptions screen) -----------------
        $firstJourney = SparPrescriptionJourney::first();
        if ($firstJourney) {
            SparDispenseRecord::create([
                'journey_id' => $firstJourney->id,
                'spar_patient_id' => $firstJourney->spar_patient_id,
                'dispense_number' => 99,
                'status' => 'reminded',
                'due_date' => now()->subDays(12),
                'reminded_at' => now()->subDays(11),
                'fulfillment_type' => 'collection',
            ]);
        }

        // --- A RENEWAL-DUE journey (dashboard + exceptions + tracker CTA) -----
        $renewalJourney = SparPrescriptionJourney::skip(1)->first() ?? $firstJourney;
        if ($renewalJourney) {
            $renewalJourney->update([
                'status' => 'renewal_due',
                'dispenses_completed' => $renewalJourney->total_dispenses,
                'renewal_due_date' => now()->subDays(9),
                'next_dispense_date' => null,
            ]);
        }

        // --- An UPCOMING dispense due soon (reminder engine has work) ---------
        $activeJourney = SparPrescriptionJourney::where('status', 'active')->first();
        if ($activeJourney) {
            SparDispenseRecord::create([
                'journey_id' => $activeJourney->id,
                'spar_patient_id' => $activeJourney->spar_patient_id,
                'dispense_number' => 50,
                'status' => 'upcoming',
                'due_date' => now()->addDays(3),
                'fulfillment_type' => 'collection',
            ]);
        }

        $this->command?->info('Demo lifecycle layered: consent, orders (4 statuses), overdue + upcoming dispenses, renewal-due journey.');
    }
}
