<?php

namespace App\Livewire\Admin;

use App\Models\PharmacyUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Zapmed\SparCore\Contracts\SparIdentityProvider;
use Zapmed\SparCore\Models\SparPharmacy;
use Zapmed\SparCore\Models\SparPharmacyGroup;

/**
 * Phase 7.5 — staff/admin account management (standalone), tiered per FR-15.
 *
 * Lives in the STANDALONE host (not spar-core) because it manages the
 * standalone-owned PharmacyUser identity model; the package must never
 * reference a host user class (AC-3/AC-15). ZapMed manages its own staff via
 * its User admin.
 *
 * Creation tiers (an actor can never create at or above their own tier, nor
 * outside their scope):
 *   - super_admin   : any role, any group / pharmacy
 *   - group_admin   : pharmacy_admin | pharmacy_staff, in pharmacies of OWN group
 *   - pharmacy_admin : pharmacy_staff, in OWN pharmacy only
 *   - pharmacy_staff : no access
 */
class StaffManagement extends Component
{
    use WithPagination;

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'pharmacy_staff';
    public ?int $spar_pharmacy_id = null;
    public ?int $group_id = null;
    public bool $is_active = true;

    public function mount(): void
    {
        abort_unless($this->actor()?->canManageUsers(), 403);
    }

    private function actor(): ?PharmacyUser
    {
        return auth()->user();
    }

    private function identity(): SparIdentityProvider
    {
        return app(SparIdentityProvider::class);
    }

    /** Roles the current actor is allowed to assign (never at/above own tier). */
    public function assignableRoles(): array
    {
        $actor = $this->actor();
        if ($actor->isSuperAdmin()) {
            return ['super_admin', 'group_admin', 'pharmacy_admin', 'pharmacy_staff'];
        }
        if ($actor->isGroupAdmin()) {
            return ['pharmacy_admin', 'pharmacy_staff'];
        }
        if ($actor->isPharmacyAdmin()) {
            return ['pharmacy_staff'];
        }

        return [];
    }

    /** Pharmacies the actor may assign a new user to (their scope). */
    public function assignablePharmacies()
    {
        return SparPharmacy::visibleToCurrentActor()->orderBy('name')->get();
    }

    public function assignableGroups()
    {
        $actor = $this->actor();
        if ($actor->isSuperAdmin()) {
            return SparPharmacyGroup::orderBy('name')->get();
        }

        return SparPharmacyGroup::whereKey($this->identity()->currentGroupId())->get();
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('pharmacy_users', 'email')->ignore($this->editingId)],
            'password' => $this->editingId ? 'nullable|string|min:8' : 'required|string|min:8',
            'role' => ['required', Rule::in($this->assignableRoles())],
            'spar_pharmacy_id' => 'nullable|integer|exists:spar_pharmacies,id',
            'group_id' => 'nullable|integer|exists:spar_pharmacy_groups,id',
            'is_active' => 'boolean',
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $actor = $this->actor();

        // Pre-scope group/pharmacy for non-super actors.
        if ($actor->isGroupAdmin()) {
            $this->group_id = $actor->group_id;
        } elseif ($actor->isPharmacyAdmin()) {
            $this->spar_pharmacy_id = $actor->spar_pharmacy_id;
            $this->role = 'pharmacy_staff';
        }

        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = $this->findInScope($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->role;
        $this->spar_pharmacy_id = $user->spar_pharmacy_id;
        $this->group_id = $user->group_id;
        $this->is_active = $user->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        // Enforce assignment scope beyond validation (defence in depth).
        $this->assertAssignmentAllowed($validated);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'spar_pharmacy_id' => $validated['spar_pharmacy_id'] ?? null,
            'group_id' => $validated['group_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        if ($this->editingId) {
            $user = $this->findInScope($this->editingId);
            $user->update($data);
            $this->audit('staff_updated', $user->id);
            session()->flash('success', 'Staff member updated.');
        } else {
            $user = PharmacyUser::create($data);
            $this->audit('staff_created', $user->id);
            session()->flash('success', 'Staff member created.');
        }

        $this->resetForm();
    }

    /**
     * Guard the target scope: the chosen role must be assignable, and the
     * chosen pharmacy/group must be within the actor's manageable scope.
     */
    private function assertAssignmentAllowed(array $data): void
    {
        abort_unless(in_array($data['role'], $this->assignableRoles(), true), 403);

        $actor = $this->actor();

        // A pharmacy must be within scope (unless super assigning a group-level role).
        if (!empty($data['spar_pharmacy_id'])) {
            abort_unless($this->identity()->canManagePharmacy((int) $data['spar_pharmacy_id']), 403);
        }

        // A group must be within scope.
        if (!empty($data['group_id']) && !$actor->isSuperAdmin()) {
            abort_unless((int) $data['group_id'] === (int) $actor->group_id, 403);
        }
    }

    /** Find a user the actor is allowed to see/edit, else 403/404. */
    private function findInScope(int $id): PharmacyUser
    {
        $user = PharmacyUser::findOrFail($id);
        $actor = $this->actor();

        if ($actor->isSuperAdmin()) {
            return $user;
        }
        if ($actor->isGroupAdmin()) {
            abort_unless((int) $user->group_id === (int) $actor->group_id
                || ($user->spar_pharmacy_id && $this->identity()->canManagePharmacy((int) $user->spar_pharmacy_id)), 403);

            return $user;
        }
        if ($actor->isPharmacyAdmin()) {
            abort_unless((int) $user->spar_pharmacy_id === (int) $actor->spar_pharmacy_id, 403);

            return $user;
        }

        abort(403);
    }

    public function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->reset(['name', 'email', 'password', 'spar_pharmacy_id', 'group_id']);
        $this->role = 'pharmacy_staff';
        $this->is_active = true;
    }

    private function audit(string $event, int $userId): void
    {
        Log::channel(config('logging.channels.spar_audit') ? 'spar_audit' : 'stack')->info(
            "{$event}: pharmacy_user #{$userId}",
            ['event' => $event, 'target_user_id' => $userId, 'actor_id' => auth()->id()]
        );
    }

    public function render()
    {
        $actor = $this->actor();

        $query = PharmacyUser::query()->orderBy('name');

        // Scope the staff list.
        if (!$actor->isSuperAdmin()) {
            if ($actor->isGroupAdmin()) {
                $pharmacyIds = SparPharmacy::where('group_id', $actor->group_id)->pluck('id');
                $query->where(function ($q) use ($actor, $pharmacyIds) {
                    $q->where('group_id', $actor->group_id)
                        ->orWhereIn('spar_pharmacy_id', $pharmacyIds);
                });
            } elseif ($actor->isPharmacyAdmin()) {
                $query->where('spar_pharmacy_id', $actor->spar_pharmacy_id);
            }
        }

        return view('livewire.admin.staff-management', [
            'staff' => $query->paginate(15),
            'roles' => $this->assignableRoles(),
            'pharmacies' => $this->assignablePharmacies(),
            'groups' => $this->assignableGroups(),
        ])->layout(config('spar.layouts.staff', 'layouts.staff'));
    }
}
