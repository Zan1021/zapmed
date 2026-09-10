<?php

namespace Zapmed\SparCore\Http\Middleware;

use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces a stricter idle-session timeout for SPAR pharmacy staff. Pharmacy
 * terminals should not stay logged in indefinitely.
 *
 * The "is this a pharmacy staff actor" decision is made via the host's
 * SparIdentityProvider (a non-null pharmacy scope), not a host role enum, so
 * the middleware is host-agnostic. Non-scoped actors keep the default session
 * lifetime. Timeout is configurable via spar.session_timeout_minutes.
 */
class SparSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return $next($request);
        }

        // Livewire component updates are AJAX round-trips, not fresh user
        // navigations. Writing to the session on every one of them churns the
        // session store and, under file sessions on a single-process dev
        // server, can race the CSRF token the page already holds — surfacing
        // as a spurious 419 "This page has expired". Idle-timeout tracks page
        // activity, so skip Livewire's own update calls.
        if ($request->is('livewire/*')) {
            return $next($request);
        }

        // Only scoped pharmacy-staff sessions get the stricter timeout.
        $pharmacyId = app(SparIdentityProvider::class)->currentPharmacyId();
        if ($pharmacyId === null) {
            return $next($request);
        }

        $timeout = (int) config('spar.session_timeout_minutes', 30);
        $lastActivity = session('spar_last_activity');

        // Carbon 3 returns a signed diff; use absolute minutes elapsed.
        if ($lastActivity && now()->diffInMinutes($lastActivity, true) > $timeout) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Your session has expired for security reasons. Please log in again.');
        }

        // Update last activity timestamp
        session(['spar_last_activity' => now()]);

        return $next($request);
    }
}
