<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : $value]
        );

        Cache::forget("setting.{$key}");
    }

    /**
     * Get a JSON setting as array.
     */
    public static function getArray(string $key, array $default = []): array
    {
        $value = static::get($key);
        if (is_string($value)) {
            return json_decode($value, true) ?? $default;
        }
        return $default;
    }

    /**
     * Get enabled languages.
     */
    public static function enabledLanguages(): array
    {
        return static::getArray('enabled_languages', ['en']);
    }
}
