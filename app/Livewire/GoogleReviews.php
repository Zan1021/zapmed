<?php

namespace App\Livewire;

use App\Services\GoogleReviewsService;
use Livewire\Component;

class GoogleReviews extends Component
{
    public function render()
    {
        $service = app(GoogleReviewsService::class);
        $data = $service->getReviews();

        return view('livewire.google-reviews', [
            'reviews' => $data['reviews'] ?? [],
            'overallRating' => $data['rating'] ?? 0,
            'totalReviews' => $data['total_reviews'] ?? 0,
            'isConfigured' => $service->isConfigured(),
        ]);
    }
}
