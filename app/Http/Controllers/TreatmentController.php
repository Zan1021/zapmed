<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TreatmentController extends Controller
{
    public function show(string $slug)
    {
        $categories = config('treatments');
        $treatment = null;
        $categoryName = null;

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

        return view('treatments.show', compact('treatment', 'slug'));
    }
}
