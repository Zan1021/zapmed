<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\Medication;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Message wording and on/off switches (Craig's guide 6.4 and 8.3).
 *
 *  - The built-in wording lives in config operations.templates.registry. Operations can save different wording or switch a
 *    message off; "reset" goes back to the built-in text.
 *  - A message can only use the {placeholders} listed for it and must keep its required ones.
 *  - No health detail: a saved text is refused if it contains a banned word or the name of any medicine in the product list.
 *  - Every save and reset keeps a version row; the version number is written into the notification log with each message.
 *  - render() is what the senders call. It returns null when the message is switched off.
 */
class MessageTemplates
{
    public function registry(): array
    {
        return config('operations.templates.registry');
    }

    /**
     * @param array<string,string> $vars placeholder values
     * @return array{subject:string, body:string, version:int}|null null = switched off
     */
    public function render(string $key, array $vars = []): ?array
    {
        $def = $this->registry()[$key] ?? throw new \InvalidArgumentException("Unknown message template '{$key}'.");
        $row = MessageTemplate::where('key', $key)->first();

        if ($row && ! $row->is_enabled) {
            return null;
        }

        $fill = function (string $text) use ($vars, $def): string {
            foreach ($def['placeholders'] as $p) {
                $text = str_replace('{'.$p.'}', (string) ($vars[$p] ?? ''), $text);
            }

            return trim(preg_replace('/ {2,}/', ' ', $text));
        };

        return [
            'subject' => $fill($row?->subject ?? $def['subject']),
            'body' => $fill($row?->body ?? $def['body']),
            'version' => $row?->version ?? 0,
        ];
    }

    public function isCustom(string $key): bool
    {
        return MessageTemplate::where('key', $key)->exists();
    }

    public function save(User $ops, string $key, string $subject, string $body, bool $enabled): MessageTemplate
    {
        abort_unless($ops->role === UserRole::Admin, 403);

        $def = $this->registry()[$key] ?? null;
        if (! $def) {
            throw ValidationException::withMessages(['key' => 'That message does not exist.']);
        }

        $subject = trim($subject);
        $body = trim($body);
        if ($subject === '' || mb_strlen($subject) > 120) {
            throw ValidationException::withMessages(['subject' => 'The subject needs 1 to 120 characters.']);
        }
        if ($body === '' || mb_strlen($body) > 1000) {
            throw ValidationException::withMessages(['body' => 'The message needs 1 to 1000 characters.']);
        }

        foreach ([$subject, $body] as $text) {
            preg_match_all('/\{([a-z_]+)\}/i', $text, $found);
            $bad = array_diff($found[1], $def['placeholders']);
            if ($bad) {
                throw ValidationException::withMessages(['body' => 'You can only use these placeholders here: '.($def['placeholders'] ? '{'.implode('}, {', $def['placeholders']).'}' : 'none').'. Found {'.implode('}, {', $bad).'}.']);
            }
        }
        foreach ($def['required'] as $need) {
            if (! str_contains($body, '{'.$need.'}')) {
                throw ValidationException::withMessages(['body' => 'This message must keep {'.$need.'}.']);
            }
        }

        $this->assertNoHealthDetail($subject.' '.$body);

        return DB::transaction(function () use ($ops, $key, $subject, $body, $enabled) {
            $version = $this->nextVersion($key);

            $row = MessageTemplate::updateOrCreate(['key' => $key], [
                'subject' => $subject, 'body' => $body, 'is_enabled' => $enabled, 'version' => $version, 'updated_by' => $ops->id,
            ]);
            $this->history($key, $version, $subject, $body, $enabled, 'saved', $ops);

            return $row;
        });
    }

    /** Back to the built-in wording and switched on. The version number keeps counting up so the log stays unambiguous. */
    public function reset(User $ops, string $key): void
    {
        abort_unless($ops->role === UserRole::Admin, 403);

        $row = MessageTemplate::where('key', $key)->first();
        if (! $row) {
            throw ValidationException::withMessages(['key' => 'This message already uses the built-in wording.']);
        }

        DB::transaction(function () use ($ops, $key, $row) {
            $this->history($key, $this->nextVersion($key), null, null, true, 'reset', $ops);
            $row->delete();
        });
    }

    /** Refuse health detail: banned words and medicine names from the product list. */
    public function assertNoHealthDetail(string $text): void
    {
        $lower = mb_strtolower($text);

        foreach (config('operations.templates.banned_words') as $w) {
            if (preg_match('/(?<![a-z])'.preg_quote(mb_strtolower($w), '/').'/u', $lower)) {
                throw ValidationException::withMessages(['body' => 'Messages must not contain health detail. Please remove "'.$w.'".']);
            }
        }

        $names = Medication::query()->where('is_active', true)->pluck('name');
        foreach ($names as $name) {
            $n = mb_strtolower(trim((string) $name));
            if (mb_strlen($n) >= 4 && preg_match('/(?<![a-z])'.preg_quote($n, '/').'(?![a-z])/u', $lower)) {
                throw ValidationException::withMessages(['body' => 'Messages must not name a medicine. Please remove "'.$name.'".']);
            }
        }
    }

    /** One more than any version ever used for this message (a reset also counts), so a version number is never used twice. */
    private function nextVersion(string $key): int
    {
        return (int) DB::table('message_template_versions')->where('template_key', $key)->max('version') + 1;
    }

    private function history(string $key, int $version, ?string $subject, ?string $body, bool $enabled, string $change, User $ops): void
    {
        DB::table('message_template_versions')->insert([
            'template_key' => $key, 'version' => $version, 'subject' => $subject, 'body' => $body, 'is_enabled' => $enabled,
            'change' => $change, 'changed_by' => $ops->id, 'created_at' => now(),
        ]);
    }
}
