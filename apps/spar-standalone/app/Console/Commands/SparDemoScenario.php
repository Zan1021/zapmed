<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;
use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPrescriptionJourney;

/**
 * DEMO helper: reset a fixed set of patients into the exact states needed for
 * the SPAR pitch, then print signed tracker links for each.
 *
 * Re-runnable (idempotent) — run it again after a rehearsal has mutated the
 * data (orders placed, consent granted) to restore the demo to a clean slate.
 * Works on any environment whose seed contains the named patients; patients are
 * resolved by display NAME (not hard id) because ids differ between local
 * sqlite and staging postgres.
 *
 * Scenario:
 *   - CONSENT SIGNUP demo : one patient reset to pre-consent → lands on the
 *                           consent gate so you can demo the opt-in flow.
 *   - ORDER PROCESS demo  : two consented PRIMARY members with an active script
 *                           that has repeats left and no open order → the
 *                           "Order next meds" button is ready to click.
 *
 * Only PRIMARY members work for tracker demos: the tracker always shows the
 * profile's primary member, so a dependant's own link resolves to their primary.
 *
 * Usage:
 *   php artisan spar:demo-scenario
 *   php artisan spar:demo-scenario --host=https://spar.zapmed.africa
 */
class SparDemoScenario extends Command
{
    protected $signature = 'spar:demo-scenario
        {--host= : Base URL to render the tracker links against (default APP_URL)}
        {--consent= : Display name of the consent-signup patient (default "Zander Cronje")}
        {--order=* : Display name(s) of order-demo patients (repeatable; default Thandi Mbeki + Johan van Wyk)}';

    protected $description = '[DEMO] Reset the SPAR pitch demo patients to clean states + print tracker links';

    /** Statuses that count as an "open" order (hide the order button). */
    private const OPEN_ORDER_STATUSES = ['requested', 'preparing', 'ready'];

    public function handle(): int
    {
        // Guard: never touch real production data with this reset tool.
        if (! app()->environment('local', 'staging') && ! config('app.debug')) {
            $this->error('Refusing to run: spar:demo-scenario is only for local/staging/debug environments.');

            return self::FAILURE;
        }

        $consentName = (string) ($this->option('consent') ?: 'Zander Cronje');
        $orderNames = $this->option('order') ?: ['Thandi Mbeki', 'Johan van Wyk'];

        $this->newLine();
        $this->info('Resetting SPAR demo scenario…');

        // ---- CONSENT SIGNUP patient -----------------------------------------
        $consentPatient = $this->findPrimaryByName($consentName);
        if (! $consentPatient) {
            $this->error("Consent-demo patient not found by name: {$consentName}");

            return self::FAILURE;
        }
        $this->resetToPreConsent($consentPatient);
        // Make sure there's an orderable script waiting behind the gate too.
        $this->makeOrderable($consentPatient);

        // ---- ORDER PROCESS patients -----------------------------------------
        $orderPatients = [];
        foreach ($orderNames as $name) {
            $p = $this->findPrimaryByName($name);
            if (! $p) {
                $this->error("Order-demo patient not found by name: {$name}");

                return self::FAILURE;
            }
            $this->makeConsented($p);
            $this->makeOrderable($p);
            $orderPatients[] = $p;
        }

        // ---- Report + links --------------------------------------------------
        $ttl = (int) config('spar.link.ttl_minutes', 60 * 24 * 7);
        $days = round($ttl / 60 / 24, 1);

        $this->newLine();
        $this->info("Done. Signed tracker links (valid {$days} days):");
        $this->newLine();

        $this->line('  [CONSENT SIGNUP] ' . $this->describe($consentPatient));
        $this->line('  ' . $this->trackerLink($consentPatient, $ttl));
        $this->newLine();

        foreach ($orderPatients as $i => $p) {
            $this->line('  [ORDER DEMO #' . ($i + 1) . '] ' . $this->describe($p));
            $this->line('  ' . $this->trackerLink($p, $ttl));
            $this->newLine();
        }

        if (config('spar.link.require_otp_reverify', true)) {
            $this->warn('OTP re-verify is ON — opening a link asks for an OTP (sent via the configured channel; on local it is logged / flashed).');
            $this->line('  Set SPAR_LINK_REQUIRE_OTP=false to skip it while presenting.');
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /* ---------------------------------------------------------------------- */

    /**
     * Resolve a PRIMARY member by display name. profile_code/name are encrypted
     * at rest, so we scan in PHP rather than a SQL where().
     */
    private function findPrimaryByName(string $name): ?SparPatient
    {
        $needle = mb_strtolower(trim($name));

        return SparPatient::all()->first(function (SparPatient $p) use ($needle) {
            return $p->is_primary_member && mb_strtolower(trim($p->display_name)) === $needle;
        });
    }

    /** Put a patient into a pristine pre-consent state (lands on consent gate). */
    private function resetToPreConsent(SparPatient $p): void
    {
        $p->update([
            'consent_status' => 'pending',
            'consent_given_at' => null,
            'consent_revoked_at' => null,
        ]);
        // Wipe prior consent audit rows so it reads as a clean first-time gate.
        $p->consents()->delete();

        if (empty($p->cellphone)) {
            $p->update(['cellphone' => '0821110000']);
        }
    }

    /** Mark a patient consented (lands straight on the dashboard). */
    private function makeConsented(SparPatient $p): void
    {
        $p->update([
            'consent_status' => 'opted_in',
            'consent_given_at' => now(),
            'consent_revoked_at' => null,
            'consent_channel' => $p->consent_channel ?: 'web',
        ]);

        if (empty($p->cellphone)) {
            $p->update(['cellphone' => '0821110000']);
        }
    }

    /**
     * Guarantee the patient has an ACTIVE journey with repeats remaining, a
     * latest dispense record, and NO open order — so "Order next meds" shows.
     */
    private function makeOrderable(SparPatient $p): void
    {
        $journeys = SparPrescriptionJourney::where('spar_patient_id', $p->id)->get();
        $journey = $journeys->firstWhere('status', 'active') ?? $journeys->sortByDesc('id')->first();

        if (! $journey) {
            $journey = SparPrescriptionJourney::create([
                'spar_patient_id' => $p->id,
                'spar_pharmacy_id' => $p->spar_pharmacy_id,
                'script_number' => 'DEMO-' . $p->id . '-' . now()->format('His'),
                'status' => 'active',
                'total_dispenses' => 6,
                'dispenses_completed' => 2,
                'start_date' => now()->subMonths(2),
                'next_dispense_date' => now()->addDays(14),
                'renewal_due_date' => now()->addMonths(4),
                'medications' => [['name' => 'CARVEDILOL 12,5MG TAB 30', 'quantity' => 30]],
            ]);
        }

        // Force an active, mid-course, orderable shape.
        if ((int) $journey->dispenses_completed >= (int) $journey->total_dispenses) {
            $journey->total_dispenses = max((int) $journey->total_dispenses, 6);
            $journey->dispenses_completed = max(0, (int) $journey->total_dispenses - 2);
        }
        $journey->status = 'active';
        $journey->renewal_due_date = now()->addMonths(4);
        $journey->next_dispense_date = now()->addDays(14);
        $journey->save();

        // Ensure a latest dispense record exists (placeOrder needs one).
        $dispense = SparDispenseRecord::where('journey_id', $journey->id)->orderByDesc('id')->first();
        if (! $dispense) {
            SparDispenseRecord::create([
                'journey_id' => $journey->id,
                'spar_patient_id' => $p->id,
                'dispense_number' => ((int) $journey->dispenses_completed) ?: 1,
                'status' => 'collected',
                'due_date' => now()->subDays(20),
            ]);
        }

        // Cancel any OPEN order on this journey's dispenses so the button returns.
        $dispIds = SparDispenseRecord::where('journey_id', $journey->id)->pluck('id');
        SparOrder::whereIn('dispense_record_id', $dispIds)
            ->whereIn('status', self::OPEN_ORDER_STATUSES)
            ->get()
            ->each(function (SparOrder $o) {
                $o->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_reason' => 'Reset by spar:demo-scenario',
                ]);
            });
    }

    private function trackerLink(SparPatient $p, int $ttl): string
    {
        $link = URL::temporarySignedRoute('spar.track', now()->addMinutes($ttl), ['patient' => $p->id]);

        if ($host = $this->option('host')) {
            $parts = parse_url($link);
            $link = rtrim($host, '/') . ($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return $link;
    }

    private function describe(SparPatient $p): string
    {
        $consent = $p->hasConsented()
            ? 'opted_in → dashboard + Order button'
            : 'pending → CONSENT GATE';

        return "{$p->display_name} (id {$p->id}, profile {$p->profile_code}) — {$consent}";
    }
}
