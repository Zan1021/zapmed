<?php

namespace App\Livewire\Admin;

use App\Models\RepeatCycle;
use App\Services\Operations\RepeatCycles;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Repeat-cycle settings (Craig's guide 6.4): pack length, first-repeat day, same-day flag, on or off. */
class RepeatCycleSettings extends Component
{
    public ?int $editing = null;
    public string $description = '';
    public string $days = '';
    public string $firstRepeatDays = '';
    public bool $sameDay = false;
    public bool $enabled = true;

    public function edit(int $id): void
    {
        $c = RepeatCycle::findOrFail($id);
        $this->resetErrorBag();
        $this->editing = $c->id;
        $this->description = $c->description;
        $this->days = (string) $c->days;
        $this->firstRepeatDays = (string) $c->first_repeat_days;
        $this->sameDay = $c->same_day;
        $this->enabled = $c->is_enabled;
    }

    public function cancel(): void
    {
        $this->reset(['editing', 'description', 'days', 'firstRepeatDays', 'sameDay']);
        $this->enabled = true;
        $this->resetErrorBag();
    }

    public function save(RepeatCycles $cycles): void
    {
        $this->resetErrorBag();

        $cycles->save(
            Auth::user(), $this->editing ? RepeatCycle::findOrFail($this->editing) : null,
            $this->description, (int) $this->days, (int) $this->firstRepeatDays, $this->sameDay, $this->enabled,
        );

        $this->cancel();
    }

    public function toggle(int $id, RepeatCycles $cycles): void
    {
        $this->resetErrorBag();
        $cycles->toggle(Auth::user(), RepeatCycle::findOrFail($id));
    }

    public function render()
    {
        return view('livewire.admin.repeat-cycle-settings', [
            'cycles' => RepeatCycle::orderBy('days')->orderBy('same_day')->get(),
        ])->layout('layouts.app');
    }
}
