<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConvertImagesToWebp extends Command
{
    protected $signature = 'images:convert-webp {--quality=85 : WebP quality (1-100)} {--delete-originals : Delete original files after conversion}';
    protected $description = 'Convert all JPG/PNG images in public/images to WebP format';

    public function handle(): int
    {
        $quality = (int) $this->option('quality');
        $deleteOriginals = $this->option('delete-originals');
        $basePath = public_path('images');

        if (!is_dir($basePath)) {
            $this->error('Directory public/images does not exist.');
            return 1;
        }

        $files = $this->getImageFiles($basePath);
        $converted = 0;
        $skipped = 0;
        $failed = 0;

        $this->info("Found " . count($files) . " image(s) to process.");
        $this->newLine();

        $bar = $this->output->createProgressBar(count($files));

        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $webpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $file);

            // Skip if webp already exists
            if (file_exists($webpPath)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            try {
                $image = match ($extension) {
                    'jpg', 'jpeg' => imagecreatefromjpeg($file),
                    'png' => $this->createFromPng($file),
                    default => null,
                };

                if (!$image) {
                    $failed++;
                    $bar->advance();
                    continue;
                }

                imagewebp($image, $webpPath, $quality);
                imagedestroy($image);

                $originalSize = filesize($file);
                $webpSize = filesize($webpPath);
                $savings = round((1 - $webpSize / $originalSize) * 100);

                $converted++;

                if ($deleteOriginals) {
                    unlink($file);
                }
            } catch (\Exception $e) {
                $this->newLine();
                $this->warn("Failed: " . basename($file) . " - " . $e->getMessage());
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Conversion complete!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Converted', $converted],
                ['Skipped (webp exists)', $skipped],
                ['Failed', $failed],
            ]
        );

        if ($converted > 0 && !$deleteOriginals) {
            $this->newLine();
            $this->comment('Tip: Run with --delete-originals to remove the old JPG/PNG files.');
            $this->comment('Then update blade/config references from .jpg/.png to .webp');
        }

        return 0;
    }

    private function getImageFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.(jpg|jpeg|png)$/i', $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function createFromPng(string $file)
    {
        $image = imagecreatefrompng($file);
        // Handle transparency - create white background
        $width = imagesx($image);
        $height = imagesy($image);
        $background = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($background, 255, 255, 255);
        imagefill($background, 0, 0, $white);
        imagecopy($background, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);
        return $background;
    }
}
