<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class AvatarUpload extends Component
{
    use WithFileUploads;

    public $avatar;
    public $currentAvatarUrl;

    public function mount()
    {
        $this->currentAvatarUrl = auth()->user()->avatar_url;
    }

    public function updatedAvatar()
    {
        $this->validate([
            'avatar' => 'image|max:2048|mimes:jpg,jpeg,png,webp',
        ]);

        $user = auth()->user();

        // Delete old avatar if exists
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        // Store new avatar
        $path = $this->avatar->store('avatars', 'public');

        // Update user
        $user->update(['avatar_path' => $path]);

        $this->currentAvatarUrl = $user->fresh()->avatar_url;
        $this->avatar = null;

        $this->dispatch('avatar-updated');
        session()->flash('avatar-success', 'Profile photo updated successfully.');
    }

    public function removeAvatar()
    {
        $user = auth()->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        $this->currentAvatarUrl = null;
        session()->flash('avatar-success', 'Profile photo removed.');
    }

    public function render()
    {
        return view('livewire.avatar-upload');
    }
}
