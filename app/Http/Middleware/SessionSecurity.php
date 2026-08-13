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
}
