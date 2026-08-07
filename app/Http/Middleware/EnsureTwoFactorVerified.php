<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    /**
     * Handle an incoming request.
     *
     * If the authenticated user has 2FA enabled but hasn't verified their OTP
     * in this session, redirect them to the verification page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // If 2FA is enabled and not yet verified this session
        if ($user->two_factor_enabled && !session('two_factor_verified')) {
            // Allow access to the 2FA verify page itself and logout
            if ($request->routeIs('two-factor.*') || $request->routeIs('logout')) {
                return $next($request);
            }

            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
