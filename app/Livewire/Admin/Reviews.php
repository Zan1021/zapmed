<?php

namespace App\Livewire\Admin;

use App\Models\DoctorProfile;
use App\Models\Testimonial;
use Livewire\Component;
use Livewire\WithPagination;

class Reviews extends Component
{
    use WithPagination;

    public string $filter = 'pending'; // pending, approved, all

    public function approve(int $id): void
    {
        $review = Testimonial::findOrFail($id);
        $review->update(['is_approved' => true]);

        // Recalculate doctor rating
        $profile = DoctorProfile::where('user_id', $review->doctor_id)->first();
        if ($profile) {
            $profile->recalculateRating();
        }

        session()->flash('message', 'Review approved.');
    }

    public function reject(int $id): void
    {
        $review = Testimonial::findOrFail($id);
        $review->update(['is_approved' => false]);

        // Recalculate doctor rating
        $profile = DoctorProfile::where('user_id', $review->doctor_id)->first();
        if ($profile) {
            $profile->recalculateRating();
        }

        session()->flash('message', 'Review rejected.');
    }

    public function feature(int $id): void
    {
        $review = Testimonial::findOrFail($id);
        $review->update(['is_featured' => !$review->is_featured]);
    }

    public function delete(int $id): void
    {
        $review = Testimonial::findOrFail($id);
        $doctorId = $review->doctor_id;
        $review->delete();

        // Recalculate
        $profile = DoctorProfile::where('user_id', $doctorId)->first();
        if ($profile) {
            $profile->recalculateRating();
        }

        session()->flash('message', 'Review deleted.');
    }

    public function getReviewsProperty()
    {
        $query = Testimonial::with(['patient', 'doctor', 'consultation'])
            ->orderByDesc('created_at');

        return match ($this->filter) {
            'pending' => $query->where('is_approved', false)->paginate(20),
            'approved' => $query->where('is_approved', true)->paginate(20),
            default => $query->paginate(20),
        };
    }

    public function getPendingCountProperty(): int
    {
        return Testimonial::where('is_approved', false)->count();
    }

    public function render()
    {
        return view('livewire.admin.reviews')
            ->layout('layouts.app');
    }
}
