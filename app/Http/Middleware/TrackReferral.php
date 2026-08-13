<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use App\Models\Referral;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackReferral
{
    /**
     * Track partner referrals via ?ref= parameter or existing cookie.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $refSlug = $request->query('ref');

        if ($refSlug && !$request->cookie('zapmed_ref')) {
            $partner = Partner::active()->where('slug', $refSlug)->first();

            if ($partner) {
                // Create referral record
                $referral = Referral::create([
                    'partner_id' => $partner->id,
                    'landing_url' => $request->fullUrl(),
                    'source_url' => $request->header('referer'),
                    'ip_address' => $request->ip(),
                    'status' => 'clicked',
                    'cookie_expires_at' => now()->addDays($partner->cookie_days),
                ]);

                // Set cookie and continue
                $response = $next($request);

                $cookieMinutes = $partner->cookie_days * 24 * 60;

                return $response->withCookie(cookie(
                    'zapmed_ref',
                    $partner->slug . '|' . $referral->id,
                    $cookieMinutes,
                    '/',
                    null,
                    true,  // secure
                    true,  // httpOnly
                    false, // raw
                    'lax'  // sameSite
                ));
            }
        }

        return $next($request);
    }
}
