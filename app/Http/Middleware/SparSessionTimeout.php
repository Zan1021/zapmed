<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces a stricter session timeout for pharmacy staff (30 minutes).
 * SPAR pharmacy terminals should not stay logged in indefinitely.
 *
 * For other roles, the default Laravel session lifetime applies.
 */
class SparSessionTimeout
{
    private const PHARMACY_TIMEOUT_MINUTES = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== UserRole::PharmacyStaff) {
            return $next($request);
        }

        $lastActivity = session('spar_last_activity');

        if ($lastActivity && now()->diffInMinutes($lastActivity) > self::PHARMACY_TIMEOUT_MINUTES) {
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
