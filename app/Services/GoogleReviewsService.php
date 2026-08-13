<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleReviewsService
{
    /**
     * Fetch Google reviews for the business.
     * Cached for 24 hours (Google reviews don't change that often).
     *
     * To set up:
     * 1. Get a Google Places API key from console.cloud.google.com
     * 2. Find your Place ID at: https://developers.google.com/maps/documentation/places/web-service/place-id
     * 3. Set GOOGLE_PLACES_API_KEY and GOOGLE_PLACE_ID in .env
     */
    public function getReviews(int $limit = 5): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        return Cache::remember('google_reviews', now()->addHours(24), function () {
            try {
                $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/place/details/json', [
                    'place_id' => config('services.google.place_id'),
                    'fields' => 'reviews,rating,user_ratings_total',
                    'key' => config('services.google.places_api_key'),
                    'language' => 'en',
                ]);

                if (!$response->ok()) {
                    return [];
                }

                $data = $response->json();
                $result = $data['result'] ?? [];

                return [
                    'rating' => $result['rating'] ?? 0,
                    'total_reviews' => $result['user_ratings_total'] ?? 0,
                    'reviews' => collect($result['reviews'] ?? [])
                        ->map(fn ($r) => [
                            'author' => $r['author_name'] ?? 'Anonymous',
                            'rating' => $r['rating'] ?? 5,
                            'text' => $r['text'] ?? '',
                            'time' => $r['relative_time_description'] ?? '',
                            'profile_photo' => $r['profile_photo_url'] ?? null,
                        ])
                        ->toArray(),
                ];
            } catch (\Exception $e) {
                return [];
            }
        });
    }

    /**
     * Get just the overall rating + count.
     */
    public function getRatingSummary(): array
    {
        $data = $this->getReviews();
        return [
            'rating' => $data['rating'] ?? 0,
            'total' => $data['total_reviews'] ?? 0,
        ];
    }

    /**
     * Clear cached reviews (force refresh).
     */
    public function clearCache(): void
    {
        Cache::forget('google_reviews');
    }

    public function isConfigured(): bool
    {
        return !empty(config('services.google.places_api_key'))
            && !empty(config('services.google.place_id'));
    }
}
