<?php

namespace App\Livewire\Patient;

use App\Services\Patient\PatientRewards;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Rewards tab (Craig's guide 3.1): coupons and discounts meant for this patient. */
class Rewards extends Component
{
    public function render(PatientRewards $rewards)
    {
        return view('livewire.patient.rewards', ['coupons' => $rewards->available(Auth::user())])->layout('layouts.app');
    }
}
