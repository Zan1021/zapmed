<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ImageService
{
    /**
     * Process an uploaded image: convert to WebP, rename with SEO-friendly slug.
     *
     * @param UploadedFile $file The uploaded image
     * @param string $directory Storage directory (e.g. 'assessments/acne')
     * @param string $altContext Context for alt text generation (e.g. 'acne treatment patient photo')
     * @return array{path: string, alt: string, filename: string}
     */
    public function process(UploadedFile $file, string $directory, string $altContext = ''): array
    {
        $seoFilename = $this->generateSeoFilename($altContext ?: $directory);
        $webpFilename = $seoFilename . '.webp';

        // Try to convert to WebP
        if ($this->canConvertToWebp()) {
            $webpContent = $this->convertToWebp($file);

            if ($webpContent) {
                $storagePath = "{$directory}/{$webpFilename}";
                \Illuminate\Support\Facades\Storage::disk('public')->put($storagePath, $webpContent);

                return [
                    'path' => $storagePath,
                    'alt' => $this->generateAltText($altContext ?: $directory),
                    'filename' => $webpFilename,
                ];
            }
        }

        // Fallback: store original with SEO filename
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $fallbackFilename = $seoFilename . '.' . $extension;
        $storagePath = $file->storeAs($directory, $fallbackFilename, 'public');

        return [
            'path' => $storagePath,
            'alt' => $this->generateAltText($altContext ?: $directory),
            'filename' => $fallbackFilename,
        ];
    }

    /**
     * Process multiple uploaded images.
     *
     * @param array $files Array of UploadedFile
     * @param string $directory Storage directory
     * @param string $altContext Context for alt text
     * @return array Array of processed image results
     */
    public function processMultiple(array $files, string $directory, string $altContext = ''): array
    {
        $results = [];

        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile) {
                $context = $altContext ? "{$altContext} photo " . ($index + 1) : '';
                $results[] = $this->process($file, $directory, $context);
            }
        }

        return $results;
    }

    /**
     * Generate an SEO-friendly filename.
     */
    private function generateSeoFilename(string $context): string
    {
        // Create slug from context + timestamp for uniqueness
        $slug = Str::slug($context);
        $timestamp = now()->format('Ymd-His');
        $random = Str::random(4);

        // e.g. "acne-treatment-patient-photo-20260807-091500-a1b2"
        return Str::limit($slug, 50, '') . '-' . $timestamp . '-' . $random;
    }

    /**
     * Generate descriptive alt text for SEO.
     */
    public function generateAltText(string $context): string
    {
        // Clean up the context into readable alt text
        $alt = str_replace(['/', '-', '_'], ' ', $context);
        $alt = preg_replace('/\s+/', ' ', $alt);
        $alt = trim(ucwords($alt));

        // Add Zapmed branding for SEO
        if (!str_contains(strtolower($alt), 'zapmed')) {
            $alt .= ' - Zapmed Telehealth South Africa';
        }

        return $alt;
    }

    /**
     * Convert an uploaded file to WebP format.
     *
     * @return string|null WebP binary data, or null if conversion failed
     */
    private function convertToWebp(UploadedFile $file): ?string
    {
        $mimeType = $file->getMimeType();
        $sourcePath = $file->getRealPath();

        try {
            $image = match ($mimeType) {
                'image/jpeg', 'image/jpg' => imagecreatefromjpeg($sourcePath),
                'image/png' => $this->createFromPngWithAlpha($sourcePath),
                'image/gif' => imagecreatefromgif($sourcePath),
                'image/webp' => imagecreatefromwebp($sourcePath),
                default => null,
            };

            if (!$image) {
                return null;
            }

            // Compress to WebP (quality 80 — good balance of size vs quality)
            ob_start();
            imagewebp($image, null, 80);
            $webpData = ob_get_clean();
            imagedestroy($image);

            return $webpData ?: null;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('WebP conversion failed', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create image from PNG preserving alpha transparency.
     */
    private function createFromPngWithAlpha(string $path)
    {
        $image = imagecreatefrompng($path);

        if ($image) {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        return $image;
    }

    /**
     * Check if GD WebP conversion is available.
     */
    public function canConvertToWebp(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp');
    }
}
