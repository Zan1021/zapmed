<?php

namespace App\Livewire\Doctor;

use App\Models\DoctorProfile;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class PublicProfile extends Component
{
    use WithPagination;

    public User $doctor;
    public DoctorProfile $profile;

    public function mount(User $doctor): void
    {
        if (!$doctor->doctorProfile || !$doctor->doctorProfile->is_verified) {
            abort(404);
        }

        $this->doctor = $doctor;
        $this->profile = $doctor->doctorProfile;
    }

    public function getReviewsProperty()
    {
        return Testimonial::where('doctor_id', $this->doctor->id)
            ->where('is_approved', true)
            ->orderByDesc('created_at')
            ->paginate(10);
    }

    public function getRatingBreakdownProperty(): array
    {
        $reviews = Testimonial::where('doctor_id', $this->doctor->id)
            ->where('is_approved', true)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $breakdown = [];
        for ($i = 5; $i >= 1; $i--) {
            $count = $reviews[$i] ?? 0;
            $breakdown[$i] = [
                'count' => $count,
                'percentage' => $this->profile->total_reviews > 0
                    ? round(($count / $this->profile->total_reviews) * 100)
                    : 0,
            ];
        }

        return $breakdown;
    }

    public function render()
    {
        return view('livewire.doctor.public-profile')
            ->layout('layouts.guest');
    }
}
