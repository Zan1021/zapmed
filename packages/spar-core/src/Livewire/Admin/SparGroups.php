<?php

namespace Zapmed\SparCore\Livewire\Admin;

use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Models\SparPharmacyGroup;

/**
 * Pharmacy group management (spec FR-14.3, Phase 7.3). SUPER-ADMIN ONLY —
 * groups are the platform-owner tier, so only ZapMed super-admin creates,
 * edits, or deactivates them. Enforced via the host SparIdentityProvider, not
 * a UI-only guard (defence in depth with the route/policy layer).
 */
class SparGroups extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $region = '';
    public string $contact_name = '';
    public string $contact_email = '';
    public string $contact_phone = '';
    public bool $is_active = true;

    /** Hard gate: abort unless the current actor is a super-admin. */
    public function mount(): void
    {
        $this->authorizeSuperAdmin();
    }

    protected function authorizeSuperAdmin(): void
    {
        abort_unless(app(SparIdentityProvider::class)->isSuperAdmin(), 403);
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'region' => 'nullable|string|max:100',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:30',
            'is_active' => 'boolean',
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeSuperAdmin();
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeSuperAdmin();
        $group = SparPharmacyGroup::findOrFail($id);
        $this->editingId = $group->id;
        $this->name = $group->name;
        $this->region = $group->region ?? '';
        $this->contact_name = $group->contact_name ?? '';
        $this->contact_email = $group->contact_email ?? '';
        $this->contact_phone = $group->contact_phone ?? '';
        $this->is_active = $group->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeSuperAdmin();
        $validated = $this->validate();

        if ($this->editingId) {
            $group = SparPharmacyGroup::findOrFail($this->editingId);
            $group->update($validated);
            $this->audit('group_updated', $group->id);
            session()->flash('success', 'Group updated.');
        } else {
            $group = SparPharmacyGroup::create($validated);
            $this->audit('group_created', $group->id);
            session()->flash('success', 'Group created.');
        }

        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeSuperAdmin();
        $group = SparPharmacyGroup::findOrFail($id);
        $group->update(['is_active' => !$group->is_active]);
        $this->audit($group->is_active ? 'group_activated' : 'group_deactivated', $group->id);
    }

    public function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->reset(['name', 'region', 'contact_name', 'contact_email', 'contact_phone']);
        $this->is_active = true;
    }

    private function audit(string $event, int $groupId): void
    {
        Log::channel(config('logging.channels.spar_audit') ? 'spar_audit' : 'stack')->info(
            "{$event}: group #{$groupId}",
            ['event' => $event, 'group_id' => $groupId, 'actor_id' => auth()->id()]
        );
    }

    public function render()
    {
        $groups = SparPharmacyGroup::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('region', 'like', "%{$this->search}%"))
            ->withCount('pharmacies')
            ->orderBy('name')
            ->paginate(15);

        return view('spar::livewire.admin.spar-groups', ['groups' => $groups])
            ->layout(config('spar.layouts.staff', 'layouts.app'));
    }
}
