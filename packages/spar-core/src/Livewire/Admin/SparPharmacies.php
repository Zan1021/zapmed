<?php

namespace Zapmed\SparCore\Livewire\Admin;

use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;

/**
 * Pharmacy management (spec FR-15.2/15.3, Phase 7.4). Scope-enforced:
 *   - super-admin  : any pharmacy, any group
 *   - group-admin  : pharmacies in their OWN group only
 *   - others       : no access (403)
 * The list is filtered via SparPharmacy::visibleToCurrentActor(); create/edit
 * are gated by SparIdentityProvider::canManagePharmacy()/canManageGroup().
 */
class SparPharmacies extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showForm = false;
    public ?int $editingId = null;

    public ?int $group_id = null;
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

    public function mount(): void
    {
        // Only actors who can manage at least their own scope may reach this.
        $id = $this->identity();
        abort_unless($id->isSuperAdmin() || $this->currentRole() === 'group_admin', 403);
    }

    private function identity(): SparIdentityProvider
    {
        return app(SparIdentityProvider::class);
    }

    private function currentRole(): ?string
    {
        return $this->identity()->currentRole();
    }

    protected function rules(): array
    {
        return [
            'group_id' => 'required|integer|exists:spar_pharmacy_groups,id',
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
        // Group-admin: pre-select and lock to their own group.
        if (!$this->identity()->isSuperAdmin()) {
            $this->group_id = $this->identity()->currentGroupId();
        }
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        abort_unless($this->identity()->canManagePharmacy($id), 403);

        $pharmacy = SparPharmacy::findOrFail($id);
        $this->editingId = $pharmacy->id;
        $this->group_id = $pharmacy->group_id;
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

        // Authorisation: the actor must be able to manage the CHOSEN group
        // (super=any, group-admin=own). This blocks a group-admin from smuggling
        // another group's id via the request.
        abort_unless($this->identity()->canManageGroup((int) $validated['group_id']), 403);

        if ($this->editingId) {
            abort_unless($this->identity()->canManagePharmacy($this->editingId), 403);
            $pharmacy = SparPharmacy::findOrFail($this->editingId);
            $pharmacy->update($validated);
            $this->audit('pharmacy_updated', $pharmacy->id);
            session()->flash('success', 'Pharmacy updated.');
        } else {
            $pharmacy = SparPharmacy::create($validated);
            $this->audit('pharmacy_created', $pharmacy->id);
            session()->flash('success', 'Pharmacy created.');
        }

        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        abort_unless($this->identity()->canManagePharmacy($id), 403);
        $pharmacy = SparPharmacy::findOrFail($id);
        $pharmacy->update(['is_active' => !$pharmacy->is_active]);
        $this->audit('pharmacy_toggled', $pharmacy->id);
    }

    public function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->reset(['group_id', 'name', 'spar_store_id', 'bhf_code', 'phone', 'email', 'address', 'city', 'province', 'postal_code', 'supports_delivery', 'delivery_fee']);
        $this->is_active = true;
    }

    private function audit(string $event, int $pharmacyId): void
    {
        Log::channel(config('logging.channels.spar_audit') ? 'spar_audit' : 'stack')->info(
            "{$event}: pharmacy #{$pharmacyId}",
            ['event' => $event, 'pharmacy_id' => $pharmacyId, 'actor_id' => auth()->id()]
        );
    }

    public function render()
    {
        $pharmacies = SparPharmacy::visibleToCurrentActor()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('spar_store_id', 'like', "%{$this->search}%")
                        ->orWhere('city', 'like', "%{$this->search}%");
                });
            })
            ->withCount(['patients', 'orders'])
            ->with('group')
            ->orderBy('name')
            ->paginate(15);

        // Group options for the form: super-admin sees all; group-admin only theirs.
        $groups = $this->identity()->isSuperAdmin()
            ? SparPharmacyGroup::orderBy('name')->get()
            : SparPharmacyGroup::whereKey($this->identity()->currentGroupId())->get();

        return view('spar::livewire.admin.spar-pharmacies', [
            'pharmacies' => $pharmacies,
            'groups' => $groups,
        ])->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
