<?php

namespace Zapmed\SparCore\Services;

use Zapmed\SparCore\Contracts\MessagingChannel;
use Zapmed\SparCore\Models\SparPatient;
use Zapmed\SparCore\Services\Channels\EmailChannel;
use Zapmed\SparCore\Services\Channels\InAppChannel;
use Illuminate\Support\Facades\Log;

/**
 * Resolves and dispatches SPAR patient messaging across channels in the
 * configured priority order (spec FR-12). WhatsApp is the intended primary
 * (deferred); SMS is the permanent fallback; email is a push option; in-app
 * is a pull-based companion that is always recorded.
 *
 * Guarantees no configuration leaves a contactable patient unreachable: if the
 * configured push channels can't reach the patient but SMS could, SMS is used
 * as an implicit fallback.
 *
 * Channel registry is HOST-INJECTED so the package carries no dependency on any
 * host's SMS/WhatsApp implementation. The host binds its concrete channels via
 * `config('spar.channel_factories')` (map of key => resolver callable/class) or
 * by passing an explicit registry to the constructor. The package ships in-app
 * and email as portable defaults; every host supplies its own SMS channel.
 */
class MessagingDispatcher
{
    /** @var array<string, MessagingChannel> */
    private array $channels;

    public function __construct(?array $channels = null)
    {
        $this->channels = $channels ?? $this->resolveChannels();
    }

    /**
     * Build the channel registry. Package defaults (in-app, email) plus any
     * host-registered channels resolved from `spar.channel_factories`. Each
     * factory is a class name or callable returning a MessagingChannel.
     * WhatsApp is intentionally omitted until a provider is provisioned.
     */
    private function resolveChannels(): array
    {
        $channels = [
            'inapp' => new InAppChannel(),
            'email' => new EmailChannel(),
        ];

        foreach ((array) config('spar.channel_factories', []) as $key => $factory) {
            $resolved = $this->resolveFactory($factory);
            if ($resolved instanceof MessagingChannel) {
                $channels[$key] = $resolved;
            }
        }

        return $channels;
    }

    private function resolveFactory(mixed $factory): ?MessagingChannel
    {
        try {
            if (is_callable($factory)) {
                $instance = $factory();
            } elseif (is_string($factory) && class_exists($factory)) {
                $instance = app($factory);
            } else {
                return null;
            }
        } catch (\Throwable $e) {
            Log::warning('SPAR channel factory failed to resolve', [
                'factory' => is_string($factory) ? $factory : gettype($factory),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $instance instanceof MessagingChannel ? $instance : null;
    }

    /**
     * Send a message to a patient. Records the in-app companion always, then
     * delivers via the first push channel (in priority order) that can reach
     * the patient. Returns true if any push channel succeeded.
     *
     * @param  array  $payload  ['subject'=>?, 'body'=>string, 'link'=>?, 'template'=>?, 'vars'=>array]
     */
    public function send(SparPatient $patient, array $payload): bool
    {
        // Never message a patient who hasn't consented (spec FR-7.2).
        if (!$patient->hasConsented()) {
            return false;
        }

        // Always record the in-app companion so the card appears in the app.
        if (isset($this->channels['inapp'])) {
            $this->channels['inapp']->send($patient, $payload);
        }

        foreach ($this->priorityOrder() as $key) {
            $channel = $this->channels[$key] ?? null;
            if (!$channel instanceof MessagingChannel) {
                continue;
            }
            if (!$channel->canReach($patient)) {
                continue;
            }
            if ($channel->send($patient, $payload)) {
                return true;
            }
        }

        // Implicit safety net: ensure SMS fallback is attempted even if it was
        // not in the configured list, so a contactable patient never goes dark.
        if (!in_array('sms', $this->priorityOrder(), true)
            && isset($this->channels['sms'])
            && $this->channels['sms']->canReach($patient)) {
            return $this->channels['sms']->send($patient, $payload);
        }

        Log::info('SPAR message not delivered on any push channel', [
            'spar_patient_id' => $patient->id,
        ]);

        return false;
    }

    /**
     * Configured priority order (`spar.channels`), filtered to known channels.
     */
    private function priorityOrder(): array
    {
        $configured = (array) config('spar.channels', ['inapp', 'email', 'sms']);

        return array_values(array_filter(
            $configured,
            fn ($key) => isset($this->channels[$key])
        ));
    }
}
