<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Dynamic XML sitemap covering every indexable public URL:
     * home, pricing, blog index, legal, all treatment landing pages,
     * published blog posts (served at root), and category archives.
     */
    public function index(): Response
    {
        $urls = [];

        // Static / high-value pages.
        $urls[] = $this->url(url('/'), now(), '1.0', 'daily');
        $urls[] = $this->url(route('blog'), now(), '0.8', 'daily');

        foreach (['pricing', 'terms-conditions', 'privacy-policy', 'cookie-policy'] as $slug) {
            $urls[] = $this->url(url("/{$slug}"), null, '0.5', 'monthly');
        }

        // Treatment landing pages (root-level slugs from config).
        foreach (config('treatments') as $category) {
            foreach ($category['treatments'] as $slug => $treatment) {
                $urls[] = $this->url(url("/{$slug}"), null, '0.9', 'weekly');
            }
        }

        // Published blog posts — served at root.
        BlogPost::published()
            ->select(['slug', 'updated_at'])
            ->orderByDesc('published_at')
            ->chunk(200, function ($posts) use (&$urls) {
                foreach ($posts as $post) {
                    $urls[] = $this->url(url("/{$post->slug}"), $post->updated_at, '0.7', 'monthly');
                }
            });

        // Category archives.
        foreach (BlogCategory::orderBy('sort_order')->get() as $category) {
            $urls[] = $this->url(url("/category/{$category->slug}"), null, '0.6', 'weekly');
        }

        $xml = $this->render($urls);

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function url(string $loc, $lastmod, string $priority, string $changefreq): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod ? $lastmod->toAtomString() : null,
            'priority' => $priority,
            'changefreq' => $changefreq,
        ];
    }

    private function render(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $u) {
            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . htmlspecialchars($u['loc'], ENT_XML1) . "</loc>\n";
            if ($u['lastmod']) {
                $xml .= "\t\t<lastmod>{$u['lastmod']}</lastmod>\n";
            }
            $xml .= "\t\t<changefreq>{$u['changefreq']}</changefreq>\n";
            $xml .= "\t\t<priority>{$u['priority']}</priority>\n";
            $xml .= "\t</url>\n";
        }

        $xml .= '</urlset>' . "\n";

        return $xml;
    }
}
