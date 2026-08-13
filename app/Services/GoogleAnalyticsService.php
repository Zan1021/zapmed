<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleAnalyticsService
{
    /**
     * Check if Google Analytics is configured.
     */
    public function isConfigured(): bool
    {
        return !empty(config('analytics.measurement_id'));
    }

    /**
     * Check if the Data API is configured (for admin dashboard).
     */
    public function isDataApiConfigured(): bool
    {
        return !empty(config('analytics.property_id'))
            && !empty(env('GOOGLE_APPLICATION_CREDENTIALS'));
    }

    /**
     * Get PageSpeed Insights score for a URL.
     * Cached for 24 hours.
     */
    public function getPageSpeedScore(string $url, string $strategy = 'mobile'): ?array
    {
        $apiKey = config('analytics.pagespeed_api_key');

        if (!$apiKey) {
            return null;
        }

        $cacheKey = 'pagespeed_' . md5($url . $strategy);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($url, $apiKey, $strategy) {
            try {
                $response = Http::timeout(30)->get('https://www.googleapis.com/pagespeedonline/v5/runPagespeed', [
                    'url' => $url,
                    'key' => $apiKey,
                    'strategy' => $strategy,
                    'category' => ['performance', 'accessibility', 'best-practices', 'seo'],
                ]);

                if (!$response->ok()) {
                    return null;
                }

                $data = $response->json();
                $categories = $data['lighthouseResult']['categories'] ?? [];

                return [
                    'url' => $url,
                    'strategy' => $strategy,
                    'performance' => round(($categories['performance']['score'] ?? 0) * 100),
                    'accessibility' => round(($categories['accessibility']['score'] ?? 0) * 100),
                    'best_practices' => round(($categories['best-practices']['score'] ?? 0) * 100),
                    'seo' => round(($categories['seo']['score'] ?? 0) * 100),
                    'fetched_at' => now()->toIso8601String(),
                ];
            } catch (\Exception $e) {
                return null;
            }
        });
    }

    /**
     * Get PageSpeed scores for all monitored URLs.
     */
    public function getAllPageSpeedScores(): array
    {
        $urls = config('analytics.monitored_urls', []);
        $results = [];

        foreach ($urls as $url) {
            $mobile = $this->getPageSpeedScore($url, 'mobile');
            if ($mobile) {
                $results[] = $mobile;
            }
        }

        return $results;
    }

    /**
     * Clear cached PageSpeed scores (to force refresh).
     */
    public function clearPageSpeedCache(): void
    {
        $urls = config('analytics.monitored_urls', []);
        foreach ($urls as $url) {
            Cache::forget('pagespeed_' . md5($url . 'mobile'));
            Cache::forget('pagespeed_' . md5($url . 'desktop'));
        }
    }
}
