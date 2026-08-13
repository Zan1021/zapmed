<?php

namespace App\Livewire\Patient;

use App\Models\Consultation;
use App\Models\Testimonial;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TestimonialPopup extends Component
{
    public bool $showPopup = false;
    public ?Consultation $pendingConsultation = null;

    // Form fields
    public int $rating = 0;
    public string $comment = '';
    public bool $wouldRecommend = true;
    public bool $showName = false;

    // State
    public bool $submitted = false;

    public function mount(): void
    {
        // Find the most recent completed consultation without a testimonial
        $this->pendingConsultation = Consultation::where('patient_id', Auth::id())
            ->where('status', 'completed')
            ->whereDoesntHave('testimonial')
            ->where('completed_at', '>=', now()->subDays(7)) // Only ask within 7 days
            ->orderByDesc('completed_at')
            ->first();

        if ($this->pendingConsultation) {
            $this->showPopup = true;
        }
    }

    public function setRating(int $stars): void
    {
        $this->rating = $stars;
    }

    public function submit(): void
    {
        $this->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:500',
        ]);

        $appointment = $this->pendingConsultation->appointment;
        $treatmentCategory = $appointment->type ?? 'general';
        $treatmentSlug = null;

        // Try to get the treatment slug from the assessment
        $assessment = $this->pendingConsultation->appointment->assessment;
        if ($assessment) {
            $treatmentSlug = $assessment->treatment_slug;
            // Map treatment slug back to category
            foreach (config('treatments', []) as $categorySlug => $category) {
                if (isset($category['treatments'][$treatmentSlug])) {
                    $treatmentCategory = $categorySlug;
                    break;
                }
            }
        }

        // Auto-approve 4-5 star reviews
        $autoApprove = $this->rating >= 4;

        Testimonial::create([
            'patient_id' => Auth::id(),
            'consultation_id' => $this->pendingConsultation->id,
            'treatment_category' => $treatmentCategory,
            'treatment_slug' => $treatmentSlug,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'would_recommend' => $this->wouldRecommend,
            'show_name' => $this->showName,
            'is_approved' => $autoApprove,
        ]);

        $this->submitted = true;
    }

    public function dismiss(): void
    {
        $this->showPopup = false;
    }

    public function render()
    {
        return view('livewire.patient.testimonial-popup');
    }
}
