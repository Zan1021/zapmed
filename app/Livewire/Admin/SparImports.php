<?php

namespace App\Livewire\Admin;

use App\Models\SparImportBatch;
use App\Services\SparImportService;
use App\Traits\LogsSparActivity;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class SparImports extends Component
{
    use WithFileUploads, WithPagination, LogsSparActivity;

    public $csvFile;
    public bool $importing = false;
    public ?int $viewingBatchId = null;

    protected function rules(): array
    {
        return [
            'csvFile' => 'required|file|mimes:csv,txt|max:10240',
        ];
    }

    public function import(): void
    {
        $this->validate();
        $this->importing = true;

        try {
            $path = $this->csvFile->store('spar-imports', 'local');
            $fullPath = storage_path('app/' . $path);

            $service = new SparImportService();
            $batch = $service->importFile($fullPath, auth()->id());

            if ($batch->status === 'completed') {
                $this->logImportEvent($batch->id, $batch->filename, 'completed');
                session()->flash('success', "Import complete: {$batch->records_created} created, {$batch->records_updated} updated, {$batch->records_skipped} skipped, {$batch->records_failed} failed.");
            } else {
                $this->logImportEvent($batch->id, $batch->filename, 'failed');
                session()->flash('error', 'Import failed: ' . implode(', ', $batch->errors ?? ['Unknown error']));
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Import failed: ' . $e->getMessage());
        }

        $this->importing = false;
        $this->reset('csvFile');
    }

    public function viewBatch(int $id): void
    {
        $this->viewingBatchId = $id;
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
        $batches = SparImportBatch::with('importedBy')
            ->latest()
            ->paginate(15);

        return view('livewire.admin.spar-imports', ['batches' => $batches])
            ->layout('layouts.app');
    }
}
