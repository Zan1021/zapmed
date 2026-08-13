<?php

namespace App\Livewire\Patient;

use App\Models\Consultation;
use App\Models\DoctorProfile;
use App\Models\Testimonial;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class LeaveReview extends Component
{
    public Consultation $consultation;
    public int $rating = 5;
    public string $comment = '';
    public bool $wouldRecommend = true;
    public bool $showName = false;
    public bool $submitted = false;
    public bool $alreadyReviewed = false;

    public function mount(Consultation $consultation): void
    {
        // Ensure patient owns this consultation
        if ($consultation->patient_id !== Auth::id()) {
            abort(403);
        }

        // Check if already reviewed
        $this->alreadyReviewed = Testimonial::where('consultation_id', $consultation->id)->exists();
        $this->consultation = $consultation;
    }

    public function submit(): void
    {
        if ($this->alreadyReviewed) {
            return;
        }

        $this->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1000',
            'wouldRecommend' => 'boolean',
            'showName' => 'boolean',
        ]);

        // Determine treatment category from the appointment
        $appointment = $this->consultation->appointment;
        $treatmentCategory = $appointment->type ?? 'general-health';

        Testimonial::create([
            'patient_id' => Auth::id(),
            'doctor_id' => $this->consultation->doctor_id,
            'consultation_id' => $this->consultation->id,
            'treatment_category' => $treatmentCategory,
            'treatment_slug' => null,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'would_recommend' => $this->wouldRecommend,
            'show_name' => $this->showName,
            'is_approved' => false, // requires admin moderation
            'is_featured' => false,
        ]);

        // Recalculate doctor's rating
        $doctorProfile = DoctorProfile::where('user_id', $this->consultation->doctor_id)->first();
        if ($doctorProfile) {
            $doctorProfile->recalculateRating();
        }

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.patient.leave-review');
    }
}
