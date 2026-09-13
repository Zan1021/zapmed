<?php

namespace App\Services\Notifications;

use App\Models\NotificationLog;
use App\Services\CommunicationPreferenceService;
use App\Services\Notifications\Channels\EmailChannel;
use App\Services\Notifications\Channels\SmsChannel;
use App\Services\Notifications\Channels\WhatsAppChannel;
use App\Services\Notifications\Contracts\NotificationChannel;

/**
 * The single choke point for every outbound message (UAT Task 8, Option B).
 *
 * For each requested channel it:
 *   1. Consults CommunicationPreferenceService (marketing can be opted out of;
 *      transactional/clinical is always allowed) — never lets a channel sneak
 *      marketing past an opt-out or suppress a safety message.
 *   2. Delegates delivery to the channel.
 *   3. Writes exactly one NotificationLog row per attempt (sent/failed/suppressed/skipped).
 *
 * Channels are register-able so tests can inject fakes.
 */
class NotificationDispatcher
{
    /** @var array<string, NotificationChannel> */
    private array $channels = [];

    public function __construct(
        private CommunicationPreferenceService $preferences,
        EmailChannel $email,
        SmsChannel $sms,
        WhatsAppChannel $whatsapp,
    ) {
        $this->register($email);
        $this->register($sms);
        $this->register($whatsapp);
    }

    public function register(NotificationChannel $channel): void
    {
        $this->channels[$channel->key()] = $channel;
    }

    /**
     * Send a message across one or more channels.
     *
     * @param  array<int, string>  $channels  channel keys: email|sms|whatsapp
     * @return array<int, NotificationLog>    the log rows written
     */
    public function send(OutboundMessage $message, array $channels): array
    {
        $logs = [];

        foreach ($channels as $key) {
            $logs[] = $this->sendOne($message, $key);
        }

        return $logs;
    }

    /** Convenience for a single channel. */
    public function sendVia(OutboundMessage $message, string $channel): NotificationLog
    {
        return $this->sendOne($message, $channel);
    }

    /**
     * Queue a rich Mailable for email delivery and record the hand-off in the log.
     *
     * Used by hot paths (e.g. payment webhooks) that must return fast and cannot
     * block on synchronous SMTP. The log row records status=sent meaning "queued
     * for delivery"; actual transport failures surface via the queue/failed_jobs,
     * not here. Preference gating still applies before queueing.
     *
     * @param  \Illuminate\Mail\Mailable  $mailable
     */
    public function queueMailable(OutboundMessage $message): NotificationLog
    {
        $to = $message->resolvedEmail();

        if ($message->user !== null && !$this->preferences->canReceive($message->user, $message->category)) {
            return $this->log($message, NotificationLog::CHANNEL_EMAIL, NotificationLog::STATUS_SUPPRESSED, null, null, 'Recipient opted out of ' . $message->category . '.');
        }

        if (empty($to) || $message->mailable === null) {
            return $this->log($message, NotificationLog::CHANNEL_EMAIL, NotificationLog::STATUS_SKIPPED, null, null, 'No email address or no mailable to queue.');
        }

        $provider = (string) config('mail.default', 'smtp');

        try {
            \Illuminate\Support\Facades\Mail::to($to)->queue($message->mailable);

            return $this->log($message, NotificationLog::CHANNEL_EMAIL, NotificationLog::STATUS_SENT, $provider, null, null);
        } catch (\Throwable $e) {
            return $this->log($message, NotificationLog::CHANNEL_EMAIL, NotificationLog::STATUS_FAILED, $provider, null, $e->getMessage());
        }
    }

    private function sendOne(OutboundMessage $message, string $channelKey): NotificationLog
    {
        $channel = $this->channels[$channelKey] ?? null;

        if ($channel === null) {
            return $this->log($message, $channelKey, NotificationLog::STATUS_SKIPPED, null, null, "Unknown channel '{$channelKey}'.");
        }

        // Preference gate. Transactional always passes; marketing respects opt-out.
        if ($message->user !== null && !$this->preferences->canReceive($message->user, $message->category)) {
            return $this->log($message, $channelKey, NotificationLog::STATUS_SUPPRESSED, null, null, 'Recipient opted out of ' . $message->category . '.');
        }

        $result = $channel->send($message);

        return $this->log(
            $message,
            $channelKey,
            $result->ok ? NotificationLog::STATUS_SENT : NotificationLog::STATUS_FAILED,
            $result->provider,
            $result->providerRef,
            $result->error,
        );
    }

    private function log(
        OutboundMessage $message,
        string $channel,
        string $status,
        ?string $provider,
        ?string $providerRef,
        ?string $error,
    ): NotificationLog {
        $recipient = match ($channel) {
            NotificationLog::CHANNEL_EMAIL => $message->resolvedEmail(),
            NotificationLog::CHANNEL_SMS, NotificationLog::CHANNEL_WHATSAPP => $message->phone,
            default => null,
        } ?? 'unknown';

        return NotificationLog::create([
            'channel' => $channel,
            'template_key' => $message->templateKey,
            'category' => $message->category,
            'user_id' => $message->user?->id,
            'recipient' => $recipient,
            'status' => $status,
            'provider' => $provider,
            'provider_ref' => $providerRef,
            'error' => $error,
            'meta' => $message->meta ?: null,
        ]);
    }
}
