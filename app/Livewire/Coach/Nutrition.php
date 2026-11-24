<?php

namespace App\Livewire\Coach;

use App\Models\MealPlanItem;
use App\Services\Coach\MealLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Daily nutrition (design screen 15): protein, fibre and carb bars, calories and fat as numbers, and a card for each
 * meal slot with what was logged today. Removing an entry that came from the plan unticks that meal.
 */
class Nutrition extends Component
{
    public function remove(int $entryId, MealLogger $logger): void
    {
        $logger->remove(Auth::user(), $entryId);
    }

    public function render(MealLogger $logger)
    {
        $user = Auth::user();

        return view('livewire.coach.nutrition', [
            'totals' => $logger->todayTotals($user),
            'entries' => $logger->todayEntries($user)->groupBy('slot'),
            'slots' => config('coach.recipes.slots'),
            'targets' => config('coach.targets'),
        ])->layout('layouts.coach', ['title' => 'Nutrition']);
    }
}
