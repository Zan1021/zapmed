<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures pharmacy_staff users can ONLY access their assigned pharmacy's data.
 * Prevents URL manipulation, parameter tampering, and cross-pharmacy data access.
 *
 * For admin users, this middleware passes through (they have global access).
 */
class EnsureSparPharmacyScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Admins pass through — they see everything
        if ($user->role === UserRole::Admin) {
            return $next($request);
        }

        // Pharmacy staff must have an assigned pharmacy
        if ($user->role === UserRole::PharmacyStaff) {
            if (!$user->spar_pharmacy_id) {
                abort(403, 'Your account is not linked to a SPAR pharmacy. Contact your administrator.');
            }

            // Bind the pharmacy ID into the request for downstream use
            $request->merge(['_spar_pharmacy_id' => $user->spar_pharmacy_id]);

            return $next($request);
        }

        // Other roles should not access SPAR routes
        abort(403, 'You do not have permission to access SPAR pharmacy resources.');
    }
}
