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
    private const DEMO_DIR = 'E:\\OneDrive\\Desktop\\craig';

    public function run(): void
    {
        $this->runImportLayer();

        // The lifecycle layer (consent, orders, exceptions, renewals, banners)
        // runs against whatever patients/journeys exist — whether they came from
        // the import above or a prior seed. This is deliberate: on the staging
        // server the local Windows import path does NOT exist, so the import is
        // skipped there; the demo queue must still be populated so screens like
        // Orders are never empty in front of the client.
        $this->layerLifecycle();
    }

    /**
     * Run the REAL dual-file import when the demo files are reachable, so the
     * demo mirrors a live SPAR upload. Skipped gracefully when the files aren't
     * present (e.g. the Forge server has no local Windows path) — the lifecycle
     * layer then works on whatever data is already seeded.
     */
    private function runImportLayer(): void
    {
        $sales = self::DEMO_DIR . '\\Demo SalesExtract072026-wapadrand.csv';
        $drug = self::DEMO_DIR . '\\Demo Drug Usage 01 Sept 2026.xlsx';

        if (!is_file($sales) || !is_file($drug)) {
            $this->command?->warn('Demo import files not found — skipping import layer (expected on servers without the local path). Lifecycle layer will run on existing data.');
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

        // Stamp onboarding provenance so the staff patient-detail view shows a
        // real "onboarded by <pharmacist>" for the demo. Use the seeded staff.
        $pharmacist = \App\Models\PharmacyUser::where('email', 'staff@sparmeds.test')->first();
        if ($pharmacist) {
            foreach (SparPatient::whereNull('captured_by_id')->get() as $sp) {
                $sp->forceFill([
                    'captured_by_id' => $pharmacist->id,
                    'captured_at' => $sp->captured_at ?? now(),
                    'onboarding_pharmacy_id' => $sp->onboarding_pharmacy_id ?? $sp->spar_pharmacy_id,
                ])->saveQuietly();
            }
        }
    }

    /**
     * Layer realistic lifecycle states on top of whatever patients/journeys
     * exist so EVERY screen has content: group attachment, consent variety,
     * orders in each status (never-empty queue), an overdue + upcoming dispense,
     * a renewal-due journey, demo banners, and a multi-store journey.
     */
    private function layerLifecycle(): void
    {
        if (SparPatient::query()->doesntExist()) {
            $this->command?->warn('No SPAR patients present — lifecycle layer skipped. Run an import or the demo files first.');
            return;
        }

        // Attach every pharmacy to the demo group so group-admin sees them.
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
            if (! $p->hasConsented()) {
                $p->optIn('whatsapp', ['source' => 'pharmacist', 'ip_address' => '127.0.0.1']);
            }
        }

        // --- Orders in every status (for the pharmacy dashboard queue) --------
        // Idempotent: only seed the demo queue when it's empty, so re-running the
        // seeder doesn't pile up duplicate demo orders, but a fresh/empty staging
        // DB always ends up with a populated queue (requested + preparing + ready
        // + completed) — the fix for the empty-Orders-screen finding.
        $existingDemoOrders = SparOrder::where('notes', 'like', 'Demo order%')->count();
        if ($existingDemoOrders === 0) {
            $withJourney = SparPatient::whereHas('journeys')->get();
            $statuses = ['requested', 'preparing', 'ready', 'completed'];
            $seeded = 0;
            foreach ($withJourney->take(4)->values() as $idx => $patient) {
                $journey = $patient->journeys()->first();
                if (! $journey) {
                    continue;
                }
                $dispense = SparDispenseRecord::where('journey_id', $journey->id)->first();
                $status = $statuses[$idx % count($statuses)];

                SparOrder::create([
                    'spar_patient_id' => $patient->id,
                    'spar_pharmacy_id' => $patient->spar_pharmacy_id,
                    'dispense_record_id' => $dispense?->id,
                    'type' => $idx % 2 === 0 ? 'collection' : 'delivery',
                    'fulfilment_mode' => $idx % 2 === 0 ? 'collect_pay_store' : 'deliver_pay_now',
                    'status' => $status,
                    'payment_status' => $idx % 2 === 0 ? 'pay_at_store' : 'paid',
                    'delivery_address' => $idx % 2 === 0 ? null : ($patient->metadata['address'] ?? '1 Demo St'),
                    'delivery_phone' => $idx % 2 === 0 ? null : $patient->cellphone,
                    'notes' => 'Demo order (' . $status . ')',
                    'prepared_at' => in_array($status, ['preparing', 'ready', 'completed']) ? now()->subHours(2) : null,
                    'ready_at' => in_array($status, ['ready', 'completed']) ? now()->subHour() : null,
                    'completed_at' => $status === 'completed' ? now() : null,
                ]);
                $seeded++;
            }
            $this->command?->info("Demo orders seeded ({$seeded}) across requested/preparing/ready/completed.");
        } else {
            $this->command?->info("Demo orders already present ({$existingDemoOrders}) — queue seed skipped (idempotent).");
        }

        // --- An OVERDUE upcoming dispense (exceptions screen) -----------------
        $firstJourney = SparPrescriptionJourney::first();
        if ($firstJourney && ! SparDispenseRecord::where('journey_id', $firstJourney->id)->where('dispense_number', 99)->exists()) {
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
        if ($activeJourney && ! SparDispenseRecord::where('journey_id', $activeJourney->id)->where('dispense_number', 50)->exists()) {
            SparDispenseRecord::create([
                'journey_id' => $activeJourney->id,
                'spar_patient_id' => $activeJourney->spar_patient_id,
                'dispense_number' => 50,
                'status' => 'upcoming',
                'due_date' => now()->addDays(3),
                'fulfillment_type' => 'collection',
            ]);
        }

        $this->command?->info('Demo lifecycle layered: consent, orders, overdue + upcoming dispenses, renewal-due journey.');

        // Demo promo banners for the group so the mobi slider shows something.
        $this->seedDemoBanners($group);

        // NOTE: the multi-store "second pharmacy" demo was removed for the
        // Wapadrand pitch — Wapadrand is the ONLY branch, so all journeys stay
        // under it. (National-identity multi-store behaviour is still in the
        // code; it just isn't exercised by this single-branch demo.)
    }

    /**
     * Seed a couple of demo promo banners (real WebP) for the group so the mobi
     * slider shows content. Generated with GD — no upload needed.
     */
    private function seedDemoBanners(\Zapmed\SparCore\Models\SparPharmacyGroup $group): void
    {
        if (\Zapmed\SparCore\Models\SparBanner::where('group_id', $group->id)->exists()) {
            return;
        }
        if (!function_exists('imagewebp') || empty(gd_info()['WebP Support'])) {
            $this->command?->warn('Demo banners skipped — GD WebP not available.');
            return;
        }

        $disk = config('spar.banners.disk', 'public');
        $w = (int) config('spar.banners.width', 1080);
        $h = (int) config('spar.banners.height', 420);

        $slides = [
            ['Winter Flu Specials — 20% off', [0, 107, 63], 'https://www.spar.co.za'],
            ['Free BP checks this month', [200, 30, 40], null],
        ];

        foreach ($slides as $i => [$text, $rgb, $url]) {
            $img = imagecreatetruecolor($w, $h);
            $bg = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
            imagefilledrectangle($img, 0, 0, $w, $h, $bg);
            $white = imagecolorallocate($img, 255, 255, 255);
            imagestring($img, 5, 40, (int) ($h / 2) - 10, $text, $white);

            $tmp = tempnam(sys_get_temp_dir(), 'demoban') . '.webp';
            imagewebp($img, $tmp, (int) config('spar.banners.quality', 78));
            imagedestroy($img);

            $path = 'spar-banners/demo-' . ($i + 1) . '.webp';
            \Illuminate\Support\Facades\Storage::disk($disk)->put($path, file_get_contents($tmp));
            @unlink($tmp);

            \Zapmed\SparCore\Models\SparBanner::create([
                'group_id' => $group->id,
                'title' => $text,
                'image_path' => $path,
                'link_url' => $url,
                'sort_order' => $i + 1,
                'is_active' => true,
                'impressions' => rand(120, 480),
                'clicks' => rand(5, 40),
            ]);
        }

        $this->command?->info('Demo promo banners seeded (2) for ' . $group->name . '.');
    }
}
