<?php

namespace App\Livewire\Admin;

use App\Models\Medication;
use Livewire\Component;
use Livewire\WithPagination;

class MedicationCatalog extends Component
{
    use WithPagination;

    public string $search = '';
    public string $categoryFilter = '';
    public string $formFilter = '';
    public bool $showModal = false;
    public ?int $editingId = null;

    // Form fields
    public string $name = '';
    public string $generic_name = '';
    public string $brand_name = '';
    public string $form = 'tablet';
    public string $strength = '';
    public string $schedule = 'S4';
    public string $nappi_code = '';
    public string $category = '';
    public ?int $price_cents = null;
    public ?int $repeat_cycle_days = 30;
    public bool $is_subscription = false;
    public string $description = '';
    public string $dosage_instructions = '';
    public string $manufacturer = '';
    public bool $is_active = true;

    protected $rules = [
        'name' => 'required|string|max:255',
        'generic_name' => 'nullable|string|max:255',
        'brand_name' => 'nullable|string|max:255',
        'form' => 'required|string|max:50',
        'strength' => 'required|string|max:50',
        'schedule' => 'nullable|string|max:10',
        'nappi_code' => 'nullable|string|max:20',
        'category' => 'nullable|string|max:50',
        'price_cents' => 'nullable|integer|min:0',
        'repeat_cycle_days' => 'nullable|integer|min:1',
        'is_subscription' => 'boolean',
        'description' => 'nullable|string',
        'dosage_instructions' => 'nullable|string',
        'manufacturer' => 'nullable|string|max:255',
        'is_active' => 'boolean',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $med = Medication::findOrFail($id);
        $this->editingId = $id;
        $this->name = $med->name;
        $this->generic_name = $med->generic_name ?? '';
        $this->brand_name = $med->brand_name ?? '';
        $this->form = $med->form;
        $this->strength = $med->strength;
        $this->schedule = $med->schedule ?? '';
        $this->nappi_code = $med->nappi_code ?? '';
        $this->category = $med->category ?? '';
        $this->price_cents = $med->price_cents;
        $this->repeat_cycle_days = $med->repeat_cycle_days;
        $this->is_subscription = $med->is_subscription;
        $this->description = $med->description ?? '';
        $this->dosage_instructions = $med->dosage_instructions ?? '';
        $this->manufacturer = $med->manufacturer ?? '';
        $this->is_active = $med->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'generic_name' => $this->generic_name ?: null,
            'brand_name' => $this->brand_name ?: null,
            'form' => $this->form,
            'strength' => $this->strength,
            'schedule' => $this->schedule ?: null,
            'nappi_code' => $this->nappi_code ?: null,
            'category' => $this->category ?: null,
            'price_cents' => $this->price_cents,
            'repeat_cycle_days' => $this->repeat_cycle_days,
            'is_subscription' => $this->is_subscription,
            'description' => $this->description ?: null,
            'dosage_instructions' => $this->dosage_instructions ?: null,
            'manufacturer' => $this->manufacturer ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            Medication::findOrFail($this->editingId)->update($data);
        } else {
            Medication::create($data);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $med = Medication::findOrFail($id);
        $med->update(['is_active' => !$med->is_active]);
    }

    public function delete(int $id): void
    {
        Medication::findOrFail($id)->delete();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->generic_name = '';
        $this->brand_name = '';
        $this->form = 'tablet';
        $this->strength = '';
        $this->schedule = 'S4';
        $this->nappi_code = '';
        $this->category = '';
        $this->price_cents = null;
        $this->repeat_cycle_days = 30;
        $this->is_subscription = false;
        $this->description = '';
        $this->dosage_instructions = '';
        $this->manufacturer = '';
        $this->is_active = true;
    }

    public function render()
    {
        $medications = Medication::query()
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->when($this->formFilter, fn ($q) => $q->where('form', $this->formFilter))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(20);

        $categories = Medication::distinct()->whereNotNull('category')->pluck('category')->sort();
        $forms = Medication::distinct()->pluck('form')->sort();

        return view('livewire.admin.medication-catalog', compact('medications', 'categories', 'forms'))
            ->layout('layouts.app');
    }
}
