<?php

namespace Zapmed\SparCore\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Converts an uploaded banner image to a small, mobile-friendly WebP at the
 * configured banner dimensions (cover-fit), and stores it on the public disk.
 *
 * Uses GD's imagewebp() (confirmed available). If a server lacks GD-WebP the
 * service degrades gracefully: it stores the validated original and logs a
 * warning rather than hard-failing the upload (spec NFR-2).
 */
class SparBannerImageService
{
    /**
     * Process + store an uploaded banner. Returns the stored relative path.
     */
    public function store(UploadedFile $file): string
    {
        $disk = config('spar.banners.disk', 'public');
        $targetW = (int) config('spar.banners.width', 1080);
        $targetH = (int) config('spar.banners.height', 420);
        $quality = (int) config('spar.banners.quality', 78);

        if (!$this->webpAvailable()) {
            // Fallback: keep the validated original (no conversion possible here).
            Log::warning('SPAR banners: GD WebP not available — storing original without conversion.');

            return $file->store('spar-banners', $disk);
        }

        $src = $this->decode($file);
        if ($src === null) {
            // Unsupported/corrupt image decoded to nothing — store original.
            Log::warning('SPAR banners: could not decode image — storing original.');

            return $file->store('spar-banners', $disk);
        }

        $canvas = $this->coverFit($src, $targetW, $targetH);
        imagedestroy($src);

        // Encode WebP to a temp file, then put on the disk.
        $tmp = tempnam(sys_get_temp_dir(), 'sparban') . '.webp';
        imagewebp($canvas, $tmp, $quality);
        imagedestroy($canvas);

        $path = 'spar-banners/' . Str::uuid()->toString() . '.webp';
        Storage::disk($disk)->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return $path;
    }

    public function delete(?string $path): void
    {
        if (!$path) {
            return;
        }
        $disk = config('spar.banners.disk', 'public');
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    public function webpAvailable(): bool
    {
        return function_exists('imagewebp') && function_exists('gd_info')
            && !empty(gd_info()['WebP Support']);
    }

    /** @return \GdImage|null */
    private function decode(UploadedFile $file)
    {
        $data = file_get_contents($file->getRealPath());
        if ($data === false) {
            return null;
        }
        $img = @imagecreatefromstring($data);

        return $img ?: null;
    }

    /**
     * Cover-fit: scale to fill the target box then centre-crop the overflow, so
     * the banner always fills 1080x420 without distortion.
     *
     * @param  \GdImage  $src
     * @return \GdImage
     */
    private function coverFit($src, int $targetW, int $targetH)
    {
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        $scale = max($targetW / $srcW, $targetH / $srcH);
        $newW = (int) ceil($srcW * $scale);
        $newH = (int) ceil($srcH * $scale);

        $resized = imagecreatetruecolor($newW, $newH);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

        // Centre-crop to the exact target.
        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $offX = (int) (($newW - $targetW) / 2);
        $offY = (int) (($newH - $targetH) / 2);
        imagecopy($canvas, $resized, 0, 0, $offX, $offY, $targetW, $targetH);
        imagedestroy($resized);

        return $canvas;
    }
}
