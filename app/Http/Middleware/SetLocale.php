<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Set the application locale based on:
     * 1. URL query param (?lang=zu) — also saves to session
     * 2. Session preference
     * 3. Cookie preference
     * 4. Default
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $enabledLanguages = Setting::enabledLanguages();
        } catch (\Exception $e) {
            // Table might not exist yet (fresh install, testing)
            $enabledLanguages = ['en'];
        }

        $locale = null;

        // 1. Check URL param (switching language)
        if ($request->has('lang')) {
            $requested = $request->query('lang');
            if (in_array($requested, $enabledLanguages)) {
                $locale = $requested;
                session(['locale' => $locale]);
            }
        }

        // 2. Check session
        if (!$locale && session('locale') && in_array(session('locale'), $enabledLanguages)) {
            $locale = session('locale');
        }

        // 3. Check cookie
        if (!$locale && $request->cookie('locale') && in_array($request->cookie('locale'), $enabledLanguages)) {
            $locale = $request->cookie('locale');
        }

        // 4. Default
        $locale = $locale ?? config('languages.default', 'en');

        app()->setLocale($locale);

        $response = $next($request);

        // Persist locale in cookie (30 days)
        if ($locale !== 'en') {
            $response->cookie('locale', $locale, 60 * 24 * 30);
        }

        return $response;
    }
}
