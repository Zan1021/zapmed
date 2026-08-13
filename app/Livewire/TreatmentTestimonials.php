<?php

namespace App\Livewire;

use App\Models\Testimonial;
use Livewire\Component;

class TreatmentTestimonials extends Component
{
    public string $category;
    public int $limit = 6;

    public function render()
    {
        $testimonials = Testimonial::displayable()
            ->forCategory($this->category)
            ->with('patient')
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->limit($this->limit)
            ->get();

        $avgRating = Testimonial::displayable()
            ->forCategory($this->category)
            ->avg('rating');

        $totalCount = Testimonial::displayable()
            ->forCategory($this->category)
            ->count();

        // Check if this is a sensitive category
        $isSensitive = in_array($this->category, [
            'mens-health',
            'sexual-health',
            'womens-health',
        ]);

        return view('livewire.treatment-testimonials', [
            'testimonials' => $testimonials,
            'avgRating' => round($avgRating, 1),
            'totalCount' => $totalCount,
            'isSensitive' => $isSensitive,
        ]);
    }
}
