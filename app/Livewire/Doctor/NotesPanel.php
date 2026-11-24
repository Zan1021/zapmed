<?php

namespace App\Livewire\Doctor;

use App\Models\Order;
use App\Models\PatientNote;
use App\Models\User;
use App\Services\Doctor\PatientNoteService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** The notes about one patient that the viewer may read, and a box to write one (Craig's guide 4.3). */
class NotesPanel extends Component
{
    public int $patientId;
    public string $type = PatientNote::CLINICAL;
    public string $body = '';
    public ?int $orderId = null;
    /** @var string[] roles the staff note is addressed to */
    public array $roles = [];
    /** @var int[] people the staff note is addressed to */
    public array $people = [];
    public bool $actionRequired = false;

    public function save(PatientNoteService $service): void
    {
        $this->resetErrorBag();

        $audience = array_merge(
            array_map(fn ($r) => ['role' => $r], $this->roles),
            array_map(fn ($u) => ['user_id' => (int) $u], $this->people),
        );

        $service->create(
            Auth::user(), User::findOrFail($this->patientId), $this->type, $this->body, $audience,
            $this->orderId ?: null, $this->actionRequired,
        );

        $this->reset(['body', 'orderId', 'roles', 'people', 'actionRequired']);
    }

    public function done(int $id, PatientNoteService $service): void
    {
        $this->resetErrorBag();
        $service->completeAction(Auth::user(), PatientNote::where('patient_id', $this->patientId)->findOrFail($id));
    }

    public function render(PatientNoteService $service)
    {
        $me = Auth::user();
        $patient = User::findOrFail($this->patientId);
        $types = $service->typesFor($me, $patient);

        if (! in_array($this->type, $types, true) && $types) {
            $this->type = $types[0];
        }

        return view('livewire.doctor.notes-panel', [
            'notes' => $service->readableFor($me, $patient),
            'types' => $types,
            'labels' => PatientNote::LABELS,
            'orders' => Order::where('patient_id', $patient->id)->orderByDesc('id')->limit(10)->get(['id', 'reference']),
            'staff' => User::whereIn('role', ['admin', 'doctor', 'health_coach'])->where('id', '!=', $me->id)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'role']),
        ]);
    }
}
