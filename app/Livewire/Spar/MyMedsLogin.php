<?php

namespace App\Livewire\Spar;

use App\Enums\UserRole;
use App\Models\SparPatient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

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

        // Normalize phone number
        $normalizedPhone = $this->normalizePhone($this->phone);

        // Check if this phone belongs to a SPAR patient
        $user = User::where('phone', $normalizedPhone)
            ->whereHas('sparPatients')
            ->first();

        if (!$user) {
            // Also check by raw phone in case formatting differs
            $user = User::where('phone', 'like', '%' . substr($normalizedPhone, -9))
                ->whereHas('sparPatients')
                ->first();
        }

        if (!$user) {
            $this->error = 'No SPAR prescription account found for this number. Please contact your SPAR pharmacy.';
            return;
        }

        // Generate 6-digit OTP
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in cache (expires in 10 minutes)
        Cache::put("spar_otp_{$normalizedPhone}", [
            'code' => $code,
            'user_id' => $user->id,
            'attempts' => 0,
        ], now()->addMinutes(10));

        // Send OTP via SMS (currently dev mode — logs it)
        $this->dispatchOtp($normalizedPhone, $code);

        $this->maskedPhone = $this->maskPhone($normalizedPhone);
        $this->step = 'verify';
    }

    /**
     * Step 2: Verify the OTP code.
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

        // Rate limit: max 5 attempts
        if (($cached['attempts'] ?? 0) >= 5) {
            Cache::forget("spar_otp_{$normalizedPhone}");
            $this->error = 'Too many attempts. Please request a new code.';
            $this->step = 'phone';
            return;
        }

        // Increment attempts
        $cached['attempts'] = ($cached['attempts'] ?? 0) + 1;
        Cache::put("spar_otp_{$normalizedPhone}", $cached, now()->addMinutes(10));

        if ($cached['code'] !== $this->otp) {
            $this->error = 'Invalid code. Please try again.';
            return;
        }

        // OTP verified — log the user in
        Cache::forget("spar_otp_{$normalizedPhone}");

        $user = User::find($cached['user_id']);
        if (!$user) {
            $this->error = 'Account not found. Please contact support.';
            return;
        }

        Auth::login($user);
        session(['spar_meds_session' => true]);

        Log::channel('spar_audit')->info('Patient OTP login', [
            'user_id' => $user->id,
            'phone' => $normalizedPhone,
            'ip' => request()->ip(),
        ]);

        $this->redirect(route('my-meds.dashboard'));
    }

    /**
     * Resend OTP.
     */
    public function resendOtp(): void
    {
        $this->step = 'phone';
        $this->otp = '';
        $this->error = '';
        $this->sendOtp();
    }

    /**
     * Go back to phone entry.
     */
    public function back(): void
    {
        $this->step = 'phone';
        $this->otp = '';
        $this->error = '';
    }

    /**
     * Dispatch OTP via SMS. Currently logs in dev mode.
     */
    private function dispatchOtp(string $phone, string $code): void
    {
        // TODO: Replace with BulkSMS/WhatsApp when configured
        Log::info("SPAR My Meds OTP: {$code} → {$phone}");

        if (app()->environment('local', 'staging')) {
            session()->flash('dev_otp', $code);
        }
    }

    /**
     * Normalize a South African phone number.
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Convert +27 to 0
        if (str_starts_with($phone, '+27')) {
            $phone = '0' . substr($phone, 3);
        }
        // Convert 27 to 0
        if (str_starts_with($phone, '27') && strlen($phone) === 11) {
            $phone = '0' . substr($phone, 2);
        }

        return $phone;
    }

    /**
     * Mask phone for display (e.g., 082****567).
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 7) return $phone;
        return substr($phone, 0, 3) . '****' . substr($phone, -3);
    }

    public function render()
    {
        return view('livewire.spar.my-meds-login')
            ->layout('layouts.spar-meds');
    }
}
