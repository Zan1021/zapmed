<?php

namespace App\Livewire\Patient;

use App\Services\Patient\PatientChannels;
use App\Services\Patient\PatientInbox;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Notifications tab (Craig's guide 3.1, 3.3): choose WhatsApp or email, record consent, see what your care team asked of you. */
class Notifications extends Component
{
    public string $preferred = 'email';
    public bool $consent = false;
    public bool $saved = false;

    public function mount(PatientChannels $channels): void
    {
        $user = Auth::user();
        $this->preferred = $channels->preferred($user);
        $this->consent = $channels->whatsappConsent($user);
    }

    public function save(PatientChannels $channels): void
    {
        $this->resetErrorBag();
        $this->saved = false;

        $channels->save(Auth::user(), $this->preferred, $this->consent);

        $this->saved = true;
    }

    public function render(PatientChannels $channels, PatientInbox $inbox)
    {
        $user = Auth::user();

        return view('livewire.patient.notifications', [
            'items' => $inbox->items($user),
            'hasPhone' => filled($user->phone),
            'wording' => config('patient.notifications.whatsapp_wording'),
            'whatsappReady' => $channels->usable($user, 'whatsapp'),
            'order' => $channels->order($user),
        ])->layout('layouts.app');
    }
}
