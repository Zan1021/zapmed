<?php

namespace App\Livewire\Patient;

use App\Models\BloodTestRequest;
use App\Models\CoachingAssignment;
use App\Models\Order;
use App\Models\PatientProfile;
use App\Models\SideEffectEntry;
use App\Services\Coach\CoachAccess;
use App\Services\Coach\SideEffectLogger;
use App\Services\Coach\WeightChart;
use App\Services\Coach\WeightLogger;
use App\Services\Coach\WeightStats;
use App\Services\Doctor\BloodTestService;
use App\Services\Doctor\PatientNoteService;
use App\Services\Patient\HealthRecords;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Health Stats tab (Craig's guide 3.1): weight and progress, side-effect log (weight-loss patients only), records and
 * results, tests the doctor asked for, and the latest summary the doctor wrote for the patient. Never advises a dose.
 */
class HealthStats extends Component
{
    use WithFileUploads;

    public string $weight = '';
    public string $height = '';
    public array $scores = [];            // symptom name => 0..10
    public string $recordTitle = '';
    public $recordFile = null;
    public array $testUploads = [];       // blood test request id => file

    public function mount(): void
    {
        $profile = PatientProfile::where('user_id', Auth::id())->first();
        $this->height = $profile?->height_cm ? (string) (float) $profile->height_cm : '';
    }

    public function logWeight(WeightLogger $logger): void
    {
        $this->resetErrorBag('weight');

        if (! is_numeric($this->weight)) {
            $this->addError('weight', 'Type your weight in kilograms, for example 82.5.');

            return;
        }

        try {
            $logger->log(Auth::user(), (float) $this->weight, 'health-stats');
        } catch (InvalidArgumentException $e) {
            $this->addError('weight', $e->getMessage());

            return;
        }

        $this->weight = '';
    }

    public function saveHeight(): void
    {
        $this->resetErrorBag('height');
        [$min, $max] = config('patient.health.height_cm');

        if (! is_numeric($this->height) || $this->height < $min || $this->height > $max) {
            $this->addError('height', "Height must be between {$min} and {$max} cm.");

            return;
        }

        PatientProfile::updateOrCreate(['user_id' => Auth::id()], ['height_cm' => round((float) $this->height, 1)]);
    }

    public function saveSideEffects(SideEffectLogger $logger): void
    {
        $this->resetErrorBag('scores');
        abort_unless($this->isWeightLoss(), 403);

        try {
            $result = $logger->save(Auth::user(), array_filter($this->scores, fn ($s) => $s !== '' && $s !== null));
        } catch (InvalidArgumentException $e) {
            $this->addError('scores', $e->getMessage());

            return;
        }

        $this->scores = [];
        session()->flash('health-flagged', $result['flagged'] ? 'Thank you. Because of what you reported, someone from your care team will be in touch.' : null);
    }

    public function uploadRecord(HealthRecords $records): void
    {
        $this->resetErrorBag();
        $this->validate(['recordFile' => ['required', 'file']], ['recordFile.required' => 'Choose a file first.']);

        $records->upload(Auth::user(), Auth::user(), $this->recordFile, $this->recordTitle);

        $this->reset(['recordTitle', 'recordFile']);
    }

    public function uploadTestResult(int $requestId, BloodTestService $tests): void
    {
        $this->resetErrorBag();
        $file = $this->testUploads[$requestId] ?? null;
        if (! $file) {
            $this->addError('test_'.$requestId, 'Choose a file first.');

            return;
        }

        $request = BloodTestRequest::where('patient_id', Auth::id())->findOrFail($requestId);
        $tests->uploadResult(Auth::user(), $request, $file);

        unset($this->testUploads[$requestId]);
    }

    /** A weight-loss patient: one of their orders is weight-loss, or they have the coaching add-on / a coach. */
    public function isWeightLoss(): bool
    {
        $user = Auth::user();

        $markers = config('patient.health.weight_loss_markers');
        $byOrder = Order::where('patient_id', $user->id)->get(['service_category'])->contains(
            fn ($o) => collect($markers)->contains(fn ($m) => $o->service_category && stripos($o->service_category, $m) !== false)
        );

        return $byOrder
            || app(CoachAccess::class)->patientHasAddon($user)
            || CoachingAssignment::active()->where('patient_id', $user->id)->exists();
    }

    public function render(PatientNoteService $notes, HealthRecords $records, SideEffectLogger $sideEffects)
    {
        $user = Auth::user();
        $weightLoss = $this->isWeightLoss();
        $stats = WeightStats::forUser($user);
        $chart = WeightChart::build($user, 90);

        return view('livewire.patient.health-stats', [
            'weightLoss' => $weightLoss,
            'stats' => $stats,
            'chart' => $chart,
            'symptoms' => $weightLoss ? $sideEffects->visible($user) : [],
            'recent' => $weightLoss ? SideEffectEntry::where('user_id', $user->id)->whereDate('logged_on', '>=', now()->subDays(14)->toDateString())->orderByDesc('logged_on')->orderByDesc('id')->limit(30)->get() : collect(),
            'summary' => $notes->summariesFor($user)->first(),
            'records' => $records->listFor($user, $user),
            'tests' => BloodTestRequest::with('results')->where('patient_id', $user->id)->orderByDesc('id')->limit(20)->get(),
        ])->layout('layouts.app');
    }
}
