<?php

namespace Zapmed\SparCore\Http\Middleware;

use Zapmed\SparCore\Services\SparPatientSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards no-login SPAR patient pages (spec FR-9). Requires an active,
 * OTP-verified (if enabled) SPAR patient session AND granted consent — no PHI
 * page is reachable before the consent gate is passed (spec FR-7.2).
 */
class EnsureSparPatientSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = app(SparPatientSession::class);

        if (!$session->isActive()) {
            return redirect()->route('my-meds.track');
        }

        $patient = $session->patient();
        if (!$patient || !$patient->hasConsented()) {
            // Consent gate not passed — send back to the tracker (consent step).
            return redirect()->route('my-meds.track');
        }

        return $next($request);
    }
}
