<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Standalone pharmacy-staff login (design §5.3). Authenticates against the
 * pharmacy_users guard — there is no telehealth User table. Patients never
 * reach this screen; they use the no-login tracker.
 */
#[Layout('layouts.auth')]
class StaffLogin extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login(): void
    {
        $credentials = $this->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::guard('web')->attempt(
            array_merge($credentials, ['is_active' => true]),
            $this->remember
        )) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        session()->regenerate();
        session(['spar_last_activity' => now()]);

        $this->redirectRoute('spar.dashboard', navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.staff-login');
    }
}
