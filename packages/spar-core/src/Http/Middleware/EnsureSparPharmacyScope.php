<?php

namespace Zapmed\SparCore\Http\Middleware;

use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures a SPAR staff actor can only access their assigned pharmacy's data.
 * Prevents URL manipulation, parameter tampering, and cross-pharmacy access.
 *
 * The pharmacy scope is resolved through the host's SparIdentityProvider
 * (never off a host User/role enum directly), so this works identically in
 * ZapMed (integrated) and the standalone app:
 *   - unauthenticated              -> redirect to login
 *   - scoped staff (pharmacyId set)-> bind _spar_pharmacy_id, continue
 *   - global actor (pharmacyId null, e.g. admin) -> pass through
 *
 * Route-level role gating (e.g. ZapMed's role:pharmacy_staff) remains a host
 * responsibility; this middleware enforces the data-isolation scope.
 */
class EnsureSparPharmacyScope
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        $pharmacyId = app(SparIdentityProvider::class)->currentPharmacyId();

        if ($pharmacyId !== null) {
            // Scoped staff: bind the pharmacy id for downstream use.
            $request->merge(['_spar_pharmacy_id' => $pharmacyId]);
        }

        // Global actors (admin) resolve to a null pharmacy scope and pass through.
        return $next($request);
    }
}
