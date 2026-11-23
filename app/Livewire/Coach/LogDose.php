<?php

namespace App\Livewire\Coach;

use App\Services\Coach\DoseLogger;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Log dose (design screen 7): six sites with the next one in rotation starred, and pain 0 to 10.
 */
class LogDose extends Component
{
    public ?string $site = null;

    public int $pain = 0;

    public ?string $error = null;

    public function chooseSite(string $site, DoseLogger $logger): void
    {
        if (in_array($site, $logger->sites(), true)) {
            $this->site = $site;
            $this->error = null;
        }
    }

    public function save(DoseLogger $logger)
    {
        if (! $this->site) {
            $this->error = 'Please choose where you injected.';

            return;
        }

        try {
            $logger->log(Auth::user(), $this->site, (int) $this->pain);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        return redirect()->route('coach.medication')->with('coach_toast', 'Dose logged. Your next dose date is updated.');
    }

    public function render(DoseLogger $logger)
    {
        return view('livewire.coach.log-dose', [
            'sites' => $logger->sites(),
            'nextSite' => $logger->nextSite(Auth::user()),
            'painMax' => (int) config('coach.medication.pain_max', 10),
            'noAdvice' => config('coach.medication.no_dose_advice'),
        ])->layout('layouts.coach', ['title' => 'Log dose', 'hideTabs' => true]);
    }
}