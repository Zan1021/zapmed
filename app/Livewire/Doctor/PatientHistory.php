<?php

namespace App\Livewire\Doctor;

use App\Services\Doctor\DoctorWorkboard;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Patient history board (Craig's guide 4.1): the patients this doctor has seen in a date range, with the notes they may
 * read. The secure export (fresh 2FA, terms, password-protected file) is NOT built yet: it needs a decision on how the
 * second 2FA check works. See .kiro/specs/craig-guide-platform/phase-2-doctor-portal.md.
 */
class PatientHistory extends Component
{
    public string $from = '';
    public string $to = '';
    public string $search = '';

    public function render(DoctorWorkboard $board)
    {
        $this->resetErrorBag();

        try {
            $rows = $board->patientHistory(Auth::user(), $this->from ?: null, $this->to ?: null, $this->search);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            $rows = collect();
        }

        return view('livewire.doctor.patient-history', ['rows' => $rows])->layout('layouts.app');
    }
}
