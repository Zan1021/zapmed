<?php

namespace App\Livewire\Admin;

use App\Models\Addon;
use App\Services\Operations\Addons;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** The add-ons Operations offer (Craig's guide 6.2): name, monthly price, the service categories it is offered for. */
class AddonSettings extends Component
{
    public ?int $editing = null;
    public string $name = '';
    public string $price = '';
    public string $categories = '';        // comma separated words
    public bool $active = true;

    public function edit(int $id): void
    {
        $a = Addon::findOrFail($id);
        $this->resetErrorBag();
        $this->editing = $a->id;
        $this->name = $a->name;
        $this->price = number_format($a->price_cents / 100, 2, '.', '');
        $this->categories = implode(', ', $a->categories ?? []);
        $this->active = $a->is_active;
    }

    public function cancel(): void
    {
        $this->reset(['editing', 'name', 'price', 'categories']);
        $this->active = true;
        $this->resetErrorBag();
    }

    public function save(Addons $addons): void
    {
        $this->resetErrorBag();

        $rand = str_replace([' ', ','], ['', '.'], trim($this->price));
        if (! is_numeric($rand)) {
            $this->addError('price', 'Enter the monthly price in rand, for example 250.00.');

            return;
        }

        $addons->save(
            Auth::user(), $this->editing ? Addon::findOrFail($this->editing) : null, $this->name,
            (int) round(((float) $rand) * 100), array_map('trim', explode(',', $this->categories)), $this->active,
        );

        $this->cancel();
    }

    public function render()
    {
        return view('livewire.admin.addon-settings', ['addons' => Addon::orderBy('name')->get()])->layout('layouts.app');
    }
}
