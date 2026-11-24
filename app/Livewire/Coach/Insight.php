<?php

namespace App\Livewire\Coach;

use App\Services\Coach\InsightBuilder;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Insight (design screen 21): the day's headline and "what we noticed", the estimated medication level, the
 * "How are you feeling today?" check-in, today's goal, a cycle card for women, thumbs feedback and the weekly sheet.
 * Always ends with the "not medical advice" footer.
 */
class Insight extends Component
{
    public bool $showWeekly = false;

    public ?string $error = null;

    public function answer(string $answer, InsightBuilder $insights): void
    {
        try {
            $insights->answerCheckin(Auth::user(), $answer);
            $this->error = null;
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function feedback(bool $helpful, InsightBuilder $insights): void
    {
        $insights->feedback(Auth::user(), $helpful);
    }

    public function openWeekly(): void
    {
        $this->showWeekly = true;
    }

    public function closeWeekly(): void
    {
        $this->showWeekly = false;
    }

    public function render(InsightBuilder $insights)
    {
        $user = Auth::user();
        $entry = $insights->todayEntry($user);

        return view('livewire.coach.insight', [
            'daily' => $insights->daily($user),
            'entry' => $entry,
            'answers' => config('coach.insights.answers'),
            'reply' => $insights->replyFor($user, $entry),
            'weekly' => $this->showWeekly ? $insights->weekly($user) : null,
            'disclaimer' => config('coach.disclaimers.insight'),
            'medDisclaimer' => config('coach.disclaimers.medication_curve'),
        ])->layout('layouts.coach', ['title' => 'Insight']);
    }
}
