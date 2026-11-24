<?php

namespace App\Livewire\Coach;

use App\Services\Coach\MealPlanner;
use App\Services\Coach\ProfileSettings;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Personalise plan (design screen 18): diet, allergies and avoids. Choices are held until "Save and rebuild plan",
 * which saves them, removes every meal that is now banned (locked or not) and rebuilds the rest.
 */
class Personalise extends Component
{
    public string $diet = 'everything';

    /** @var array<int,string> */
    public array $allergies = [];

    /** @var array<int,string> */
    public array $avoids = [];

    public ?string $error = null;

    public function mount(ProfileSettings $settings): void
    {
        $p = $settings->profile(Auth::user());
        $this->diet = $p->diet ?: 'everything';
        $this->allergies = array_values((array) ($p->allergies ?? []));
        $this->avoids = array_values((array) ($p->avoids ?? []));
    }

    public function setDiet(string $diet): void
    {
        if (array_key_exists($diet, config('coach.profile.diets', []))) {
            $this->diet = $diet;
        }
    }

    public function toggle(string $field, string $key): void
    {
        $options = ['allergies' => 'coach.profile.allergies', 'avoids' => 'coach.profile.avoids'];
        if (! isset($options[$field]) || ! array_key_exists($key, config($options[$field], []))) {
            return;
        }

        $this->{$field} = in_array($key, $this->{$field}, true)
            ? array_values(array_diff($this->{$field}, [$key]))
            : array_values(array_merge($this->{$field}, [$key]));
    }

    public function save(ProfileSettings $settings, MealPlanner $planner): void
    {
        try {
            $settings->setDietary(Auth::user(), $this->diet, $this->allergies, $this->avoids);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $planner->regenerate(Auth::user());
        session()->flash('coach_toast', 'Plan rebuilt without those meals');

        $this->redirect(route('coach.plan'));
    }

    public function render()
    {
        return view('livewire.coach.personalise', [
            'diets' => config('coach.profile.diets'),
            'allergyOptions' => config('coach.profile.allergies'),
            'avoidOptions' => config('coach.profile.avoids'),
        ])->layout('layouts.coach', ['title' => 'Personalise plan', 'hideTabs' => true]);
    }
}
