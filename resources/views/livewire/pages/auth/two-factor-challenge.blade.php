<?php

use App\Services\SmsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $code = '';
    public bool $codeSent = false;
    public string $maskedPhone = '';

    public function mount(): void
    {
        $user = Auth::user();

        if (!$user || !$user->two_factor_enabled) {
            $this->redirect(route('dashboard'), navigate: true);
            return;
        }

        // Mask the phone number for display
        $phone = $user->phone ?? '';
        if (strlen($phone) >= 4) {
            $this->maskedPhone = str_repeat('*', strlen($phone) - 4) . substr($phone, -4);
        }

        // Send the OTP code on page load
        $this->sendCode();
    }

    /**
     * Send or resend the OTP code.
     */
    public function sendCode(): void
    {
        $user = Auth::user();

        // Rate limit resends
        $key = 'two-factor-send:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            session()->flash('error', "Too many attempts. Please wait {$seconds} seconds.");
            return;
        }

        RateLimiter::hit($key, 120); // 3 attempts per 2 minutes

        // Generate a fresh code
        $otp = $user->generateTwoFactorCode();

        // Send via SMS
        $sms = app(SmsService::class);
        $sms->sendOtp($user->phone, $otp);

        $this->codeSent = true;
        session()->flash('status', 'Verification code sent to your phone.');
    }

    /**
     * Verify the submitted OTP code.
     */
    public function verify(): void
    {
        $this->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();

        // Check if code matches and hasn't expired
        if ($user->two_factor_code !== $this->code) {
            throw ValidationException::withMessages([
                'code' => 'Invalid verification code.',
            ]);
        }

        if ($user->two_factor_expires_at && $user->two_factor_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => 'Code has expired. Please request a new one.',
            ]);
        }

        // Success — mark session as verified
        session(['two_factor_verified' => true]);

        // Clear the code
        $user->clearTwoFactorCode();

        // Clear rate limiter
        RateLimiter::clear('two-factor-send:' . $user->id);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 text-center">
        <div class="w-14 h-14 bg-zapmed-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-zapmed-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <h2 class="text-lg font-semibold text-gray-900">Two-Factor Verification</h2>
        <p class="text-sm text-gray-500 mt-1">
            Enter the 6-digit code sent to <span class="font-medium">{{ $maskedPhone }}</span>
        </p>
    </div>

    <!-- Flash Messages -->
    @if(session('status'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-sm text-green-700">{{ session('status') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    <form wire:submit="verify">
        <!-- OTP Code Input -->
        <div>
            <x-input-label for="code" :value="__('Verification Code')" />
            <x-text-input wire:model="code" id="code" class="block mt-1 w-full text-center text-2xl tracking-[0.5em] font-mono"
                type="text" maxlength="6" inputmode="numeric" pattern="[0-9]*"
                placeholder="000000" required autofocus autocomplete="one-time-code" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="mt-6">
            <x-primary-button class="w-full justify-center">
                {{ __('Verify') }}
            </x-primary-button>
        </div>
    </form>

    <!-- Resend & Logout -->
    <div class="mt-6 flex items-center justify-between">
        <button wire:click="sendCode" class="text-sm text-zapmed-600 hover:text-zapmed-800 font-medium">
            Resend Code
        </button>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">
                Cancel &amp; Logout
            </button>
        </form>
    </div>

    <p class="mt-4 text-xs text-gray-400 text-center">
        Code expires in 10 minutes. Check your SMS inbox.
    </p>
</div>
