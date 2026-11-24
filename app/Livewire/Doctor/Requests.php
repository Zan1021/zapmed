<?php

namespace App\Livewire\Doctor;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\CoachingAssignment;
use App\Models\DoctorRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\Doctor\DoctorRequestService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Doctor Requests list + "new request" form (Craig's guide 4.1, 8.1). Doctors, coaches and Operations can ask;
 * doctors do the work. Who may see what is decided by DoctorRequestService.
 */
class Requests extends Component
{
    public string $filter = 'open';        // open | all | mine (asked by me)
    public bool $showForm = false;

    public string $title = '';
    public string $description = '';
    /** @var int[] */
    public array $assigneeIds = [];
    /** @var int[] */
    public array $participantIds = [];
    /** @var array<int,array{patient_id:int, order_id:?int}> */
    public array $refs = [];
    public string $patientSearch = '';

    public function newRequest(): void
    {
        $this->showForm = true;
    }

    public function addPatient(int $patientId): void
    {
        $patient = $this->patientChoices()->firstWhere('id', $patientId);
        if (! $patient || collect($this->refs)->contains(fn ($r) => (int) $r['patient_id'] === $patientId)) {
            return;
        }

        $this->refs[] = ['patient_id' => $patientId, 'order_id' => null];
        $this->patientSearch = '';
    }

    public function removeRef(int $index): void
    {
        unset($this->refs[$index]);
        $this->refs = array_values($this->refs);
    }

    public function create(DoctorRequestService $service): void
    {
        $this->resetErrorBag();
        $request = $service->create(Auth::user(), [
            'title' => $this->title,
            'description' => $this->description,
            'assignee_ids' => $this->assigneeIds,
            'participant_ids' => $this->participantIds,
            'refs' => array_map(fn ($r) => [
                'patient_id' => (int) $r['patient_id'],
                'order_id' => $r['order_id'] ?: null,
            ], $this->refs),
        ]);

        $this->reset(['title', 'description', 'assigneeIds', 'participantIds', 'refs', 'patientSearch', 'showForm']);

        $this->redirectRoute('doctor.requests.show', $request);
    }

    /** Patients the viewer may refer to: admin any, doctor their appointment patients, coach their assigned patients. */
    public function patientChoices()
    {
        $me = Auth::user();
        $term = trim($this->patientSearch);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $q = User::query()->where('role', UserRole::Patient->value)
            ->where(fn ($q) => $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('member_number', 'like', "%{$term}%"));

        if ($me->role === UserRole::Doctor) {
            $q->whereIn('id', app(\App\Services\Coach\CoachAccess::class)->doctorPatientIds($me));
        } elseif ($me->role === UserRole::HealthCoach) {
            $q->whereIn('id', CoachingAssignment::active()->where('coach_id', $me->id)->select('patient_id'));
        }

        return $q->orderBy('first_name')->limit(8)->get(['id', 'first_name', 'last_name', 'member_number', 'email']);
    }

    public function render(DoctorRequestService $service)
    {
        $me = Auth::user();
        $query = $service->visibleTo($me);

        if ($this->filter === 'open') {
            $query->where('status', '!=', DoctorRequest::COMPLETED);
        } elseif ($this->filter === 'mine') {
            $query->where('requester_id', $me->id);
        }

        $patientNames = User::whereIn('id', collect($this->refs)->pluck('patient_id'))->get()->keyBy('id');
        $ordersByPatient = Order::whereIn('patient_id', collect($this->refs)->pluck('patient_id'))
            ->orderByDesc('id')->get(['id', 'reference', 'patient_id', 'service_category', 'status'])->groupBy('patient_id');

        return view('livewire.doctor.requests', [
            'requests' => $query->limit(100)->get(),
            'doctors' => User::where('role', UserRole::Doctor->value)->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'staff' => User::whereIn('role', [UserRole::Admin->value, UserRole::Doctor->value, UserRole::HealthCoach->value])
                ->where('id', '!=', $me->id)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'role']),
            'choices' => $this->showForm ? $this->patientChoices() : collect(),
            'patientNames' => $patientNames,
            'ordersByPatient' => $ordersByPatient,
            'canCreate' => $service->canCreate($me),
        ])->layout('layouts.app');
    }
}
