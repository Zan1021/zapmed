<?php

namespace App\Livewire\Patient;

use Livewire\Component;

/**
 * Community tab (Craig's guide 3.1). The guide asks for the tab only: "detailed community requirements remain to be
 * defined" (open question for Craig: group, forum or moderated WhatsApp, and who moderates). A placeholder until then.
 */
class Community extends Component
{
    public function render()
    {
        return view('livewire.patient.community')->layout('layouts.app');
    }
}
