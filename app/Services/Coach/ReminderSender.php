<?php

namespace App\Services\Coach;

use App\Models\User;
use App\Services\Notifications\Channels\WhatsAppChannel;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;

/**
 * Sends one reminder on the member's chosen channel, falling back to email (build guide 1.2 and 8.3). Every attempt
 * is logged by the dispatcher. WhatsApp counts as available only when Meta is really configured: until then the
 * channel only logs in dev mode and would silently drop the reminder, so we use email instead.
 */
class ReminderSender
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly WhatsAppChannel $whatsapp,
    ) {
    }

    public function body(string $type): string
    {
        return (string) config("coach.reminder_text.{$type}", '');
    }

    /** @return bool true when at least one attempt was delivered */
    public function send(User $user, string $type, string $preferred): bool
    {
        return $this->sendText($user, "coach.reminder.{$type}", (string) config('coach.reminder_text.subject'), $this->body($type), $preferred, ['type' => $type]);
    }

    /** Any short, generic service text on the member's chosen channel with email as the backup. */
    public function sendText(User $user, string $templateKey, string $subject, string $body, string $preferred, array $meta = []): bool
    {
        if ($body === '') {
            return false;
        }

        $first = $this->firstChoice($user, $preferred);
        $order = $first === 'email' ? ['email'] : [$first, 'email'];

        foreach ($order as $channel) {
            $message = new OutboundMessage(
                templateKey: $templateKey, category: 'transactional', user: $user,
                phone: $user->phone, subject: $subject, body: $body,
                meta: $meta + ['preferred' => $preferred],
            );

            if ($this->dispatcher->sendVia($message, $channel)->status === 'sent') {
                return true;
            }
        }

        return false;
    }
    private function firstChoice(User $user, string $preferred): string
    {
        if ($preferred === 'whatsapp' && $this->whatsapp->isConfigured() && ! empty($user->phone)) {
            return 'whatsapp';
        }

        if ($preferred === 'sms' && ! empty($user->phone)) {
            return 'sms';
        }

        return 'email';
    }
}