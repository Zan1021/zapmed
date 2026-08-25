<div class="max-w-sm mx-auto">
    <!-- Logo / Welcome -->
    <div class="text-center mb-8 mt-4">
        <div class="w-16 h-16 spar-bg rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900">Track Your Medication</h2>
        <p class="text-sm text-gray-500 mt-1">Enter your phone number to view your prescriptions.</p>
    </div>

    <!-- Dev OTP display -->
    @if(session('dev_otp'))
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-center">
            <p class="text-xs text-amber-600">DEV MODE — Your OTP code:</p>
            <p class="text-2xl font-mono font-bold text-amber-800 mt-1">{{ session('dev_otp') }}</p>
        </div>
    @endif

    <!-- Error -->
    @if($error)
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
            <p class="text-sm text-red-700">{{ $error }}</p>
        </div>
    @endif

    <!-- Step 1: Phone Number -->
    @if($step === 'phone')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <form wire:submit="sendOtp">
                <label class="block text-sm font-medium text-gray-700 mb-2">Mobile Number</label>
                <input type="tel" wire:model="phone" placeholder="082 123 4567" autofocus
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl text-lg text-center tracking-wider focus:ring-2 focus:ring-green-500 focus:border-green-500 placeholder:text-gray-300" />
                @error('phone') <p class="text-xs text-red-600 mt-2">{{ $message }}</p> @enderror

                <button type="submit" wire:loading.attr="disabled"
                    class="w-full mt-4 py-3 px-4 bg-green-600 text-white font-semibold rounded-xl hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition disabled:opacity-50">
                    <span wire:loading.remove wire:target="sendOtp">Send Verification Code</span>
                    <span wire:loading wire:target="sendOtp">Sending...</span>
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">We'll send a one-time code to verify your identity.<br>No password needed.</p>
    @endif

    <!-- Step 2: Verify OTP -->
    @if($step === 'verify')
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="text-center mb-4">
                <p class="text-sm text-gray-600">Code sent to <span class="font-medium text-gray-900">{{ $maskedPhone }}</span></p>
            </div>

            <form wire:submit="verifyOtp">
                <label class="block text-sm font-medium text-gray-700 mb-2 text-center">Enter 6-digit code</label>
                <input type="text" wire:model="otp" maxlength="6" placeholder="000000" autofocus inputmode="numeric" pattern="[0-9]*"
                    class="w-full px-4 py-4 border border-gray-200 rounded-xl text-2xl text-center font-mono tracking-[0.5em] focus:ring-2 focus:ring-green-500 focus:border-green-500 placeholder:text-gray-200" />
                @error('otp') <p class="text-xs text-red-600 mt-2 text-center">{{ $message }}</p> @enderror

                <button type="submit" wire:loading.attr="disabled"
                    class="w-full mt-4 py-3 px-4 bg-green-600 text-white font-semibold rounded-xl hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition disabled:opacity-50">
                    <span wire:loading.remove wire:target="verifyOtp">Verify & Continue</span>
                    <span wire:loading wire:target="verifyOtp">Verifying...</span>
                </button>
            </form>

            <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                <button wire:click="back" class="text-sm text-gray-500 hover:text-gray-700">Change number</button>
                <button wire:click="resendOtp" class="text-sm text-green-600 hover:text-green-700 font-medium">Resend code</button>
            </div>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">Code expires in 10 minutes.</p>
    @endif
</div>
