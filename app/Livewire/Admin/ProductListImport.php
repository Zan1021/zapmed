<?php

namespace App\Livewire\Admin;

use App\Services\Operations\ProductCatalogIO;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Product list import and export (Craig's guide 6.4): download the catalogue, edit it, check the file, load it back. */
class ProductListImport extends Component
{
    use WithFileUploads;

    public $file = null;
    public ?array $preview = null;
    public ?array $done = null;

    public function check(ProductCatalogIO $io): void
    {
        $this->resetErrorBag();
        $this->preview = null;
        $this->done = null;
        $this->validate(['file' => ['required', 'file', 'max:8192']], ['file.required' => 'Choose a CSV file first.', 'file.max' => 'The file must be 8 MB or less.']);

        try {
            $this->preview = $io->preview(Auth::user(), $this->file->getRealPath());
        } catch (ValidationException $e) {
            $this->addError('file', $e->errors()['file'][0] ?? 'That file could not be read.');
        }
    }

    public function apply(ProductCatalogIO $io): void
    {
        $this->resetErrorBag();
        abort_unless($this->file, 422);

        try {
            $result = $io->import(Auth::user(), $this->file->getRealPath());
        } catch (ValidationException $e) {
            $this->addError('file', $e->errors()['file'][0] ?? 'That file could not be read.');

            return;
        }

        if ($result['errors'] === []) {
            $this->done = $result;
            $this->preview = null;
            $this->file = null;
        } else {
            $this->preview = $result;
        }
    }

    public function render()
    {
        return view('livewire.admin.product-list-import', [
            'columns' => ProductCatalogIO::COLUMNS,
        ])->layout('layouts.app');
    }
}
