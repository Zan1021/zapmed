<?php

namespace App\Livewire\Admin;

use App\Models\SparPharmacy;
use Livewire\Component;
use Livewire\WithPagination;

class SparPharmacies extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showForm = false;
    public ?int $editingId = null;

    // Form fields
    public string $name = '';
    public string $spar_store_id = '';
    public string $bhf_code = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public string $city = '';
    public string $province = '';
    public string $postal_code = '';
    public bool $supports_delivery = false;
    public int $delivery_fee = 0;
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'spar_store_id' => 'required|string|unique:spar_pharmacies,spar_store_id,' . $this->editingId,
            'bhf_code' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:50',
            'postal_code' => 'nullable|string|max:10',
            'supports_delivery' => 'boolean',
            'delivery_fee' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $pharmacy = SparPharmacy::findOrFail($id);
        $this->editingId = $pharmacy->id;
        $this->name = $pharmacy->name;
        $this->spar_store_id = $pharmacy->spar_store_id;
        $this->bhf_code = $pharmacy->bhf_code ?? '';
        $this->phone = $pharmacy->phone ?? '';
        $this->email = $pharmacy->email ?? '';
        $this->address = $pharmacy->address ?? '';
        $this->city = $pharmacy->city ?? '';
        $this->province = $pharmacy->province ?? '';
        $this->postal_code = $pharmacy->postal_code ?? '';
        $this->supports_delivery = $pharmacy->supports_delivery;
        $this->delivery_fee = $pharmacy->delivery_fee;
        $this->is_active = $pharmacy->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->editingId) {
            $pharmacy = SparPharmacy::findOrFail($this->editingId);
            $pharmacy->update($validated);
            session()->flash('success', 'Pharmacy updated.');
        } else {
            SparPharmacy::create($validated);
            session()->flash('success', 'Pharmacy created.');
        }

        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $pharmacy = SparPharmacy::findOrFail($id);
        $pharmacy->update(['is_active' => !$pharmacy->is_active]);
    }

    public function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->reset(['name', 'spar_store_id', 'bhf_code', 'phone', 'email', 'address', 'city', 'province', 'postal_code', 'supports_delivery', 'delivery_fee']);
        $this->is_active = true;
    }

    public function render()
    {
        $pharmacies = SparPharmacy::when($this->search, function ($query) {
            $query->where('name', 'like', "%{$this->search}%")
                ->orWhere('spar_store_id', 'like', "%{$this->search}%")
                ->orWhere('city', 'like', "%{$this->search}%");
        })
            ->withCount('patients', 'orders')
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.spar-pharmacies', ['pharmacies' => $pharmacies])
            ->layout('layouts.app');
    }
}
