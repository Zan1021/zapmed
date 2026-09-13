<?php

namespace App\Services\Notifications\Channels;

use App\Services\Notifications\ChannelResult;
use App\Services\Notifications\Contracts\NotificationChannel;
use App\Services\Notifications\OutboundMessage;
use Illuminate\Support\Facades\Mail;

/**
 * Email via Laravel Mail (Brevo SMTP / SES per config/mail.php).
 * Prefers a rich Mailable when provided; otherwise sends subject+body as raw text.
 */
class EmailChannel implements NotificationChannel
{
    public function key(): string
    {
        return 'email';
    }

    public function isConfigured(): bool
    {
        // Laravel always has a mailer (log driver at worst), so email is always
        // "deliverable" from the dispatcher's point of view.
        return true;
    }

    public function send(OutboundMessage $message): ChannelResult
    {
        $to = $message->resolvedEmail();
        $provider = (string) config('mail.default', 'smtp');

        if (empty($to)) {
            return ChannelResult::failed($provider, 'No email address for recipient.');
        }

        try {
            if ($message->mailable !== null) {
                Mail::to($to)->send($message->mailable);
            } else {
                $subject = $message->subject ?? 'Zapmed';
                $body = $message->body ?? '';
                Mail::raw($body, function ($m) use ($to, $subject) {
                    $m->to($to)->subject($subject);
                });
            }

            return ChannelResult::sent($provider);
        } catch (\Throwable $e) {
            return ChannelResult::failed($provider, $e->getMessage());
        }
    }
}
