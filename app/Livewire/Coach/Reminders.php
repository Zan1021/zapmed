<?php

namespace App\Livewire\Coach;

use App\Models\CoachProfile;
use App\Services\Coach\CycleCalendar;
use App\Services\Coach\ReminderSender;
use App\Services\Coach\ReminderSettings;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Reminders (design screen 11). Every change saves at once. Preview shows the text on screen and sends nothing.
 */
class Reminders extends Component
{
    public ?string $error = null;

    public function toggle(string $type, ReminderSettings $settings): void
    {
        try {
            $settings->toggle(Auth::user(), $type);
            $this->error = null;
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function setValue(string $type, string $key, string $value, ReminderSettings $settings): void
    {
        try {
            $settings->set(Auth::user(), $type, $key, $value);
            $this->error = null;
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function preview(string $type, ReminderSender $sender): void
    {
        $text = $sender->body($type);

        if ($text !== '') {
            $this->dispatch('coach-toast', message: 'Zapmed: '.$text);
        }
    }

    public function render(ReminderSettings $settings)
    {
        $user = Auth::user();
        $profile = CoachProfile::where('user_id', $user->id)->first();

        return view('livewire.coach.reminders', [
            's' => $settings->all($user),
            'o' => config('coach.reminder_options'),
            'cycleOn' => CycleCalendar::enabled($profile),
        ])->layout('layouts.coach', ['title' => 'Reminders', 'hideTabs' => true]);
    }
}