<?php

namespace Zapmed\SparCore\Livewire;

use Zapmed\SparCore\Concerns\LogsSparActivity;
use Zapmed\SparCore\Contracts\OtpSender;
use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparPatientSession;
use Zapmed\SparCore\Services\SparOrderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * No-login SPAR patient tracker (spec FR-7, FR-8, FR-9).
 *
 * Flow: valid signed link establishes a scoped SPAR patient session ->
 *   [otp]     optional OTP re-verify (forwarded-link protection)
 *   [consent] consent gate — NO PHI shown until granted (hard stop)
 *   [dashboard] medication tracker (rolls up dependants under the profile)
 *
 * Never authenticates a User. All access is scoped to the session patient.
 */
class MyMedsTracker extends Component
{
    use LogsSparActivity;

    public string $step = 'consent'; // otp | consent | dashboard
    public string $otp = '';
    public string $error = '';
    public bool $consentAccepted = false;

    // delivery form (carried over from MyMedsDashboard)
    public bool $showDeliveryForm = false;
    public string $deliveryAddress = '';
    public string $deliveryCity = '';
    public string $deliveryPostalCode = '';
    public string $deliveryPhone = '';

    private function session(): SparPatientSession
    {
        return app(SparPatientSession::class);
    }

    public function mount(): void
    {
        $session = $this->session();

        if (!$session->patientId()) {
            abort(403, 'Invalid or expired tracker link.');
        }

        if ($session->needsOtp()) {
            $this->step = 'otp';
            $this->dispatchOtp();
            return;
        }

        $this->resolveStepFromConsent();
    }

    private function resolveStepFromConsent(): void
    {
        $patient = $this->session()->patient();
        $this->step = ($patient && $patient->hasConsented()) ? 'dashboard' : 'consent';
    }

    // ---- OTP re-verify -----------------------------------------------------

    private function otpCacheKey(): string
    {
        return 'spar_track_otp_' . $this->session()->patientId();
    }

    private function dispatchOtp(): void
    {
        $patient = $this->session()->patient();
        if (!$patient || !$patient->isContactable()) {
            $this->error = 'We could not reach you to verify. Please contact your SPAR pharmacy.';
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->otpCacheKey(), ['code' => $code, 'attempts' => 0],
            now()->addMinutes((int) config('spar.link.otp_ttl_minutes', 10)));

        // Consent is not yet granted here, so send the OTP directly through the
        // host OtpSender transport rather than the consent-gated dispatcher.
        if ($phone = $patient->primaryPhone()) {
            app(OtpSender::class)->sendOtp($phone, $code);
        }

        if (app()->environment('local', 'staging')) {
            session()->flash('dev_otp', $code);
        }
    }

    public function verifyOtp(): void
    {
        $this->validate(['otp' => 'required|string|size:6']);
        $cached = Cache::get($this->otpCacheKey());

        if (!$cached) {
            $this->error = 'Code expired. Please reopen your link.';
            return;
        }
        if (($cached['attempts'] ?? 0) >= 5) {
            Cache::forget($this->otpCacheKey());
            $this->error = 'Too many attempts. Please reopen your link.';
            return;
        }

        $cached['attempts']++;
        Cache::put($this->otpCacheKey(), $cached, now()->addMinutes((int) config('spar.link.otp_ttl_minutes', 10)));

        if ($cached['code'] !== $this->otp) {
            $this->error = 'Invalid code. Please try again.';
            return;
        }

        Cache::forget($this->otpCacheKey());
        $this->session()->markVerified();
        $this->error = '';
        $this->otp = '';
        $this->resolveStepFromConsent();
    }

    // ---- Consent gate (hard stop) -----------------------------------------

    public function grantConsent(): void
    {
        if (!$this->consentAccepted) {
            $this->error = 'Please tick the box to give consent before continuing.';
            return;
        }

        $patient = $this->session()->patient();
        if (!$patient) {
            abort(403);
        }

        $patient->optIn('web', [
            'source' => 'patient',
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);

        $this->logSparActivity('consent_granted', 'Patient granted consent via tracker', [
            'spar_patient_id' => $patient->id,
            'channel' => 'web',
        ]);

        $this->error = '';
        $this->step = 'dashboard';
    }

    public function declineConsent(): void
    {
        $patient = $this->session()->patient();
        if ($patient) {
            $patient->optOut([
                'source' => 'patient',
                'channel' => 'web',
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
            ]);
            $this->logSparActivity('consent_declined', 'Patient declined consent via tracker', [
                'spar_patient_id' => $patient->id,
            ]);
        }

        $this->session()->flush();
        $this->step = 'consent';
        session()->flash('declined', true);
    }

    // ---- Dashboard data (scoped to the session patient + dependants) -------

    /**
     * The primary member for this session. Dependants roll up under them
     * (spec FR-8): we resolve to the primary member of the profile.
     */
    public function getSparPatientProperty(): ?SparPatient
    {
        $patient = $this->session()->patient();
        if (!$patient) {
            return null;
        }

        // profile_code is encrypted — can't query it directly. Filter members
        // in PHP within the same pharmacy by decrypted profile_code.
        if (!$patient->is_primary_member) {
            $primary = SparPatient::where('spar_pharmacy_id', $patient->spar_pharmacy_id)
                ->where('is_primary_member', true)
                ->get()
                ->first(fn (SparPatient $p) => $p->profile_code === $patient->profile_code);
            $patient = $primary ?? $patient;
        }

        return $patient->load(['pharmacy']);
    }

    /**
     * All patients under this profile (primary + dependants) for the roll-up.
     * profile_code is encrypted, so match on the decrypted value in PHP.
     */
    public function getProfileMembersProperty()
    {
        $patient = $this->sparPatient;
        if (!$patient) {
            return collect();
        }

        return SparPatient::where('spar_pharmacy_id', $patient->spar_pharmacy_id)
            ->get()
            ->filter(fn (SparPatient $p) => $p->profile_code === $patient->profile_code)
            ->sortByDesc('is_primary_member')
            ->values();
    }

    /**
     * Active journeys across the whole profile (self + dependants).
     */
    public function getJourneysProperty()
    {
        $memberIds = $this->profileMembers->pluck('id');

        return SparPrescriptionJourney::whereIn('spar_patient_id', $memberIds)
            ->whereIn('status', ['active', 'renewal_due'])
            ->with('patient')
            ->latest()
            ->get();
    }

    public function getRenewalDueProperty()
    {
        return $this->journeys->firstWhere('status', 'renewal_due');
    }

    public function render()
    {
        return view('spar::livewire.my-meds-tracker')
            ->layout(config('spar.layouts.patient', 'layouts.spar-meds'));
    }
}
