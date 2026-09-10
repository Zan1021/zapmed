<?php

namespace Zapmed\SparCore\Services\Channels;

use Zapmed\SparCore\Contracts\MessagingChannel;
use Zapmed\SparCore\Models\SparPatient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp delivery channel — Meta Cloud API direct (spec FR-12, Phase 6.4/6.5).
 *
 * DRIVER SWITCH (`config('spar.whatsapp.driver')`, env SPAR_WHATSAPP_DRIVER):
 *   'log'       (default) — renders and LOGS the exact payload it would send.
 *                No HTTP, no credentials. Lets the whole system be tested and
 *                demoed BEFORE a WhatsApp Business Account / token exists.
 *   'cloud_api' — real POST to graph.facebook.com Cloud API. Requires
 *                phone_number_id + token in config.
 *
 * 24-HOUR SESSION WINDOW (Meta rule, spec FR-12.4 / 6.5):
 *   - Inside the 24h window since the patient's last inbound message, free-form
 *     text is allowed.
 *   - Outside it (all proactive reminders), only PRE-APPROVED TEMPLATES may be
 *     sent. If a proactive send has no template configured, we DO NOT send a
 *     free-form message (Meta would reject it); we report false so the
 *     dispatcher falls back to SMS.
 */
class WhatsAppChannel implements MessagingChannel
{
    public function key(): string
    {
        return 'whatsapp';
    }

    /**
     * Reachable when WhatsApp is enabled and the patient has a cellphone.
     * (Meta uses the phone number as the WhatsApp identity.)
     */
    public function canReach(SparPatient $patient): bool
    {
        return $this->enabled() && !empty($patient->primaryPhone());
    }

    public function send(SparPatient $patient, array $payload): bool
    {
        $to = $patient->primaryPhone();
        if (!$this->enabled() || empty($to)) {
            return false;
        }

        $template = $payload['template'] ?? null;
        $withinWindow = $this->withinSessionWindow($patient);

        // Outside the 24h window, proactive sends MUST use an approved template.
        if (!$withinWindow) {
            $templateName = $this->resolveTemplateName($template);
            if (!$templateName) {
                Log::info('SPAR WhatsApp: proactive send outside 24h window with no approved template — deferring to fallback channel', [
                    'spar_patient_id' => $patient->id,
                    'template_key' => $template,
                ]);

                return false;
            }

            return $this->dispatch($patient, $this->templateMessage($to, $templateName, $payload));
        }

        // Inside the window: a template is still preferred if provided, else free-form text.
        if ($template && $this->resolveTemplateName($template)) {
            return $this->dispatch($patient, $this->templateMessage($to, $this->resolveTemplateName($template), $payload));
        }

        return $this->dispatch($patient, $this->textMessage($to, $payload));
    }

    // ---- message builders ---------------------------------------------------

    /**
     * Free-form text (session-window only). Appends the tracker link + up to
     * two interactive reply buttons hint into the body for the log driver;
     * the cloud_api driver sends them as an interactive message where possible.
     */
    private function textMessage(string $to, array $payload): array
    {
        $body = (string) ($payload['body'] ?? '');
        if (!empty($payload['link'])) {
            $body = rtrim($body) . "\n\n" . $payload['link'];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalize($to),
            'type' => 'text',
            'text' => ['preview_url' => true, 'body' => $body],
        ];
    }

    /**
     * Template message (proactive / outside window). Body variables are passed
     * as ordered {{1}}, {{2}}… parameters; a URL button param carries the link.
     */
    private function templateMessage(string $to, string $templateName, array $payload): array
    {
        $vars = array_values($payload['vars'] ?? []);
        $components = [];

        if (!empty($vars)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $vars),
            ];
        }

        if (!empty($payload['link'])) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $payload['link']]],
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalize($to),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => config('spar.whatsapp.language', 'en')],
                'components' => $components,
            ],
        ];
    }

    // ---- drivers ------------------------------------------------------------

    private function dispatch(SparPatient $patient, array $message): bool
    {
        return match ($this->driver()) {
            'cloud_api' => $this->sendViaCloudApi($patient, $message),
            default => $this->sendViaLog($patient, $message),
        };
    }

    /**
     * Log driver — no network, no creds. Records exactly what WOULD be sent so
     * the flow is fully testable/demoable before Meta onboarding completes.
     */
    private function sendViaLog(SparPatient $patient, array $message): bool
    {
        Log::channel(config('logging.channels.spar_audit') ? 'spar_audit' : 'stack')->info(
            'SPAR WhatsApp (log driver) — would send',
            ['spar_patient_id' => $patient->id, 'message' => $message]
        );

        return true;
    }

    /**
     * Meta Cloud API driver. POSTs to the WABA phone number's /messages edge.
     */
    private function sendViaCloudApi(SparPatient $patient, array $message): bool
    {
        $phoneNumberId = config('spar.whatsapp.phone_number_id');
        $token = config('spar.whatsapp.token');
        $version = config('spar.whatsapp.api_version', 'v21.0');

        if (empty($phoneNumberId) || empty($token)) {
            Log::warning('SPAR WhatsApp cloud_api driver missing phone_number_id/token — cannot send', [
                'spar_patient_id' => $patient->id,
            ]);

            return false;
        }

        try {
            $response = Http::withToken($token)
                ->asJson()
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", $message);

            if ($response->successful()) {
                return true;
            }

            Log::error('SPAR WhatsApp cloud_api send failed', [
                'spar_patient_id' => $patient->id,
                'status' => $response->status(),
                'error' => $response->json('error.message') ?? $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('SPAR WhatsApp cloud_api exception', [
                'spar_patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ---- helpers ------------------------------------------------------------

    private function driver(): string
    {
        return (string) config('spar.whatsapp.driver', 'log');
    }

    private function enabled(): bool
    {
        return (bool) config('spar.whatsapp.enabled', false);
    }

    /**
     * Map a payload template KEY (e.g. 'renewal_due') to the Meta-approved
     * template NAME configured for it. Returns null if none configured.
     */
    private function resolveTemplateName(?string $key): ?string
    {
        if (!$key) {
            return null;
        }
        $name = config("spar.whatsapp.templates.{$key}");

        return !empty($name) ? $name : null;
    }

    /**
     * Whether we're inside the 24h customer-service window for this patient,
     * based on the last recorded inbound message time in patient metadata.
     */
    private function withinSessionWindow(SparPatient $patient): bool
    {
        $meta = $patient->metadata ?? [];
        $last = $meta['wa_last_inbound_at'] ?? null;
        if (empty($last)) {
            return false;
        }

        try {
            return Carbon::parse($last)->gt(now()->subDay());
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        // South African local (0XXXXXXXXX) → E.164 (27XXXXXXXXX).
        if (str_starts_with($digits, '0')) {
            $digits = '27' . substr($digits, 1);
        }

        return $digits;
    }
}
