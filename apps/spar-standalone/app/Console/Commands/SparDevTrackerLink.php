<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;
use Zapmed\SparCore\Models\SparPatient;

/**
 * DEV-ONLY helper: mint a signed patient tracker link so you can open the
 * no-login "My Meds" patient phone web app in a browser without wiring a real
 * SMS/WhatsApp provider.
 *
 * Hard-guarded to local / debug environments — refuses to run otherwise, so it
 * can never become a production backdoor to PHI.
 *
 * Usage:
 *   php artisan spar:dev-tracker-link                 # uses the seeded consented patient
 *   php artisan spar:dev-tracker-link 5               # specific patient id
 *   php artisan spar:dev-tracker-link --profile=DEMO-1002
 *   php artisan spar:dev-tracker-link --host=http://127.0.0.1:8090
 */
class SparDevTrackerLink extends Command
{
    protected $signature = 'spar:dev-tracker-link
        {patient? : SparPatient id (defaults to the seeded consented demo patient)}
        {--profile= : Find the patient by profile_code instead of id}
        {--host= : Base URL to render the link against (default APP_URL)}';

    protected $description = '[DEV] Generate a signed tracker link to preview the patient phone web app';

    public function handle(): int
    {
        if (!app()->environment('local') && !config('app.debug')) {
            $this->error('Refusing to run: this dev helper is only available in local/debug environments.');

            return self::FAILURE;
        }

        $patient = $this->resolvePatient();

        if (!$patient) {
            $this->error('No matching patient found. Seed the DB (php artisan db:seed) or pass a valid id/--profile.');

            return self::FAILURE;
        }

        $ttl = (int) config('spar.link.ttl_minutes', 60 * 24 * 7);
        $link = URL::temporarySignedRoute('spar.track', now()->addMinutes($ttl), ['patient' => $patient->id]);

        // Re-render against a custom host if the dev server differs from APP_URL.
        if ($host = $this->option('host')) {
            $link = $this->rehost($link, $host);
        }

        $this->newLine();
        $this->info('Patient tracker link (valid ' . round($ttl / 60 / 24, 1) . ' days):');
        $this->line($link);
        $this->newLine();
        $this->line("  Patient : #{$patient->id}  {$patient->display_name}  (profile {$patient->profile_code})");
        $this->line('  Consent : ' . ($patient->hasConsented() ? 'opted_in — goes straight to dashboard' : 'pending — you will see the CONSENT GATE first'));

        if (config('spar.link.require_otp_reverify', true)) {
            $this->newLine();
            $this->warn('OTP re-verify is ON. After opening the link you will be asked for an OTP.');
            $this->line('  The OTP is "sent" via the log driver — look in storage/logs/laravel.log for the code,');
            $this->line('  or set SPAR_LINK_REQUIRE_OTP=false in .env to skip OTP while previewing.');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function resolvePatient(): ?SparPatient
    {
        if ($profile = $this->option('profile')) {
            // profile_code is encrypted at rest — cannot where() on it; scan in PHP.
            return SparPatient::all()->first(fn ($p) => $p->profile_code === $profile);
        }

        if ($id = $this->argument('patient')) {
            return SparPatient::find($id);
        }

        // Default: the seeded consented demo patient, else any primary member.
        return SparPatient::all()->first(fn ($p) => $p->hasConsented() && $p->is_primary_member)
            ?? SparPatient::where('is_primary_member', true)->first();
    }

    private function rehost(string $url, string $host): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return rtrim($host, '/') . $path . $query;
    }
}
