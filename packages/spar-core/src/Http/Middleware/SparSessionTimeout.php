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

        // Only scoped pharmacy-staff sessions get the stricter timeout.
        $pharmacyId = app(SparIdentityProvider::class)->currentPharmacyId();
        if ($pharmacyId === null) {
            return $next($request);
        }

        $timeout = (int) config('spar.session_timeout_minutes', 30);
        $lastActivity = session('spar_last_activity');

        if ($lastActivity && now()->diffInMinutes($lastActivity) > $timeout) {
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
