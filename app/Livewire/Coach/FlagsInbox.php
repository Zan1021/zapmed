<?php

namespace App\Livewire\Coach;

use App\Models\CoachFlag;
use App\Services\Coach\CoachAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Staff list of open coach safety flags (doctors and coaches). Shows only flags the viewer may handle
 * (CoachAccess::canHandleFlag). Admin does not get health detail here; the Alerts board shows the flag without it.
 */
class FlagsInbox extends Component
{
    public function handled(int $id, CoachAccess $access): void
    {
        $flag = CoachFlag::open()->with('patient')->find($id);

        if (! $flag || ! $access->canHandleFlag(Auth::user(), $flag)) {
            abort(403);
        }

        $flag->update(['status' => 'handled', 'handled_by' => Auth::id(), 'handled_at' => now()]);
        $this->dispatch('coach-toast', message: 'Marked as handled');
    }

    public function render(CoachAccess $access)
    {
        $me = Auth::user();

        $flags = CoachFlag::open()->with('patient')->orderBy('created_at')->get()
            ->filter(fn (CoachFlag $f) => $access->canHandleFlag($me, $f))
            ->values();

        return view('livewire.coach.flags-inbox', ['flags' => $flags, 'tz' => config('coach.timezone')])
            ->layout('layouts.coach', ['title' => 'Coach safety flags', 'hideTabs' => true]);
    }
}