<?php

namespace App\Livewire\Coach;

use App\Services\Coach\CoachCalls;
use App\Services\Coach\CoachMessaging;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Book a call (design screen 24): the next 5 working days, 15 or 30 minutes, open slots (taken ones greyed).
 */
class BookCall extends Component
{
    #[Url(as: 'for')]
    public ?string $purpose = null;

    public ?string $date = null;

    public int $length = 15;

    public ?string $slot = null;

    public ?string $error = null;

    public function mount(CoachCalls $calls): void
    {
        $this->date = $calls->days()->first()?->toDateString();
        $this->length = $calls->lengths()[0] ?? 15;
    }

    public function pickDate(string $date, CoachCalls $calls): void
    {
        if ($calls->days()->contains(fn ($d) => $d->toDateString() === $date)) {
            $this->date = $date;
            $this->slot = null;
            $this->error = null;
        }
    }

    public function pickLength(int $length, CoachCalls $calls): void
    {
        if (in_array($length, $calls->lengths(), true)) {
            $this->length = $length;
            $this->slot = null;
            $this->error = null;
        }
    }

    public function pickSlot(string $time): void
    {
        $this->slot = $time;
        $this->error = null;
    }

    public function confirm(CoachCalls $calls)
    {
        if (! $this->date || ! $this->slot) {
            $this->error = 'Please pick a day and a time.';

            return;
        }

        try {
            $when = CarbonImmutable::parse("{$this->date} {$this->slot}", config('coach.timezone'));
            $call = $calls->book(Auth::user(), $when, $this->length, $this->purpose);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
            $this->slot = null;

            return;
        } catch (\Throwable) {
            $this->error = 'That time is not available. Please pick another.';
            $this->slot = null;

            return;
        }

        return redirect()->route('coach.today')->with('coach_toast', 'Call booked for '.$calls->when($call).'. A confirmation is on its way.');
    }

    public function cancel(int $id, CoachCalls $calls): void
    {
        if ($calls->cancel(Auth::user(), $id)) {
            $this->dispatch('coach-toast', message: 'Call cancelled');
        }
        $this->error = null;
    }

    public function render(CoachCalls $calls, CoachMessaging $messaging)
    {
        $user = Auth::user();
        $upcoming = $calls->upcomingFor($user);
        $days = $calls->days();
        $date = $this->date ? CarbonImmutable::parse($this->date, config('coach.timezone')) : null;
        $slots = ($date && ! $upcoming) ? $calls->slots($user, $date, $this->length) : [];

        return view('livewire.coach.book-call', [
            'coachName' => $messaging->coachName($user),
            'hasCoach' => (bool) $messaging->coachFor($user),
            'days' => $days,
            'lengths' => $calls->lengths(),
            'slots' => $slots,
            'upcoming' => $upcoming,
            'upcomingText' => $upcoming ? $calls->when($upcoming) : null,
            'anyOpen' => collect($slots)->contains('available', true),
            'note' => config('coach.calls.fully_booked_note'),
            'unassigned' => config('coach.messaging.unassigned_text'),
        ])->layout('layouts.coach', ['title' => 'Book a call', 'hideTabs' => true]);
    }
}