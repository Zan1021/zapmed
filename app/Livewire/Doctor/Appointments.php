<?php

namespace App\Livewire\Doctor;

use App\Services\Doctor\DoctorWorkboard;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * New and past appointments with date and service filters (Craig's guide 4.1). New appointments can be shown as
 * newest-booked-first, or as a day, week or month starting at the chosen date.
 */
class Appointments extends Component
{
    public string $tab = 'new';          // new | past
    public string $view = 'list';        // list | day | week | month (new tab)
    public string $anchor = '';          // the day a day/week/month view starts from
    public string $from = '';
    public string $to = '';
    public string $type = '';
    public string $sort = 'booked';      // booked | date

    public function mount(DoctorWorkboard $board): void
    {
        $this->anchor = $board->today()->format('Y-m-d');
    }

    public function clear(): void
    {
        $this->reset(['from', 'to', 'type']);
        $this->resetErrorBag();
    }

    public function render(DoctorWorkboard $board)
    {
        $this->resetErrorBag();
        $me = Auth::user();
        $from = $this->from ?: null;
        $to = $this->to ?: null;

        try {
            if ($this->tab === 'new' && $this->view !== 'list') {
                [$start, $end] = $board->range($this->view, $this->anchor ?: null);
                [$from, $to] = [$start->format('Y-m-d'), $end->format('Y-m-d')];
            }

            $rows = $this->tab === 'past'
                ? $board->pastAppointments($me, $from, $to, $this->type ?: null)
                : $board->newAppointments($me, $from, $to, $this->type ?: null, $this->sort);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            $rows = collect();
        }

        return view('livewire.doctor.appointments', [
            'rows' => $rows,
            'types' => ['general' => 'General Consultation', 'follow_up' => 'Follow-up', 'chronic_renewal' => 'Chronic Med Renewal', 'new_patient' => 'New Patient Consultation'],
        ])->layout('layouts.app');
    }
}
