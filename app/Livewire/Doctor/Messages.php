<?php

namespace App\Livewire\Doctor;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Messages extends Component
{
    public function render()
    {
        return view('livewire.doctor.messages')
            ->layout('layouts.app');
    }
}
