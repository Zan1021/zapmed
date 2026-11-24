<?php

namespace App\Livewire\Admin;

use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Operations\StaffAdmin;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/** User Admin for one person (Craig's guide 6.4): their role and their staff profile. */
class StaffProfileEditor extends Component
{
    use WithFileUploads;

    public User $user;
    public string $role = '';
    public string $registrationNumber = '';
    public string $board = '';
    public string $practiceAddress = '';
    public string $registrationStatus = 'pending';
    public string $country = 'ZA';
    public string $bookingUrl = '';
    public string $authorisationBookingUrl = '';
    public $signature = null;
    public bool $saved = false;

    public function mount(User $user): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $this->user = $user;
        $this->role = $user->role->value;
        $p = StaffProfile::where('user_id', $user->id)->first();
        if ($p) {
            $this->registrationNumber = (string) $p->registration_number;
            $this->board = (string) $p->board;
            $this->practiceAddress = (string) $p->practice_address;
            $this->registrationStatus = $p->registration_status;
            $this->country = $p->country;
            $this->bookingUrl = (string) $p->booking_url;
            $this->authorisationBookingUrl = (string) $p->authorisation_booking_url;
        }
    }

    public function assignRole(StaffAdmin $admin): void
    {
        $this->resetErrorBag();
        $this->saved = false;

        $this->user = $admin->assignRole(Auth::user(), $this->user, $this->role);
        $this->saved = true;
    }

    public function save(StaffAdmin $admin): void
    {
        $this->resetErrorBag();
        $this->saved = false;

        $admin->saveProfile(Auth::user(), $this->user, [
            'registration_number' => $this->registrationNumber, 'board' => $this->board, 'practice_address' => $this->practiceAddress,
            'registration_status' => $this->registrationStatus, 'country' => $this->country,
            'booking_url' => $this->bookingUrl, 'authorisation_booking_url' => $this->authorisationBookingUrl,
        ]);
        $this->saved = true;
    }

    public function uploadSignature(StaffAdmin $admin): void
    {
        $this->resetErrorBag();
        $this->validate(['signature' => ['required', 'file']], ['signature.required' => 'Choose an image first.']);

        $admin->uploadSignature(Auth::user(), $this->user, $this->signature);
        $this->reset('signature');
        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.admin.staff-profile-editor', [
            'profile' => StaffProfile::where('user_id', $this->user->id)->first(),
            'roles' => config('operations.staff.assignable_roles'),
            'statuses' => config('operations.staff.registration_statuses'),
            'boards' => config('operations.staff.boards'),
            'isDoctor' => $this->user->role === \App\Enums\UserRole::Doctor,
            'isStaff' => in_array($this->user->role, [\App\Enums\UserRole::Admin, \App\Enums\UserRole::Doctor, \App\Enums\UserRole::HealthCoach], true),
        ])->layout('layouts.app');
    }
}
