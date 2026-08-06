<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    /**
     * Redirect patients to onboarding if they haven't completed it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isPatient() && !$user->hasCompletedOnboarding()) {
            // Don't redirect if already on the onboarding page
            if (!$request->routeIs('patient.onboarding')) {
                return redirect()->route('patient.onboarding');
            }
        }

        return $next($request);
    }
}
