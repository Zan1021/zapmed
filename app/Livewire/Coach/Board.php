<?php

namespace App\Livewire\Coach;

use App\Services\Coach\CoachCalls;
use App\Services\Coach\CoachPatientBoard;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Staff: the coach's landing page. One card per assigned member, flags first. */
class Board extends Component
{
    public function render(CoachPatientBoard $board, CoachCalls $calls)
    {
        return view('livewire.coach.board', [
            'rows' => $board->boardFor(Auth::user()),
            'calls' => $calls,
            'tz' => config('coach.timezone'),
        ])->layout('layouts.coach', ['title' => 'My members', 'hideTabs' => true]);
    }
}
