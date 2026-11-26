<?php

namespace App\Console\Commands;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import blog articles from the legacy WordPress site via its REST API,
 * preserving slugs for SEO. Idempotent — upserts on slug, so it can be
 * re-run safely to pick up new/updated posts.
 *
 *   php artisan blog:import-wp                 # import everything (live)
 *   php artisan blog:import-wp --dry-run       # report only, write nothing
 *   php artisan blog:import-wp --no-images     # skip featured-image download
 *   php artisan blog:import-wp --source=https://zapmed.co.za
 */
class ImportWordPressBlog extends Command
{
    protected $signature = 'blog:import-wp
                                {--source=https://zapmed.co.za : Base URL of the WordPress site}
                                {--dry-run : Report what would happen without writing}
                                {--no-images : Do not download featured images}
                                {--overwrite-body : Re-import body/excerpt even for posts that already exist}';

    protected $description = 'Import blog posts from the legacy WordPress REST API, preserving slugs for SEO.';

    /**
     * Map WordPress category slugs to the local BlogCategory slugs.
     * WP has a few extra categories (hair-loss, uncategorized) that fold
     * into the closest local category.
     */
    private array $categoryMap = [
        'general-health' => 'general-health',
        'mens-health'    => 'mens-health',
        'sexual-health'  => 'sexual-health',
        'skincare'       => 'skincare',
        'weight-loss'    => 'weight-loss',
        'womens-health'  => 'womens-health',
        'hair-loss'      => 'skincare',       // dermatological — folds into Skincare
        'uncategorized'  => 'general-health', // safe default
    ];

    public function handle(): int
    {
        $base = rtrim($this->option('source'), '/');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Importing blog posts from {$base}/wp-json/wp/v2" . ($dryRun ? ' (DRY RUN)' : ''));

        // 1. Build WP-category-id => local BlogCategory id map.
        $wpCategories = $this->fetchWpCategories($base);
        if ($wpCategories === null) {
            return self::FAILURE;
        }
        $catIdToLocal = $this->resolveCategoryMap($wpCategories, $dryRun);

        $author = User::where('role', 'admin')->first();

        // 2. Page through posts.
        $created = $updated = $skipped = $imagesPulled = 0;
        $page = 1;

        do {
            $response = Http::acceptJson()->get("{$base}/wp-json/wp/v2/posts", [
                'per_page' => 100,
                'page' => $page,
                '_fields' => 'id,slug,title,content,excerpt,date,modified,categories,featured_media',
            ]);

            if ($response->status() === 400) {
                // WP returns 400 "rest_post_invalid_page_number" past the last page.
                break;
            }

            if (! $response->successful()) {
                $this->error("Failed to fetch posts page {$page}: HTTP {$response->status()}");
                return self::FAILURE;
            }

            $posts = $response->json();
            if (empty($posts)) {
                break;
            }

            foreach ($posts as $wp) {
                $slug = $wp['slug'];
                $existing = BlogPost::where('slug', $slug)->first();

                $wpCatId = $wp['categories'][0] ?? null;
                $localCategoryId = $catIdToLocal[$wpCatId] ?? $catIdToLocal['_fallback'];

                $title = $this->decode($wp['title']['rendered'] ?? $slug);
                $body = $wp['content']['rendered'] ?? '';
                $excerpt = trim(strip_tags($this->decode($wp['excerpt']['rendered'] ?? '')));

                $data = [
                    'blog_category_id' => $localCategoryId,
                    'author_id' => $existing->author_id ?? $author?->id,
                    'title' => $title,
                    'slug' => $slug,
                    'excerpt' => Str::limit($excerpt, 490, '') ?: null,
                    'body' => $body,
                    'status' => 'published',
                    'published_at' => $wp['date'] ?? now(),
                    'reading_time' => BlogPost::calculateReadingTime($body),
                ];

                if ($existing && ! $this->option('overwrite-body')) {
                    // Keep hand-edited body/excerpt; only refresh category + published date.
                    unset($data['body'], $data['excerpt'], $data['title']);
                }

                if ($dryRun) {
                    $this->line(sprintf('  %-8s %s', $existing ? 'update' : 'create', $slug));
                    $existing ? $updated++ : $created++;
                    continue;
                }

                $post = BlogPost::updateOrCreate(['slug' => $slug], $data);
                $existing ? $updated++ : $created++;

                // 3. Featured image.
                if (! $this->option('no-images') && ! empty($wp['featured_media']) && ! $post->featured_image) {
                    if ($path = $this->importFeaturedImage($base, $wp['featured_media'], $slug)) {
                        $post->update(['featured_image' => $path]);
                        $imagesPulled++;
                    }
                }
            }

            $page++;
        } while (true);

        $this->newLine();
        $this->info(sprintf(
            'Done. created=%d updated=%d skipped=%d images=%d | total published now=%d',
            $created, $updated, $skipped, $imagesPulled,
            $dryRun ? BlogPost::published()->count() : BlogPost::published()->count()
        ));

        return self::SUCCESS;
    }

    private function fetchWpCategories(string $base): ?array
    {
        $res = Http::acceptJson()->get("{$base}/wp-json/wp/v2/categories", [
            'per_page' => 100,
            '_fields' => 'id,slug,name',
        ]);

        if (! $res->successful()) {
            $this->error("Failed to fetch WP categories: HTTP {$res->status()}");
            return null;
        }

        return $res->json();
    }

    /**
     * @return array<int|string,int> WP category id => local BlogCategory id, plus '_fallback'.
     */
    private function resolveCategoryMap(array $wpCategories, bool $dryRun): array
    {
        $localBySlug = BlogCategory::pluck('id', 'slug');
        $fallbackId = $localBySlug['general-health'] ?? BlogCategory::value('id');

        $map = ['_fallback' => $fallbackId];

        foreach ($wpCategories as $wpCat) {
            $localSlug = $this->categoryMap[$wpCat['slug']] ?? 'general-health';
            $localId = $localBySlug[$localSlug] ?? $fallbackId;
            $map[$wpCat['id']] = $localId;

            if ($wpCat['slug'] !== $localSlug) {
                $this->line("  category: WP '{$wpCat['slug']}' -> local '{$localSlug}'");
            }
        }

        return $map;
    }

    private function importFeaturedImage(string $base, int $mediaId, string $slug): ?string
    {
        try {
            $res = Http::acceptJson()->get("{$base}/wp-json/wp/v2/media/{$mediaId}", [
                '_fields' => 'source_url',
            ]);
            $sourceUrl = $res->json('source_url');
            if (! $sourceUrl) {
                return null;
            }

            $binary = Http::get($sourceUrl);
            if (! $binary->successful()) {
                return null;
            }

            $ext = pathinfo(parse_url($sourceUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $path = 'blog/' . $slug . '-' . $mediaId . '.' . strtolower($ext);
            Storage::disk('public')->put($path, $binary->body());

            return $path;
        } catch (\Throwable $e) {
            $this->warn("  image skip ({$slug}): {$e->getMessage()}");
            return null;
        }
    }

    private function decode(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
