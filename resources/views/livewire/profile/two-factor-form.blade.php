<?php

use App\Services\SmsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $twoFactorEnabled = false;
    public string $confirmationCode = '';
    public bool $confirming = false;
    public bool $disabling = false;

    public function mount(): void
    {
        $this->twoFactorEnabled = Auth::user()->two_factor_enabled ?? false;
    }

    /**
     * Start the enable 2FA flow — sends a test OTP.
     */
    public function enableTwoFactor(): void
    {
        $user = Auth::user();

        if (!$user->phone) {
            session()->flash('two-factor-error', 'You need a phone number on your profile before enabling 2FA.');
            return;
        }

        // Generate and send a test code
        $code = $user->generateTwoFactorCode();
        app(SmsService::class)->sendOtp($user->phone, $code);

        $this->confirming = true;
        session()->flash('two-factor-status', 'A verification code has been sent to your phone. Enter it below to confirm.');
    }

    /**
     * Confirm and activate 2FA.
     */
    public function confirmEnable(): void
    {
        $this->validate([
            'confirmationCode' => 'required|string|size:6',
        ]);

        $user = Auth::user();

        if ($user->two_factor_code !== $this->confirmationCode) {
            $this->addError('confirmationCode', 'Invalid code. Please try again.');
            return;
        }

        if ($user->two_factor_expires_at && $user->two_factor_expires_at->isPast()) {
            $this->addError('confirmationCode', 'Code expired. Please start over.');
            $this->confirming = false;
            return;
        }

        // Enable 2FA
        $user->update(['two_factor_enabled' => true]);
        $user->clearTwoFactorCode();

        $this->twoFactorEnabled = true;
        $this->confirming = false;
        $this->confirmationCode = '';

        // Mark current session as verified
        session(['two_factor_verified' => true]);

        session()->flash('two-factor-status', 'Two-factor authentication has been enabled.');
    }

    /**
     * Disable 2FA.
     */
    public function disableTwoFactor(): void
    {
        $user = Auth::user();

        $user->update(['two_factor_enabled' => false]);
        $user->clearTwoFactorCode();

        $this->twoFactorEnabled = false;
        $this->disabling = false;

        // Clear session flag
        session()->forget('two_factor_verified');

        session()->flash('two-factor-status', 'Two-factor authentication has been disabled.');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Two-Factor Authentication') }}
        </h2>
        <p class="mt-1 text-sm text-gray-600">
            {{ __('Add an extra layer of security by requiring a verification code sent to your phone on each login.') }}
        </p>
    </header>

    <!-- Status Messages -->
    @if(session('two-factor-status'))
        <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-sm text-green-700">{{ session('two-factor-status') }}</p>
        </div>
    @endif
    @if(session('two-factor-error'))
        <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-sm text-red-700">{{ session('two-factor-error') }}</p>
        </div>
    @endif

    <div class="mt-6">
        @if($twoFactorEnabled)
            <!-- 2FA is enabled -->
            <div class="flex items-center space-x-3 p-4 bg-green-50 rounded-lg border border-green-200">
                <svg class="w-6 h-6 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-medium text-green-800">Two-factor authentication is enabled</p>
                    <p class="text-xs text-green-600 mt-0.5">A verification code will be required on each login.</p>
                </div>
            </div>

            @if($disabling)
                <div class="mt-4 p-4 bg-red-50 rounded-lg border border-red-200">
                    <p class="text-sm text-red-700 mb-3">Are you sure you want to disable two-factor authentication? This will make your account less secure.</p>
                    <div class="flex space-x-3">
                        <x-danger-button wire:click="disableTwoFactor">
                            {{ __('Yes, Disable 2FA') }}
                        </x-danger-button>
                        <x-secondary-button wire:click="$set('disabling', false)">
                            {{ __('Cancel') }}
                        </x-secondary-button>
                    </div>
                </div>
            @else
                <div class="mt-4">
                    <x-danger-button wire:click="$set('disabling', true)">
                        {{ __('Disable Two-Factor Authentication') }}
                    </x-danger-button>
                </div>
            @endif

        @elseif($confirming)
            <!-- Confirming enable -->
            <div class="p-4 bg-amber-50 rounded-lg border border-amber-200">
                <p class="text-sm text-amber-800 mb-3">Enter the 6-digit code sent to your phone to confirm enabling 2FA.</p>
                <form wire:submit="confirmEnable" class="flex items-end space-x-3">
                    <div class="flex-1 max-w-[200px]">
                        <x-text-input wire:model="confirmationCode" type="text" maxlength="6"
                            inputmode="numeric" pattern="[0-9]*"
                            class="w-full text-center text-lg tracking-widest font-mono"
                            placeholder="000000" autofocus />
                        <x-input-error :messages="$errors->get('confirmationCode')" class="mt-1" />
                    </div>
                    <x-primary-button>
                        {{ __('Confirm') }}
                    </x-primary-button>
                    <x-secondary-button wire:click="$set('confirming', false)">
                        {{ __('Cancel') }}
                    </x-secondary-button>
                </form>
            </div>

        @else
            <!-- 2FA is disabled -->
            <div class="flex items-center space-x-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <svg class="w-6 h-6 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-700">Two-factor authentication is not enabled</p>
                    <p class="text-xs text-gray-500 mt-0.5">We recommend enabling 2FA for all doctor and admin accounts.</p>
                </div>
            </div>

            <div class="mt-4">
                <x-primary-button wire:click="enableTwoFactor">
                    {{ __('Enable Two-Factor Authentication') }}
                </x-primary-button>
            </div>
        @endif
    </div>
</section>
