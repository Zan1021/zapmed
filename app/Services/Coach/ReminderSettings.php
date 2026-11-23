<?php

namespace App\Services\Coach;

use App\Models\ReminderSetting;
use App\Models\User;
use InvalidArgumentException;

/**
 * A member's reminder settings (build guide 5.7), always returned complete: saved values over config defaults.
 * Every change is validated against the options the screen offers.
 */
class ReminderSettings
{
    public const TYPES = ['hydration', 'weigh_in', 'dose_day', 'meals', 'cycle'];

    /** @return array<string,array<string,mixed>> type => settings incl. 'enabled' */
    public function all(User $user): array
    {
        $rows = ReminderSetting::where('user_id', $user->id)->get()->keyBy('type');
        $out = [];

        foreach (self::TYPES as $type) {
            $defaults = config("coach.reminders.{$type}", []);
            $row = $rows->get($type);
            $merged = array_merge($defaults, $row?->settings ?? []);
            $merged['enabled'] = $row ? (bool) $row->enabled : (bool) ($defaults['enabled'] ?? true);

            if ($type === 'hydration') {
                $merged['channel'] = $this->channel($merged['channel'] ?? null);
            }

            $out[$type] = $merged;
        }

        return $out;
    }

    public function get(User $user, string $type): array
    {
        return $this->all($user)[$type] ?? throw new InvalidArgumentException('Unknown reminder.');
    }

    public function toggle(User $user, string $type): bool
    {
        $now = ! $this->get($user, $type)['enabled'];
        $this->save($user, $type, [], $now);

        return $now;
    }

    /** Change one option, e.g. ('hydration', 'every_hours', 3). Throws if the value is not on offer. */
    public function set(User $user, string $type, string $key, mixed $value): void
    {
        $this->save($user, $type, [$key => $this->validated($type, $key, $value)], null);
    }

    /** 'both' and 'push' (older values) mean WhatsApp now; anything unknown too. */
    public function channel(?string $value): string
    {
        return array_key_exists((string) $value, config('coach.reminder_options.channels', [])) ? $value : 'whatsapp';
    }

    private function validated(string $type, string $key, mixed $value): mixed
    {
        $o = config('coach.reminder_options');

        $rules = [
            'hydration' => [
                'every_hours' => fn ($v) => in_array((int) $v, $o['every_hours'], true) ? (int) $v : null,
                'from' => fn ($v) => in_array($v, $o['from'], true) ? $v : null,
                'until' => fn ($v) => in_array($v, $o['until'], true) ? $v : null,
                'smart_skip' => fn ($v) => is_bool($v) ? $v : (in_array($v, ['1', 1, 'true'], true) ? true : (in_array($v, ['0', 0, 'false'], true) ? false : null)),
                'channel' => fn ($v) => array_key_exists((string) $v, $o['channels']) ? $v : null,
            ],
            'weigh_in' => [
                'day' => fn ($v) => in_array(strtolower((string) $v), $o['days'], true) ? strtolower($v) : null,
                'frequency' => fn ($v) => in_array($v, $o['frequencies'], true) ? $v : null,
            ],
            'dose_day' => [
                'time' => fn ($v) => in_array($v, $o['dose_times'], true) ? $v : null,
            ],
        ];

        $check = $rules[$type][$key] ?? null;
        if (! $check) {
            throw new InvalidArgumentException('That option cannot be changed.');
        }

        $clean = $check($value);
        if ($clean === null) {
            throw new InvalidArgumentException('That choice is not available.');
        }

        return $clean;
    }

    private function save(User $user, string $type, array $changes, ?bool $enabled): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unknown reminder.');
        }

        $row = ReminderSetting::firstOrNew(['user_id' => $user->id, 'type' => $type]);
        $current = $this->get($user, $type);
        unset($current['enabled']);

        $row->settings = array_merge($current, $changes);
        $row->enabled = $enabled ?? ($row->exists ? (bool) $row->enabled : (bool) config("coach.reminders.{$type}.enabled", true));
        $row->save();
    }
}