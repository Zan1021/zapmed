<?php

namespace Zapmed\SparCore\Livewire\Admin;

use Zapmed\SparCore\Concerns\LogsSparActivity;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Models\SparConsent;
use Zapmed\SparCore\Models\SparDispenseRecord;
use Zapmed\SparCore\Models\SparImportBatch;
use Zapmed\SparCore\Models\SparImportLog;
use Zapmed\SparCore\Models\SparOrder;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Models\SparPrescriptionJourney;
use Zapmed\SparCore\Services\SparImportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class SparImports extends Component
{
    use WithFileUploads, WithPagination, LogsSparActivity;

    public $csvFile;
    public $drugUsageFile;
    public bool $importing = false;
    public ?int $viewingBatchId = null;

    // --- Test-only "reset all patients" support ------------------------------
    // Lets an admin wipe imported patient data on a demo/staging box so the two
    // import files can be re-run from scratch. Hard-guarded (super-admin only,
    // never production, typed confirmation). NOT a normal operational feature.
    public bool $showResetModal = false;
    public string $resetConfirm = '';

    protected function rules(): array
    {
        return [
            'csvFile' => 'required|file|mimes:csv,txt|max:10240',
            'drugUsageFile' => 'nullable|file|mimes:xlsx,xls|max:10240',
        ];
    }

    public function import(): void
    {
        $this->validate();
        $this->importing = true;

        try {
            // Resolve the absolute path via the disk itself. The 'local' disk
            // root differs across Laravel versions (storage/app on L10,
            // storage/app/private on L11+), so hand-building storage_path('app/'
            // . $path) breaks on L11 — the file lands under private/ but is read
            // from the wrong dir → "file not found". Storage::path() is correct
            // regardless of the root.
            $path = $this->csvFile->store('spar-imports', 'local');
            $fullPath = Storage::disk('local')->path($path);

            // Keep the human-friendly uploaded names for the batch/history — the
            // stored path is an opaque hash, which is useless in the UI.
            $originalCsvName = $this->csvFile->getClientOriginalName();

            $service = new SparImportService();

            // Dual-file import: sales extract + Drug Usage report (identity),
            // linked on (profile_code, dependent_code). Falls back to a
            // sales-only import when no report is provided.
            if ($this->drugUsageFile) {
                $duPath = $this->drugUsageFile->store('spar-imports', 'local');
                $duFullPath = Storage::disk('local')->path($duPath);
                $batch = $service->importPair($fullPath, $duFullPath, auth()->id());
            } else {
                $batch = $service->importFile($fullPath, auth()->id());
            }

            // Overwrite the opaque hashed filename with the real uploaded name.
            if ($batch->filename !== $originalCsvName) {
                $batch->update(['filename' => $originalCsvName]);
            }

            if ($batch->status === 'completed') {
                $this->logImportEvent($batch->id, $batch->filename, 'completed');
                session()->flash('success', sprintf(
                    'Import complete: %d created, %d updated, %d skipped, %d failed.',
                    (int) $batch->records_created,
                    (int) $batch->records_updated,
                    (int) $batch->records_skipped,
                    (int) $batch->records_failed,
                ));
            } else {
                $this->logImportEvent($batch->id, $batch->filename, 'failed');
                session()->flash('error', 'Import failed: ' . implode(', ', $batch->errors ?? ['Unknown error']));
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Import failed: ' . $e->getMessage());
        }

        $this->importing = false;
        $this->reset('csvFile', 'drugUsageFile');
    }

    public function viewBatch(int $id): void
    {
        $this->viewingBatchId = $id;
    }

    // --- Test-only reset ------------------------------------------------------

    /**
     * Whether the current actor+environment may use the destructive reset.
     * TWO gates: (1) super-admin only, (2) never in production. Both must pass.
     * The button and the action both consult this — defence in depth.
     */
    public function getCanResetProperty(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        try {
            return app(SparIdentityProvider::class)->isSuperAdmin();
        } catch (\Throwable) {
            return false;
        }
    }

    public function openResetModal(): void
    {
        $this->resetConfirm = '';
        $this->showResetModal = true;
    }

    public function closeResetModal(): void
    {
        $this->showResetModal = false;
        $this->resetConfirm = '';
    }

    /**
     * DESTRUCTIVE (test/demo only): delete ALL SPAR patients and their dependent
     * data so the two import files can be re-run cleanly. Leaves pharmacies,
     * groups, staff users and (by default) import-batch history intact.
     *
     * Guards, in order:
     *   1. Not production (environment check).
     *   2. Super-admin only.
     *   3. Typed confirmation must equal "DELETE".
     * All wrapped in a transaction; child rows removed explicitly because SQLite
     * does not enforce ON DELETE CASCADE by default.
     */
    public function resetAllPatients(): void
    {
        if (! $this->canReset) {
            session()->flash('error', 'Reset is not available in this environment or for your role.');
            $this->closeResetModal();

            return;
        }

        if ($this->resetConfirm !== 'DELETE') {
            session()->flash('error', 'Type DELETE to confirm the reset.');

            return;
        }

        $counts = [];

        DB::transaction(function () use (&$counts) {
            // Dispense records hang off journeys (no direct patient FK) — clear
            // them first, then the rows keyed directly on the patient.
            $counts['dispense'] = SparDispenseRecord::query()->count();
            SparDispenseRecord::query()->delete();

            $counts['orders'] = SparOrder::query()->count();
            SparOrder::query()->delete();

            $counts['journeys'] = SparPrescriptionJourney::query()->count();
            SparPrescriptionJourney::query()->delete();

            $counts['consents'] = SparConsent::query()->count();
            SparConsent::query()->delete();

            $counts['patients'] = SparPatient::query()->count();
            SparPatient::query()->delete();

            // Import history: clear logs + batches too, so the imports screen
            // reads empty and Craig sees a truly fresh run.
            $counts['import_logs'] = SparImportLog::query()->count();
            SparImportLog::query()->delete();

            $counts['import_batches'] = SparImportBatch::query()->count();
            SparImportBatch::query()->delete();
        });

        $this->logSparActivity('patients_reset', 'Admin wiped all SPAR patient data (test reset)', $counts);

        $this->closeResetModal();
        $this->resetPage();

        session()->flash('success', sprintf(
            'Test reset complete — deleted %d patients, %d journeys, %d dispense records, %d orders, %d consents, and %d import batches.',
            $counts['patients'], $counts['journeys'], $counts['dispense'], $counts['orders'], $counts['consents'], $counts['import_batches']
        ));
    }

    public function closeBatch(): void
    {
        $this->viewingBatchId = null;
    }

    public function getViewingBatchProperty(): ?SparImportBatch
    {
        if (!$this->viewingBatchId) return null;
        return SparImportBatch::with('logs')->find($this->viewingBatchId);
    }

    public function render()
    {
        // NOTE: no ->with('importedBy') — SparImportBatch intentionally has no
        // importedBy relation (imported_by is a plain nullable column, AC-3, no
        // host-User coupling). Eager-loading it threw RelationNotFoundException,
        // which Livewire surfaced as a misleading "page expired" dialog.
        $batches = SparImportBatch::latest()
            ->paginate(15);

        return view('spar::livewire.admin.spar-imports', ['batches' => $batches])
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
