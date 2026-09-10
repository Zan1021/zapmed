<?php

namespace Zapmed\SparCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for SPAR responses (Phase 10.3, POPIA hardening).
 *
 * Portable (no host coupling). Applied by hosts via the route middleware groups
 * in config('spar.route_middleware.*') or globally. CSP is intentionally
 * conservative but allows the CDN Tailwind/Alpine the pilot uses; tighten to a
 * nonce-based policy before production if inline scripts are removed.
 *
 * NOTE re Phase 9 (parked): when ZapMed SSO embeds SPAR in a frame, relax
 * frame-ancestors here for the trusted ZapMed origin only.
 */
class SparSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-XSS-Protection' => '1; mode=block',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];

        // HSTS only over HTTPS (avoid locking out local http dev).
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        // Conservative CSP. self + the pilot CDNs; no framing by third parties.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval' 'unsafe-inline' https://cdn.tailwindcss.com https://unpkg.com",
            "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $headers['Content-Security-Policy'] = $csp;

        foreach ($headers as $key => $value) {
            if (!$response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }
}
