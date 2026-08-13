<?php

namespace App\Livewire\Admin;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class Partners extends Component
{
    use WithPagination;

    // Create/Edit form
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $slug = '';
    public string $website_url = '';
    public string $contact_name = '';
    public string $contact_email = '';
    public string $contact_phone = '';
    public int $commission_consultation = 10;
    public int $commission_medication = 5;
    public int $cookie_days = 30;
    public string $status = 'active';

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'slug', 'website_url', 'contact_name', 'contact_email', 'contact_phone', 'commission_consultation', 'commission_medication', 'cookie_days', 'status']);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $partner = Partner::findOrFail($id);
        $this->editingId = $partner->id;
        $this->name = $partner->name;
        $this->slug = $partner->slug;
        $this->website_url = $partner->website_url ?? '';
        $this->contact_name = $partner->contact_name;
        $this->contact_email = $partner->contact_email;
        $this->contact_phone = $partner->contact_phone ?? '';
        $this->commission_consultation = $partner->commission_consultation;
        $this->commission_medication = $partner->commission_medication;
        $this->cookie_days = $partner->cookie_days;
        $this->status = $partner->status;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:50|alpha_dash|unique:partners,slug,' . $this->editingId,
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'commission_consultation' => 'required|integer|min:0|max:50',
            'commission_medication' => 'required|integer|min:0|max:50',
            'cookie_days' => 'required|integer|min:1|max:365',
            'status' => 'required|in:pending,active,suspended',
        ]);

        $data = [
            'name' => $this->name,
            'slug' => Str::slug($this->slug),
            'website_url' => $this->website_url ?: null,
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone ?: null,
            'commission_consultation' => $this->commission_consultation,
            'commission_medication' => $this->commission_medication,
            'cookie_days' => $this->cookie_days,
            'status' => $this->status,
        ];

        if ($this->editingId) {
            Partner::findOrFail($this->editingId)->update($data);
        } else {
            // Create a user account for the partner
            $user = User::create([
                'first_name' => explode(' ', $this->contact_name)[0],
                'last_name' => explode(' ', $this->contact_name, 2)[1] ?? '',
                'email' => $this->contact_email,
                'password' => Hash::make(Str::random(16)),
                'role' => 'partner',
            ]);

            $data['user_id'] = $user->id;
            Partner::create($data);
        }

        $this->showForm = false;
        $this->reset(['editingId']);
    }

    public function toggleStatus(int $id): void
    {
        $partner = Partner::findOrFail($id);
        $partner->update([
            'status' => $partner->status === 'active' ? 'suspended' : 'active',
        ]);
    }

    public function updatedName(): void
    {
        if (!$this->editingId) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function render()
    {
        $partners = Partner::withCount('referrals')
            ->withSum('commissions', 'commission_amount')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.admin.partners', ['partners' => $partners])
            ->layout('layouts.app');
    }
}
