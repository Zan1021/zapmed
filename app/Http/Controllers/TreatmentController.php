<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TreatmentController extends Controller
{
    /**
     * Map config slugs to content file prefixes where they differ.
     * Config slug => content file prefix
     */
    private array $contentSlugMap = [
        'acne-treatment' => 'acne',
        'anti-aging-treatment' => 'anti-ageing',
        'hair-loss-treatment' => 'hair-loss',
        'erectile-dysfunction-treatment' => 'erectile-dysfunction',
        'premature-ejaculation-treatment' => 'premature-ejaculation',
        'cold-sores-treatment' => 'cold-sores',
        'genital-herpes-101' => 'genital-herpes',
        'haemorrhoids-treatment' => 'haemorrhoids',
    ];

    public function show(string $slug)
    {
        $categories = config('treatments');
        $treatment = null;

        foreach ($categories as $categorySlug => $category) {
            if (isset($category['treatments'][$slug])) {
                $treatment = $category['treatments'][$slug];
                $treatment['slug'] = $slug;
                $treatment['category'] = $category['name'];
                $treatment['category_slug'] = $categorySlug;
                $treatment['category_description'] = $category['description'];
                break;
            }
        }

        if (!$treatment) {
            abort(404);
        }

        // Check for a dedicated treatment page first
        if (view()->exists("treatments.pages.{$slug}")) {
            return view("treatments.pages.{$slug}", compact('treatment', 'slug'));
        }

        // Resolve content slug (handles mismatches between URL slug and content file names)
        $contentSlug = $this->contentSlugMap[$slug] ?? $slug;

        return view('treatments.show', compact('treatment', 'slug', 'contentSlug'));
    }
}
