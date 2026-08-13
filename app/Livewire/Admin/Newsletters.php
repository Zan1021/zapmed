<?php

namespace App\Livewire\Admin;

use App\Models\Newsletter;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class Newsletters extends Component
{
    // Compose form
    public bool $composing = false;
    public ?int $editingId = null;
    public string $subject = '';
    public string $body = '';
    public string $segment = 'all';

    public function compose(): void
    {
        $this->reset(['editingId', 'subject', 'body', 'segment']);
        $this->composing = true;
    }

    public function edit(int $id): void
    {
        $newsletter = Newsletter::findOrFail($id);
        $this->editingId = $newsletter->id;
        $this->subject = $newsletter->subject;
        $this->body = $newsletter->body;
        $this->segment = $newsletter->segment;
        $this->composing = true;
    }

    public function saveDraft(): void
    {
        $this->validate(['subject' => 'required|max:255', 'body' => 'required']);

        $data = [
            'subject' => $this->subject,
            'body' => $this->body,
            'segment' => $this->segment,
            'status' => 'draft',
        ];

        if ($this->editingId) {
            Newsletter::findOrFail($this->editingId)->update($data);
        } else {
            Newsletter::create($data);
        }

        $this->composing = false;
    }

    public function send(int $id): void
    {
        $newsletter = Newsletter::findOrFail($id);

        if ($newsletter->status === 'sent') return;

        $recipients = $newsletter->getRecipients()->get();
        $newsletter->update([
            'status' => 'sending',
            'recipients_count' => $recipients->count(),
        ]);

        $sent = 0;
        foreach ($recipients as $subscriber) {
            try {
                Mail::html($this->wrapInTemplate($newsletter->body, $subscriber), function ($message) use ($newsletter, $subscriber) {
                    $message->to($subscriber->email, $subscriber->first_name)
                        ->subject($newsletter->subject)
                        ->from(config('mail.from.address'), config('mail.from.name'));
                });
                $sent++;
            } catch (\Exception $e) {
                // Skip failed sends, continue
            }
        }

        $newsletter->update([
            'status' => 'sent',
            'sent_count' => $sent,
            'sent_at' => now(),
        ]);
    }

    public function delete(int $id): void
    {
        Newsletter::where('id', $id)->where('status', 'draft')->delete();
    }

    private function wrapInTemplate(string $body, NewsletterSubscriber $subscriber): string
    {
        $unsubUrl = url("/newsletter/unsubscribe/{$subscriber->unsubscribe_token}");
        $name = $subscriber->first_name ?: 'there';

        return <<<HTML
        <div style="max-width:600px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#333;">
            <div style="padding:20px;background:#059669;text-align:center;">
                <img src="{$this->getLogoUrl()}" alt="Zapmed" style="height:32px;">
            </div>
            <div style="padding:30px 20px;">
                <p>Hi {$name},</p>
                {$body}
            </div>
            <div style="padding:20px;background:#f3f4f6;text-align:center;font-size:12px;color:#6b7280;">
                <p>You're receiving this because you subscribed to Zapmed updates.</p>
                <a href="{$unsubUrl}" style="color:#6b7280;text-decoration:underline;">Unsubscribe</a>
            </div>
        </div>
        HTML;
    }

    private function getLogoUrl(): string
    {
        return url('/images/zapmed-logo.png');
    }

    public function render()
    {
        $newsletters = Newsletter::orderByDesc('created_at')->limit(20)->get();
        $subscriberCount = NewsletterSubscriber::active()->count();

        $segments = [
            'all' => 'All subscribers (' . $subscriberCount . ')',
            'weight-loss' => 'Weight Loss interest',
            'skincare' => 'Skincare interest',
            'mens-health' => 'Men\'s Health interest',
            'womens-health' => 'Women\'s Health interest',
            'sexual-health' => 'Sexual Health interest',
        ];

        return view('livewire.admin.newsletters', [
            'newsletters' => $newsletters,
            'subscriberCount' => $subscriberCount,
            'segments' => $segments,
        ])->layout('layouts.app');
    }
}
