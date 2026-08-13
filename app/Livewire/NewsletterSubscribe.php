<?php

namespace App\Livewire;

use App\Models\NewsletterSubscriber;
use Livewire\Component;

class NewsletterSubscribe extends Component
{
    public string $email = '';
    public string $firstName = '';
    public string $interest = '';
    public bool $subscribed = false;
    public ?string $error = null;

    public function subscribe(): void
    {
        $this->validate(['email' => 'required|email']);

        $existing = NewsletterSubscriber::where('email', $this->email)->first();

        if ($existing && $existing->status === 'active') {
            $this->subscribed = true;
            return;
        }

        if ($existing) {
            $existing->update(['status' => 'active', 'unsubscribed_at' => null]);
        } else {
            $interests = $this->interest ? [$this->interest] : [];

            NewsletterSubscriber::create([
                'email' => $this->email,
                'first_name' => $this->firstName ?: null,
                'interests' => $interests ?: null,
                'source' => 'website',
            ]);
        }

        $this->subscribed = true;
    }

    public function render()
    {
        return view('livewire.newsletter-subscribe');
    }
}
