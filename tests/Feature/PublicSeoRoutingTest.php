<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks in the SEO-critical public routing:
 *  - blog posts served at ROOT (legacy WordPress URL parity)
 *  - /blog/{slug} 301 -> root
 *  - legacy renamed WP slugs 301 -> current post
 *  - /category/{slug} archives
 *  - /sitemap.xml covers pages, treatments, posts, categories
 *  - treatment root slugs still resolve
 *  - per-page SEO meta (canonical, description, OG) rendered
 */
class PublicSeoRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $slug = 'weight-loss', string $name = 'Weight Loss'): BlogCategory
    {
        return BlogCategory::create([
            'name' => $name,
            'slug' => $slug,
            'description' => "{$name} articles.",
            'sort_order' => 1,
        ]);
    }

    private function makePost(string $slug, ?int $categoryId = null): BlogPost
    {
        return BlogPost::create([
            'blog_category_id' => $categoryId ?? $this->category()->id,
            'author_id' => null,
            'title' => 'Test Post ' . $slug,
            'slug' => $slug,
            'excerpt' => 'An excerpt for ' . $slug,
            'body' => '<p>Body content for ' . $slug . '.</p>',
            'meta_title' => 'SEO Title ' . $slug,
            'meta_description' => 'SEO description for ' . $slug . '.',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'reading_time' => 2,
        ]);
    }

    public function test_blog_post_is_served_at_root(): void
    {
        $this->makePost('my-first-post');

        $this->get('/my-first-post')
            ->assertOk()
            ->assertSee('Test Post my-first-post');
    }

    public function test_blog_slug_url_301_redirects_to_root(): void
    {
        $this->makePost('my-first-post');

        $this->get('/blog/my-first-post')
            ->assertRedirect('/my-first-post')
            ->assertStatus(301);
    }

    public function test_legacy_redirect_from_config_301s_to_canonical(): void
    {
        // The canonical post exists; a retired duplicate slug is configured to 301 to it.
        $this->makePost('canonical-post');
        config(['legacy_redirects' => ['retired-old-slug' => 'canonical-post']]);

        $this->get('/retired-old-slug')
            ->assertStatus(301)
            ->assertRedirect('/canonical-post');
    }

    public function test_legacy_redirect_ignored_when_target_missing(): void
    {
        // No canonical post exists → do not loop/redirect, fall through to 404.
        config(['legacy_redirects' => ['retired-old-slug' => 'does-not-exist']]);

        $this->get('/retired-old-slug')->assertNotFound();
    }

    public function test_category_archive_renders(): void
    {
        $cat = $this->category('skincare', 'Skincare');
        $this->makePost('acne-tips', $cat->id);

        $this->get('/category/skincare')
            ->assertOk()
            ->assertSee('Skincare');
    }

    public function test_treatment_root_slug_still_resolves(): void
    {
        // acne-treatment is defined in config/treatments.
        $this->get('/acne-treatment')->assertOk();
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->get('/definitely-not-a-real-slug-xyz')->assertNotFound();
    }

    public function test_sitemap_lists_pages_treatments_posts_and_categories(): void
    {
        $cat = $this->category('mens-health', 'Men\'s Health');
        $this->makePost('low-testosterone-signs', $cat->id);

        $res = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');

        $xml = $res->getContent();

        $this->assertStringContainsString('<loc>' . url('/') . '</loc>', $xml);
        $this->assertStringContainsString(url('/acne-treatment'), $xml);          // treatment
        $this->assertStringContainsString(url('/low-testosterone-signs'), $xml);  // post at root
        $this->assertStringContainsString(url('/category/mens-health'), $xml);    // category
    }

    public function test_blog_post_renders_seo_meta(): void
    {
        $this->makePost('seo-check-post');

        $res = $this->get('/seo-check-post')->assertOk();
        $html = $res->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="' . url('/seo-check-post') . '"', $html);
        $this->assertStringContainsString('name="description" content="SEO description for seo-check-post."', $html);
        $this->assertStringContainsString('property="og:type" content="article"', $html);
    }
}

