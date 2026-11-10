<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Support\LegacyRedirects;

class PublicSlugController extends Controller
{
    /**
     * Deterministic resolver for root-level slugs (zapmed.co.za/{slug}).
     *
     * Resolution precedence (first match wins):
     *   1. Treatment landing page  (config/treatments)
     *   2. Published blog post      (served at root to preserve legacy WP URLs)
     *   3. Legacy WordPress redirect (301 to current destination)
     *   4. 404
     *
     * Explicit named routes (blog, category, pricing, legal, etc.) are
     * registered BEFORE this catch-all, so they always take priority.
     */
    public function resolve(string $slug, TreatmentController $treatments)
    {
        // 1. Treatment landing page — delegate to the existing treatment logic.
        if ($this->isTreatment($slug)) {
            return $treatments->show($slug);
        }

        // 2. Published blog post at root.
        $post = BlogPost::published()->where('slug', $slug)->first();
        if ($post) {
            // Render the full-page blog component for this slug. A thin wrapper
            // view mounts the existing BlogShow Livewire component so posts serve
            // at the root URL without duplicating the component logic.
            return response()->view('blog.root-wrapper', ['slug' => $slug]);
        }

        // 3. Legacy WordPress URL → 301 to the best current destination.
        if ($target = LegacyRedirects::resolve($slug)) {
            return redirect($target, 301);
        }

        // 4. Nothing matched.
        abort(404);
    }

    private function isTreatment(string $slug): bool
    {
        foreach (config('treatments') as $category) {
            if (isset($category['treatments'][$slug])) {
                return true;
            }
        }

        return false;
    }
}
