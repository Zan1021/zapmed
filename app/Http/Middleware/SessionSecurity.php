<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SessionSecurity
{
    /**
     * Enhanced session security:
     * - Auto-logout after 15 min inactivity
     * - Detect IP change mid-session (potential session hijacking)
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        // Exempt the charge-at-booking onboarding journey (/start/{slug}) from the inactivity
        // auto-logout. That flow legitimately takes a while (a long clinical assessment, reading,
        // slot picking) and logs the patient in PART-WAY through; an idle-logout mid-journey
        // regenerates the CSRF token and the next Livewire POST dies with a 419 "page expired".
        // The journey is a single short-lived booking, so the idle guard adds no security here.
        if ($this->isOnboardingJourney($request)) {
            session(['last_activity' => time()]);

            return $next($request);
        }

        // Check inactivity timeout (15 minutes)
        $lastActivity = session('last_activity');
        $timeout = 15 * 60; // 15 minutes in seconds

        if ($lastActivity && (time() - $lastActivity) > $timeout) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            return redirect()->route('login')->with('message', 'You were logged out due to inactivity.');
        }

        // Update last activity
        session(['last_activity' => time()]);

        // Track IP — warn if it changes mid-session (don't force logout, some users have rotating IPs)
        $sessionIp = session('session_ip');
        if (!$sessionIp) {
            session(['session_ip' => $request->ip()]);
        }

        return $next($request);
    }

    /**
     * Is this request part of the /start/{slug} booking journey?
     * Matches the page request itself and the Livewire AJAX updates it fires
     * (those POST to /livewire/update with the journey page as Referer).
     */
    private function isOnboardingJourney(Request $request): bool
    {
        if ($request->is('start/*')) {
            return true;
        }

        if ($request->is('livewire/update')) {
            $referer = (string) $request->headers->get('referer');
            if ($referer !== '' && preg_match('#/start/[^/?]+#', parse_url($referer, PHP_URL_PATH) ?? '')) {
                return true;
            }
        }

        return false;
    }
}
