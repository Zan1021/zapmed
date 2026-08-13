<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDoctorHasAvailability
{
    /**
     * Redirect doctors to availability setup if they have no slots configured.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isDoctor() && $user->doctorProfile) {
            $hasAvailability = $user->doctorProfile->availabilities()->active()->exists();

            if (!$hasAvailability && !$request->routeIs('doctor.availability')) {
                return redirect()->route('doctor.availability')
                    ->with('warning', 'Please set your availability schedule so patients can book consultations with you.');
            }
        }

        return $next($request);
    }
}
