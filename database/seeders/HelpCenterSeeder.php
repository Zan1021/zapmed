<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HelpCenterSeeder extends Seeder
{
    /**
     * Seed the help center from the existing config/faq.php content.
     */
    public function run(): void
    {
        $faqData = config('faq');
        $sortOrder = 0;

        foreach ($faqData as $slug => $categoryData) {
            $sortOrder++;

            $category = HelpCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'] ?? null,
                    'icon' => $categoryData['icon'] ?? null,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ]
            );

            $articleOrder = 0;
            foreach ($categoryData['questions'] as $qa) {
                $articleOrder++;
                $articleSlug = Str::slug($qa['q']);

                // Ensure slug uniqueness by appending category slug if needed
                if (HelpArticle::where('slug', $articleSlug)->where('help_category_id', '!=', $category->id)->exists()) {
                    $articleSlug = $slug . '-' . $articleSlug;
                }

                HelpArticle::updateOrCreate(
                    ['slug' => $articleSlug],
                    [
                        'help_category_id' => $category->id,
                        'title' => $qa['q'],
                        'body' => '<p>' . e($qa['a']) . '</p>',
                        'status' => 'published',
                        'visibility' => 'public',
                        'sort_order' => $articleOrder,
                        'published_at' => now(),
                    ]
                );
            }

            $category->updateArticlesCount();
        }
    }
}
