<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginController extends Controller
{
    /**
     * Redirect to the social provider.
     */
    public function redirect(string $provider)
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the callback from the social provider.
     */
    public function callback(string $provider)
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->with('status', 'Unable to authenticate with ' . ucfirst($provider) . '. Please try again.');
        }

        // Find existing user by email or create new one
        $user = User::where('email', $socialUser->getEmail())->first();

        if ($user) {
            // Existing user — log them in
            Auth::login($user, remember: true);
        } else {
            // New user — create account
            $nameParts = explode(' ', $socialUser->getName(), 2);

            $user = User::create([
                'first_name' => $nameParts[0] ?? '',
                'last_name' => $nameParts[1] ?? '',
                'email' => $socialUser->getEmail(),
                'password' => Hash::make(Str::random(24)),
                'role' => UserRole::Patient,
                'is_active' => true,
                'email_verified_at' => now(), // Social login = verified email
            ]);

            Auth::login($user, remember: true);
        }

        // Record login
        $user->recordLogin(request()->ip());

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Validate the provider is supported.
     */
    private function validateProvider(string $provider): void
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            abort(404);
        }
    }
}
