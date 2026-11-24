<?php

namespace App\Livewire\Doctor;

use App\Models\User;
use App\Services\Patient\HealthRecords;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Inside the consultation screen: the patient's uploaded records and results, and a box to add a result for them. */
class RecordsPanel extends Component
{
    use WithFileUploads;

    public int $patientId;
    public string $title = '';
    public $file = null;

    public function upload(HealthRecords $records): void
    {
        $this->resetErrorBag();
        $this->validate(['file' => ['required', 'file']], ['file.required' => 'Choose a file first.']);

        $records->upload(Auth::user(), User::findOrFail($this->patientId), $this->file, $this->title);

        $this->reset(['title', 'file']);
    }

    public function render(HealthRecords $records)
    {
        return view('livewire.doctor.records-panel', [
            'records' => $records->listFor(Auth::user(), User::findOrFail($this->patientId)),
        ]);
    }
}
