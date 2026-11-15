<?php

namespace App\Support;

/**
 * Keeps uploaded files (blog images, avatars, ...) alive across Forge zero-downtime deploys.
 *
 * Each deploy builds a fresh release folder (…/releases/<id>/), so anything uploaded into that
 * release's storage/app/public is lost on the next deploy. We keep the real files in a folder that
 * deploys never touch (…/shared/storage/app/public) and make each new release's
 * storage/app/public a symlink to it. This runs on the first request after a deploy; afterwards it
 * is a single is_link() check.
 */
class SharedStorage
{
    /**
     * Link $releasePublic (the release's storage/app/public) to $sharedPublic.
     *
     * Returns true when the link is in place after the call, false when nothing was done
     * (no shared folder, or the release folder holds real files we refuse to throw away).
     */
    public static function link(string $releasePublic, string $sharedPublic): bool
    {
        if (is_link($releasePublic)) {
            return true;
        }

        if (! is_dir($sharedPublic)) {
            return false;
        }

        if (is_dir($releasePublic)) {
            // Only throw away the placeholder content a fresh release ships with:
            // the .gitignore and any symlinks (e.g. a manually created blog link).
            $entries = array_diff(scandir($releasePublic) ?: [], ['.', '..']);

            foreach ($entries as $entry) {
                $path = $releasePublic . DIRECTORY_SEPARATOR . $entry;

                if ($entry === '.gitignore' || is_link($path)) {
                    continue;
                }

                // Real uploaded content: move it into the shared folder (never overwrite).
                $target = $sharedPublic . DIRECTORY_SEPARATOR . $entry;
                if (file_exists($target) || ! @rename($path, $target)) {
                    return false;
                }
            }

            foreach (array_diff(scandir($releasePublic) ?: [], ['.', '..']) as $entry) {
                @unlink($releasePublic . DIRECTORY_SEPARATOR . $entry);
            }

            if (! @rmdir($releasePublic)) {
                return false;
            }
        }

        return @symlink($sharedPublic, $releasePublic) || is_link($releasePublic);
    }

    /**
     * The shared folder for the release this code runs from, or null when not on a
     * Forge-style layout (…/releases/<id> next to …/shared).
     */
    public static function sharedPublicFor(string $basePath): ?string
    {
        $real = realpath($basePath);

        if ($real === false || basename(dirname($real)) !== 'releases') {
            return null;
        }

        return dirname($real, 2) . '/shared/storage/app/public';
    }
}
