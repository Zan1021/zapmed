<?php

namespace App\Services\Patient;

use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\OutboundMessage;

/**
 * Sends a service message to a patient on the channel they chose, and on the other channel if that one fails
 * (Craig's guide 3.3). Every attempt is one NotificationLog row; attempt number, fallback flag and template are kept in
 * the log meta. Stops at the first channel that delivers. Never throws.
 *
 * Callers must keep health detail and medicine names out of the message (guide 8.3).
 */
class PatientMessenger
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly PatientChannels $channels,
    ) {
    }

    /** @return NotificationLog[] one row per attempt, in order */
    public function send(OutboundMessage $message): array
    {
        $user = $message->user;
        $order = $user ? $this->channels->order($user) : ['email'];
        $logs = [];

        foreach ($order as $i => $channel) {
            $tagged = new OutboundMessage(
                templateKey: $message->templateKey,
                category: $message->category,
                user: $message->user,
                email: $message->email,
                phone: $message->phone ?? $user?->phone,
                subject: $message->subject,
                body: $message->body,
                mailable: $message->mailable,
                meta: array_merge($message->meta, ['attempt' => $i + 1, 'fallback' => $i > 0, 'chosen_channel' => $order[0]]),
            );

            try {
                $log = $this->dispatcher->sendVia($tagged, $channel);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('patient message: channel threw', ['channel' => $channel, 'error' => $e->getMessage()]);

                continue;
            }

            $logs[] = $log;
            if ($log->status === NotificationLog::STATUS_SENT) {
                break;
            }
        }

        return $logs;
    }
}
