<?php

namespace App\Support;

use App\Models\BlogPost;

/**
 * Legacy WordPress → current URL redirect map.
 *
 * The old zapmed.co.za (WordPress + Rank Math) ranked a set of root-level post
 * slugs. Some of those slugs were rewritten when the content moved into the
 * Laravel blog (e.g. "contro" → "zapmed", trimmed wording). To avoid losing the
 * SEO equity on those indexed URLs we 301 each legacy slug to its current home.
 *
 * Keys are the legacy slug (no slashes). Values are either:
 *   - a current slug string (resolved against the live blog / treatments), or
 *   - an absolute path beginning with "/".
 */
class LegacyRedirects
{
    /**
     * Legacy-slug → current-slug map.
     *
     * Hand-maintained entries live here; the bulk of the retired-duplicate
     * redirects produced by the WordPress import reconciliation are loaded from
     * config('legacy_redirects') and merged in at resolve time.
     */
    private const MAP = [];

    /**
     * Merged redirect map: hardcoded MAP + config('legacy_redirects', []).
     *
     * @return array<string,string>
     */
    private static function map(): array
    {
        return array_merge(self::MAP, config('legacy_redirects', []));
    }

    /**
     * Resolve a legacy slug to an absolute path, or null if no redirect applies.
     */
    public static function resolve(string $slug): ?string
    {
        $map = self::map();

        if (! array_key_exists($slug, $map)) {
            return null;
        }

        $target = $map[$slug];

        // Absolute path — use as-is.
        if (str_starts_with($target, '/')) {
            return url($target);
        }

        // Slug target — only redirect if the destination post actually exists
        // and is published, otherwise fall through to a 404 rather than loop.
        $exists = BlogPost::published()->where('slug', $target)->exists();

        return $exists ? url("/{$target}") : null;
    }
}
