<?php

use App\Livewire\Auth\StaffLogin;
use App\Livewire\Admin\StaffManagement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
| Standalone SPAR host routes.
|
| The SPAR domain routes (patient tracker, pharmacy staff, admin) are shipped by
| the spar-core package (packages/spar-core/routes/spar.php) and loaded by its
| service provider. Middleware groups are configured for THIS host via
| config('spar.route_middleware.*') set below-package (see config/spar.php note).
|
| Here we only add host chrome: landing redirect, staff login/logout.
*/

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('spar.dashboard')
        : redirect()->route('staff.login');
})->name('home');

// Staff authentication (pharmacy_users guard — no telehealth User).
Route::middleware('guest')->group(function () {
    // Rate-limited to blunt brute-force / credential-stuffing (Phase 10.1).
    Route::get('login', StaffLogin::class)->middleware('throttle:20,1')->name('staff.login');
    // Alias used by package middleware redirects (->route('login')).
    Route::get('auth/login', fn () => redirect()->route('staff.login'))->name('login');
});

Route::post('logout', function () {
    Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('staff.login');
})->middleware('auth')->name('logout');

// Authenticated staff dashboard redirect target lives in the package
// (spar.dashboard). 'dashboard' alias for any generic redirect.
Route::get('dashboard', fn () => redirect()->route('spar.dashboard'))
    ->middleware('auth')->name('dashboard');

// Staff/admin account management (tiered, spec FR-15). Host-specific because it
// manages the standalone PharmacyUser identity model. Component enforces scope.
Route::get('admin/staff', StaffManagement::class)
    ->middleware('auth')->name('admin.staff');
