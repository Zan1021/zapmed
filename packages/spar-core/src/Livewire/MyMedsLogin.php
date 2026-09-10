<?php

namespace Zapmed\SparCore\Livewire;

use Zapmed\SparCore\Contracts\OtpSender;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Services\SparPatientSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Phone-entry patient access path (spec FR-9): the patient types their number,
 * receives an OTP, and — on success — gets a scoped SPAR patient session
 * (NOT a User login). The signed-link path (spar.track) is the primary route;
 * this is the fallback for a patient who has the number but not the link.
 */
class MyMedsLogin extends Component
{
    public string $phone = '';
    public string $otp = '';
    public string $step = 'phone'; // phone | verify
    public string $error = '';
    public string $maskedPhone = '';

    protected function rules(): array
    {
        if ($this->step === 'phone') {
            return ['phone' => 'required|string|min:9|max:15'];
        }

        return ['otp' => 'required|string|size:6'];
    }

    /**
     * Step 1: Submit phone number, generate and send OTP.
     */
    public function sendOtp(): void
    {
        $this->validate(['phone' => 'required|string|min:9|max:15']);
        $this->error = '';

        $normalizedPhone = $this->normalizePhone($this->phone);

        $patient = $this->findPatientByPhone($normalizedPhone);

        if (!$patient) {
            $this->error = 'No SPAR prescription account found for this number. Please contact your SPAR pharmacy.';
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put("spar_otp_{$normalizedPhone}", [
            'code' => $code,
            'spar_patient_id' => $patient->id,
            'attempts' => 0,
        ], now()->addMinutes(10));

        $this->dispatchOtp($normalizedPhone, $code);

        $this->maskedPhone = $this->maskPhone($normalizedPhone);
        $this->step = 'verify';
    }

    /**
     * Step 2: Verify the OTP code and establish a SPAR patient session.
     */
    public function verifyOtp(): void
    {
        $this->validate(['otp' => 'required|string|size:6']);
        $this->error = '';

        $normalizedPhone = $this->normalizePhone($this->phone);
        $cached = Cache::get("spar_otp_{$normalizedPhone}");

        if (!$cached) {
            $this->error = 'Code expired. Please request a new one.';
            $this->step = 'phone';
            return;
        }

        if (($cached['attempts'] ?? 0) >= 5) {
            Cache::forget("spar_otp_{$normalizedPhone}");
            $this->error = 'Too many attempts. Please request a new code.';
            $this->step = 'phone';
            return;
        }

        $cached['attempts'] = ($cached['attempts'] ?? 0) + 1;
        Cache::put("spar_otp_{$normalizedPhone}", $cached, now()->addMinutes(10));

        if ($cached['code'] !== $this->otp) {
            $this->error = 'Invalid code. Please try again.';
            return;
        }

        Cache::forget("spar_otp_{$normalizedPhone}");

        $patient = SparPatient::find($cached['spar_patient_id']);
        if (!$patient) {
            $this->error = 'Account not found. Please contact support.';
            return;
        }

        // No User login — establish the scoped SPAR patient session + mark verified.
        $session = app(SparPatientSession::class);
        $session->establish($patient);
        $session->markVerified();

        Log::channel('spar_audit')->info('SPAR patient session via phone OTP', [
            'spar_patient_id' => $patient->id,
            'ip' => request()->ip(),
        ]);

        $this->redirect(route('my-meds.track'));
    }

    /**
     * Find a SPAR patient by phone. cellphone is encrypted, so we compare the
     * decrypted value; falls back to a linked User phone in integrated mode.
     * (Pilot-scale scan; a blind-index lookup is a future optimisation.)
     */
    private function findPatientByPhone(string $normalizedPhone): ?SparPatient
    {
        $last9 = substr($normalizedPhone, -9);

        return SparPatient::query()
            ->where('is_active', true)
            ->get()
            ->first(function (SparPatient $p) use ($last9) {
                $phone = $p->primaryPhone();
                return $phone && str_ends_with(preg_replace('/[^0-9]/', '', $phone), $last9);
            });
    }

    public function resendOtp(): void
    {
        $this->step = 'phone';
        $this->otp = '';
        $this->error = '';
        $this->sendOtp();
    }

    public function back(): void
    {
        $this->step = 'phone';
        $this->otp = '';
        $this->error = '';
    }

    /**
     * Dispatch OTP via the host-bound OtpSender transport. Logs in dev mode.
     */
    private function dispatchOtp(string $phone, string $code): void
    {
        app(OtpSender::class)->sendOtp($phone, $code);

        if (app()->environment('local', 'staging')) {
            session()->flash('dev_otp', $code);
        }
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '+27')) {
            $phone = '0' . substr($phone, 3);
        }
        if (str_starts_with($phone, '27') && strlen($phone) === 11) {
            $phone = '0' . substr($phone, 2);
        }

        return $phone;
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 7) return $phone;
        return substr($phone, 0, 3) . '****' . substr($phone, -3);
    }

    public function render()
    {
        return view('spar::livewire.my-meds-login')
            ->layout(config('spar.layouts.patient', 'layouts.spar-meds'));
    }
}
